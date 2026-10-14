<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Sichert den LOKALEN Stand der zentralen Datenbank, bevor sun:db:pull sie
 * mit `drop database` ersetzt (#8).
 *
 * Lokal → lokal: Live ist hier nicht beteiligt. In der lokalen `sun` haengen
 * die Test-Tenants mit `.test`-Domains und ganzzahligen IDs, Live hat die
 * echten Portale mit UUIDs — ein voller Pull wirft den Arbeitsstand also
 * weg. Die Sicherung ist der Weg zurueck:
 *
 *   gunzip -c storage/app/db-pull/vor-pull-20261014-183000.sql.gz | mysql
 *
 * Der Dump enthaelt `DROP DATABASE` und `CREATE DATABASE` (siehe
 * LocalMariaDb::dumpCommand), es braucht also keine Vorarbeit. Die
 * Tenant-Datenbanken `tenant_<n>` der Test-Tenants bleiben beim Pull
 * ohnehin liegen und werden nicht mitgesichert.
 */
class LocalBackup
{
    public function __construct(
        private readonly LocalMariaDb $local,
        private readonly string $directory,
        private readonly int $keep,
        private readonly float $freeFactor,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(LocalMariaDb $local): self
    {
        return new self(
            local: $local,
            directory: storage_path((string) config('db-pull.local_backup.directory')),
            keep: max(1, (int) config('db-pull.local_backup.keep')),
            freeFactor: (float) config('db-pull.local_backup.free_factor'),
            timeout: max(60, (int) config('db-pull.local_backup.timeout')),
        );
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** Groesse der zu sichernden Datenbank laut information_schema. */
    public function estimateBytes(string $database): int
    {
        return $this->local->statistics($database)['bytes'];
    }

    /** Geforderter freier Platz fuer den komprimierten Dump. */
    public function requiredBytes(string $database): int
    {
        return (int) ceil($this->estimateBytes($database) * $this->freeFactor);
    }

    public function freeBytes(): int
    {
        $this->ensureDirectory();

        $free = @disk_free_space($this->directory);

        return $free === false ? 0 : (int) $free;
    }

    /**
     * Platzpruefung vor dem Dump. Rueckgabe ist die Fehlermeldung oder null
     * — abgebrochen wird im Befehl, damit die Zentrale unangetastet bleibt.
     */
    public function spaceProblem(string $database): ?string
    {
        $required = $this->requiredBytes($database);
        $free = $this->freeBytes();

        if ($required === 0 || $free >= $required) {
            return null;
        }

        return sprintf(
            'Zu wenig Plattenplatz fuer die Sicherung von `%s`: %s frei, mindestens %s noetig '
            .'(%d %% der Datenbankgroesse %s). Platz schaffen oder mit --no-local-backup ohne Sicherung holen.',
            $database,
            $this->human($free),
            $this->human($required),
            (int) round($this->freeFactor * 100),
            $this->human($this->estimateBytes($database)),
        );
    }

    public function path(string $stamp): string
    {
        return $this->directory.'/vor-pull-'.$stamp.'.sql.gz';
    }

    /**
     * Schreibt den Dump. Erst in eine `.part`-Datei, am Ende umbenannt: ein
     * abgebrochener Lauf hinterlaesst damit keine halbe Sicherung, die beim
     * Zuruecksichern wie eine vollstaendige aussieht.
     *
     * @return array{ok: bool, path: string, bytes: int, seconds: float, note: string}
     */
    public function run(string $database): array
    {
        $this->ensureDirectory();

        // Reste eines abgebrochenen Laufs: sie sind unbrauchbar und belegen
        // ein Gigabyte.
        foreach (glob($this->directory.'/vor-pull-*.sql.gz.part') ?: [] as $leftover) {
            @unlink($leftover);
        }

        $path = $this->path(CarbonImmutable::now()->format('Ymd-His'));
        $partial = $path.'.part';
        $started = microtime(true);

        $script = implode("\n", [
            'set -euo pipefail',
            $this->local->dumpCommand($database).' | gzip -c > '.escapeshellarg($partial),
        ])."\n";

        try {
            $result = Process::timeout($this->timeout)
                ->env($this->local->clientEnv())
                ->input($script)
                ->run(['bash', '-s']);
        } catch (Throwable $exception) {
            // Zeitgrenze (oder ein Fehler beim Starten): die angefangene
            // Datei weg, Fehler als Ergebnis zurueck. Eine Ausnahme bis in
            // den Befehl wuerde dort als "Live nicht erreichbar" gedeutet.
            @unlink($partial);

            return [
                'ok' => false,
                'path' => $path,
                'bytes' => 0,
                'seconds' => microtime(true) - $started,
                'note' => $exception->getMessage(),
            ];
        }

        $seconds = microtime(true) - $started;

        if (! $result->successful()) {
            @unlink($partial);

            return [
                'ok' => false,
                'path' => $path,
                'bytes' => 0,
                'seconds' => $seconds,
                'note' => $this->errorLine($result->errorOutput()) !== ''
                    ? $this->errorLine($result->errorOutput())
                    : "mysqldump mit Code {$result->exitCode()} beendet",
            ];
        }

        if (! @rename($partial, $path)) {
            @unlink($partial);

            return [
                'ok' => false,
                'path' => $path,
                'bytes' => 0,
                'seconds' => $seconds,
                'note' => "Sicherung konnte nicht nach {$path} umbenannt werden.",
            ];
        }

        $bytes = (int) (@filesize($path) ?: 0);

        // Ein leerer oder winziger Dump ist kein Erfolg: dann hat mysqldump
        // nichts geliefert und die Sicherung waere wertlos (Lehre aus der
        // Live-Sicherung, die ueber Monate leere Dateien schrieb).
        if ($bytes < 1024) {
            return [
                'ok' => false,
                'path' => $path,
                'bytes' => $bytes,
                'seconds' => $seconds,
                'note' => sprintf('Sicherung ist nur %d Byte gross — mysqldump hat nichts geliefert.', $bytes),
            ];
        }

        return [
            'ok' => true,
            'path' => $path,
            'bytes' => $bytes,
            'seconds' => $seconds,
            'note' => '',
        ];
    }

    /**
     * Haelt die letzten `keep` Sicherungen, loescht die aelteren. 7,5 GB je
     * Stand summieren sich sonst schnell auf der Laptop-Platte.
     *
     * @return array<int, string> geloeschte Dateinamen, jung zuerst
     */
    public function prune(): array
    {
        $files = $this->existing();
        $removed = [];

        foreach (array_slice($files, $this->keep) as $file) {
            if (@unlink($file)) {
                $removed[] = basename($file);
            }
        }

        return $removed;
    }

    /**
     * Vorhandene Sicherungen, neueste zuerst. Angefangene `.part`-Dateien
     * zaehlen nicht mit.
     *
     * @return array<int, string> absolute Pfade
     */
    public function existing(): array
    {
        $files = glob($this->directory.'/vor-pull-*.sql.gz') ?: [];

        // Der Zeitstempel im Namen ist sortierbar — kein filemtime, das
        // beim Kopieren der Datei kippt.
        rsort($files);

        return array_values($files);
    }

    public function keep(): int
    {
        return $this->keep;
    }

    public function human(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        foreach (['KB', 'MB', 'GB', 'TB'] as $index => $unit) {
            $value = $bytes / (1024 ** ($index + 1));

            if ($value < 1024 || $unit === 'TB') {
                return sprintf('%.1f %s', $value, $unit);
            }
        }

        throw new RuntimeException('unerreichbar');
    }

    /** Erste echte Fehlerzeile von mysqldump, ohne Deprecation-Rauschen. */
    private function errorLine(string $output): string
    {
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, '[Warning]') || str_contains($line, 'Using a password')) {
                continue;
            }

            return $line;
        }

        return '';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Verzeichnis {$this->directory} konnte nicht angelegt werden.");
        }
    }
}
