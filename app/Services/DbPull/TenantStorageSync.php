<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Optionaler Storage-Sync fuer `sun:db:pull --storage` (#1, #6).
 *
 * Die Dateien eines Portals liegen auf Live unter
 * `<storage>/tenant<uuid>/app/public` — `suffix_base` in config/tenancy.php
 * ist `tenant` OHNE Unterstrich, die Datenbanken heissen dagegen
 * `tenant_<uuid>`. Beide Namen kommen aus der Tenancy-Konfiguration, keiner
 * wird aus dem anderen abgeleitet (#2-Befund: im Live-storage/ liegen
 * ausserdem Ordner von Tenants, die es nicht mehr gibt — deshalb iteriert
 * dieser Sync nur ueber die lebenden Tenants, nie ueber die Verzeichnisse).
 *
 * Gespiegelt wird ausschliesslich von Live nach lokal:
 *
 * * `--delete` nur LOKAL und nur auf `storage/tenant<id>/app/public` (der
 *   Zielpfad wird vor jedem Lauf dagegen geprueft, siehe
 *   {@see self::assertDeletableTarget()}) — nie auf `storage/` gesamt und
 *   schon gar nicht auf der Gegenseite,
 * * gedrosselt (`--bwlimit`), weil /home der Produktion auf rotierenden HDDs
 *   liegt,
 * * seriell, ein Portal nach dem anderen,
 * * ohne die Ausschluesse aus `db-pull.storage_exclude` (Sicherungen,
 *   Protokolle, Cache, angefangene Uploads).
 *
 * Zwei Begrenzungen (#9), weil ein einzelnes Portal 127 GB echte Firmenbilder
 * haelt und ein voller Spiegel aller Portale auf keinen Laptop passt:
 *
 * * `$subPath` (`--storage-path=media/logos`) spiegelt nur einen Unterbaum
 *   von `app/public`. Quelle und Ziel wandern dann beide in den Unterbaum,
 *   `--delete` wirkt also nur dort.
 * * `$maxSize` (`--max-size=2M`) laesst grosse Originale draussen. rsync
 *   behandelt zu grosse Dateien wie Ausschluesse: zusammen mit `--delete`
 *   bleiben sie lokal vorhanden statt geloescht zu werden (es wird bewusst
 *   kein `--delete-excluded` gesetzt).
 */
class TenantStorageSync
{
    /** Erkennbares Praefix, damit die Dateizeilen von rsync-Meldungen unterscheidbar sind. */
    private const OUT_FORMAT = 'SUNFILE|%i|%l|%n';

    private ?string $excludeFile = null;

    /**
     * @param  array<int, string>  $exclude
     */
    public function __construct(
        private readonly string $sshTarget,
        private readonly string $remoteStoragePath,
        private readonly string $storagePrefix,
        private readonly string $bwlimit,
        private readonly int $timeout,
        private readonly array $exclude = [],
        private readonly ?string $sshPort = null,
        private readonly ?string $sshKeyPath = null,
        private readonly string $subPath = '',
        private readonly ?string $maxSize = null,
    ) {}

    public function __destruct()
    {
        if ($this->excludeFile !== null && is_file($this->excludeFile)) {
            @unlink($this->excludeFile);
        }
    }

    public static function fromConfig(RemoteMariaDb $remote, ?string $subPath = null, ?string $maxSize = null): self
    {
        $subPath ??= (string) config('db-pull.storage_path', '');
        $maxSize ??= config('db-pull.storage_max_size');

        return new self(
            sshTarget: $remote->sshTarget(),
            remoteStoragePath: $remote->storagePath(),
            storagePrefix: (string) config('db-pull.tenant_storage_prefix'),
            bwlimit: (string) config('db-pull.bwlimit'),
            timeout: (int) config('db-pull.timeout'),
            exclude: array_values((array) config('db-pull.storage_exclude', [])),
            sshPort: config('db-pull.ssh_port') === null ? null : (string) config('db-pull.ssh_port'),
            sshKeyPath: config('db-pull.ssh_key_path'),
            subPath: self::normalizeSubPath((string) $subPath),
            maxSize: self::normalizeMaxSize($maxSize === null ? null : (string) $maxSize),
        );
    }

    /**
     * Normiert einen Teilpfad relativ zu `app/public`: ohne fuehrenden und
     * abschliessenden Schraegstrich, ohne Doppelschraegstriche. Ein Pfad, der
     * aus dem Tenant-Storage herausfuehren koennte, ist ein Aufruffehler —
     * er landet sonst in `--delete`.
     */
    public static function normalizeSubPath(string $subPath): string
    {
        $subPath = trim(str_replace('\\', '/', $subPath));
        $subPath = trim(preg_replace('#/+#', '/', $subPath) ?? '', '/');

        if ($subPath === '' || $subPath === '.') {
            return '';
        }

        if (preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $subPath) !== 1
            || in_array('..', explode('/', $subPath), true)) {
            throw new RuntimeException(
                "Unerwarteter Teilpfad fuer --storage-path: {$subPath} (erlaubt ist ein relativer Pfad unter app/public, z.B. media/logos)."
            );
        }

        return $subPath;
    }

    /**
     * Groessenangabe fuer `--max-size` in rsync-Schreibweise (1024, 2M,
     * 1.5G, 500KiB). Ein Tippfehler soll hier auffallen und nicht erst als
     * rsync-Abbruch mitten im Lauf.
     */
    public static function normalizeMaxSize(?string $maxSize): ?string
    {
        $maxSize = trim((string) $maxSize);

        if ($maxSize === '') {
            return null;
        }

        if (preg_match('/^\d+(\.\d+)?([KMGTP]i?B?|B)?$/i', $maxSize) !== 1) {
            throw new RuntimeException(
                "Unerwartete Groesse fuer --max-size: {$maxSize} (rsync-Schreibweise, z.B. 2M, 500K, 1.5G)."
            );
        }

        return $maxSize;
    }

    /** Der gespiegelte Unterbaum von app/public ('' = alles). */
    public function subPath(): string
    {
        return $this->subPath;
    }

    public function maxSize(): ?string
    {
        return $this->maxSize;
    }

    /** Eine Zeile fuer Ausgabe und Protokoll, was gerade begrenzt ist. */
    public function limitsLabel(): string
    {
        $parts = [];

        if ($this->subPath !== '') {
            $parts[] = 'nur app/public/'.$this->subPath;
        }

        if ($this->maxSize !== null) {
            $parts[] = 'max. '.$this->maxSize.' je Datei';
        }

        return implode(', ', $parts);
    }

    /**
     * Fehlt rsync lokal, ist das kein Fehler eines einzelnen Portals,
     * sondern ein Aufruffehler — der Befehl prueft das vor dem ersten Lauf.
     */
    public static function missingRsyncHint(): ?string
    {
        if (Process::run(['which', 'rsync'])->successful()) {
            return null;
        }

        return 'rsync ist lokal nicht installiert — mit "brew install rsync" nachruesten '
            .'(macOS liefert nur openrsync; beides genuegt, der Befehl braucht nur ein rsync im PATH).';
    }

    /**
     * Holt `storage/tenant<id>/app/public` eines Portals.
     *
     * @return array{ok: bool, missing: bool, target: string, error: string, files: int, bytes: int, deleted: int, summary: string}
     */
    public function pull(string $tenantId, bool $dryRun = false): array
    {
        $suffix = $this->subPath === '' ? '' : '/'.$this->subPath;

        $remote = sprintf(
            '%s:%s/%s%s/app/public%s/',
            $this->sshTarget,
            $this->remoteStoragePath,
            $this->storagePrefix,
            $tenantId,
            $suffix,
        );

        $target = storage_path($this->storagePrefix.$tenantId.'/app/public'.$suffix);

        $this->assertDeletableTarget($tenantId, $target);

        if (! $dryRun && ! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
            return $this->failure($target, "Zielordner nicht anlegbar: {$target}");
        }

        $arguments = [
            'rsync',
            '-az',
            '--delete',
            '--partial',
            '--stats',
            '--out-format='.self::OUT_FORMAT,
            '--bwlimit='.$this->bwlimit,
        ];

        if ($this->maxSize !== null) {
            // Bewusst ohne --delete-excluded: eine lokal vorhandene, jetzt zu
            // grosse Datei bleibt liegen, statt beim naechsten Lauf mit
            // Begrenzung zu verschwinden (#9).
            $arguments[] = '--max-size='.$this->maxSize;
        }

        $excludeFile = $this->excludeFile();

        if ($excludeFile !== null) {
            $arguments[] = '--exclude-from='.$excludeFile;
        }

        if ($dryRun) {
            // Zeigt Dateizahl und Volumen, ohne etwas zu uebertragen —
            // ein Tenant-Storage ist schnell zwei Gigabyte gross.
            $arguments[] = '--dry-run';
        }

        $arguments = array_merge($arguments, ['-e', $this->sshCommand(), $remote, $target.'/']);

        $result = Process::timeout($this->timeout)->run($arguments);

        if (! $result->successful()) {
            // Ein Teilpfad muss nicht in jedem Portal existieren (nicht jedes
            // hat z.B. media/logos) — das ist kein Fehler, nur nichts zu tun.
            if ($this->subPath !== '' && $this->missingRemoteSubtree($result)) {
                // Der leer angelegte Zielordner soll nach einem Tippfehler im
                // Teilpfad nicht liegen bleiben.
                if (is_dir($target) && array_diff((array) scandir($target), ['.', '..']) === []) {
                    @rmdir($target);
                }

                return [
                    'ok' => true,
                    'missing' => true,
                    'target' => $target,
                    'error' => '',
                    'files' => 0,
                    'bytes' => 0,
                    'deleted' => 0,
                    'summary' => "app/public/{$this->subPath} gibt es auf Live nicht — nichts zu holen",
                ];
            }

            return $this->failure($target, $this->errorLine($result));
        }

        $counted = $this->countTransfers($result->output());

        return [
            'ok' => true,
            'missing' => false,
            'target' => $target,
            'error' => '',
            'files' => $counted['files'],
            'bytes' => $counted['bytes'],
            'deleted' => $counted['deleted'],
            'summary' => $this->summarize($counted, $result->output()),
        ];
    }

    /**
     * `--delete` loescht lokal. Der Zielpfad muss deshalb nachweislich der
     * Tenant-Storage sein und darf nie `storage/` selbst oder ein
     * Elternverzeichnis davon sein.
     */
    private function assertDeletableTarget(string $tenantId, string $target): void
    {
        // Eine ID mit / oder .. wuerde aus storage/tenant<id>/app/public
        // herausfuehren — dann zeigte --delete irgendwohin.
        if (preg_match('/^[A-Za-z0-9._-]+$/', $tenantId) !== 1 || str_contains($tenantId, '..')) {
            throw new RuntimeException("Unerwartete Portal-ID fuer rsync: {$tenantId}");
        }

        // Mit --storage-path endet das Ziel nicht mehr auf /app/public (#9).
        // Geprueft wird deshalb gegen den erwarteten Pfad selbst: er muss
        // genau der app/public dieses Portals oder ein Pfad darunter sein.
        $base = storage_path($this->storagePrefix.$tenantId.'/app/public');
        $expected = $this->subPath === '' ? $base : $base.'/'.$this->subPath;

        if ($target !== $expected
            || str_contains($target, '/../')
            || ! str_starts_with($target, storage_path($this->storagePrefix))) {
            throw new RuntimeException("Unerwarteter Zielpfad fuer rsync --delete: {$target}");
        }
    }

    /**
     * @return array{ok: bool, missing: bool, target: string, error: string, files: int, bytes: int, deleted: int, summary: string}
     */
    private function failure(string $target, string $error): array
    {
        return [
            'ok' => false,
            'missing' => false,
            'target' => $target,
            'error' => $error,
            'files' => 0,
            'bytes' => 0,
            'deleted' => 0,
            'summary' => '',
        ];
    }

    /**
     * Zaehlt aus den `--out-format`-Zeilen, was wirklich uebertragen wurde.
     * Die `--stats`-Bloecke der beiden rsync-Familien (macOS/openrsync und
     * rsync 3 auf Live) sind unterschiedlich beschriftet und melden beim
     * Delta nicht dasselbe; die Dateizeilen sind bei beiden gleich
     * aufgebaut.
     *
     * Empfangene Dateien erkennt man am Aenderungskennzeichen `>` an erster
     * Stelle (`%i`), Loeschungen an der Zeile `*deleting <pfad>`.
     *
     * @return array{files: int, bytes: int, deleted: int}
     */
    private function countTransfers(string $output): array
    {
        $files = 0;
        $bytes = 0;
        $deleted = 0;

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, '*deleting')) {
                $deleted++;

                continue;
            }

            if (! str_starts_with($line, 'SUNFILE|')) {
                continue;
            }

            [, $change, $size] = array_pad(explode('|', $line, 4), 4, '');

            if (! str_starts_with($change, '>')) {
                continue;
            }

            $files++;
            $bytes += (int) $size;
        }

        return ['files' => $files, 'bytes' => $bytes, 'deleted' => $deleted];
    }

    /**
     * Eine Zeile fuer Ausgabe und Protokoll: Delta zuerst (das ist beim
     * zweiten Lauf die interessante Zahl), danach der Gesamtbestand aus
     * `--stats`.
     *
     * @param  array{files: int, bytes: int, deleted: int}  $counted
     */
    private function summarize(array $counted, string $output): string
    {
        $parts = [sprintf(
            '%d Datei(en) / %s uebertragen',
            $counted['files'],
            $this->humanBytes($counted['bytes']),
        )];

        if ($counted['deleted'] > 0) {
            $parts[] = sprintf('%d lokal geloescht', $counted['deleted']);
        }

        $total = $this->statsLine($output, ['Number of files:']);

        if ($total !== null) {
            $parts[] = 'Bestand: '.$total;
        }

        $size = $this->statsLine($output, ['Total file size:']);

        if ($size !== null) {
            $parts[] = $size;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, string>  $prefixes
     */
    private function statsLine(string $output, array $prefixes): ?string
    {
        foreach (explode("\n", $output) as $line) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($line, $prefix)) {
                    return trim(substr($line, strlen($prefix)));
                }
            }
        }

        return null;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf($unit === 0 ? '%d %s' : '%.1f %s', $value, $units[$unit]);
    }

    /**
     * Die Ausschlussliste aus der Konfiguration als Datei — rsync liest sie
     * mit `--exclude-from`, damit die Muster nicht als Dutzend Argumente in
     * der Kommandozeile stehen.
     */
    private function excludeFile(): ?string
    {
        $patterns = array_values(array_filter(array_map(
            static fn ($pattern): string => trim((string) $pattern),
            $this->exclude,
        ), static fn (string $pattern): bool => $pattern !== ''));

        if ($patterns === []) {
            return null;
        }

        if ($this->excludeFile !== null && is_file($this->excludeFile)) {
            return $this->excludeFile;
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'sun-db-pull-exclude-');
        file_put_contents($path, implode("\n", $patterns)."\n");
        $this->excludeFile = $path;

        return $path;
    }

    /** rsync meldet einen fehlenden Quellpfad mit Code 23 bzw. 3. */
    private function missingRemoteSubtree(ProcessResult $result): bool
    {
        return in_array($result->exitCode(), [3, 23], true)
            && preg_match('/No such file or directory|change_dir|failed to stat/i', $result->errorOutput()) === 1;
    }

    private function errorLine(ProcessResult $result): string
    {
        $error = trim($result->errorOutput());
        $error = $error !== '' ? $error : "rsync mit Code {$result->exitCode()} beendet";

        // openrsync (macOS-Bordmittel) kennt --max-size nicht; der Abbruch
        // sieht sonst wie ein Verbindungsfehler aus.
        if ($this->maxSize !== null && preg_match('/unknown option|unsupported|invalid option/i', $error) === 1) {
            $error .= ' — das lokale rsync kennt --max-size offenbar nicht, mit "brew install rsync" ein rsync 3 nachruesten.';
        }

        return $error;
    }

    private function sshCommand(): string
    {
        $command = 'ssh -o BatchMode=yes';

        if ($this->sshPort !== null && $this->sshPort !== '') {
            $command .= ' -p '.escapeshellarg($this->sshPort);
        }

        if ($this->sshKeyPath !== null && $this->sshKeyPath !== '') {
            $command .= ' -i '.escapeshellarg($this->sshKeyPath);
        }

        return $command;
    }
}
