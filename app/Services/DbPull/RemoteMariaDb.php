<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Lesender Zugriff auf die Datenbanken der Produktion fuer `sun:db:pull` (#1).
 *
 * Drei Regeln bestimmen den Aufbau dieser Klasse:
 *
 * 1. Auf Live wird nichts geschrieben. Ausgefuehrt werden ausschliesslich
 *    `mariadb -N -B -e "select ..."` und `mariadb-dump`, beides mit dem
 *    Read-only-Benutzer `sun_sync` (nur SELECT, SHOW VIEW auf `sun`.* und
 *    `tenant\_%`.*, Abnahme in docs/db-pull.md). Es gibt hier keinen Pfad,
 *    der eine Datei auf Live anlegt.
 * 2. Das Passwort steht nie in `argv`, sonst liest es jeder `ps`-Aufruf auf
 *    der Produktion mit. Deshalb geht das Remote-Kommando als Skript ueber
 *    STDIN an `bash -s` und setzt `MYSQL_PWD` dort — in der Prozessliste
 *    steht nur "bash -s". Gleiche Vorgehensweise wie
 *    scripts/sun-db-sicherung.sh.
 * 3. Aufgerufen werden die `mariadb*`-Programme, nicht die `mysql*`-Aliase:
 *    letztere schreiben auf MariaDB 11.4 bei jedem Aufruf eine
 *    Deprecation-Warnung nach stderr, die im Protokoll wie ein Fehler
 *    aussieht.
 *
 * Die Dumps werden gestreamt (`| gzip -1` auf Live, Entpacken lokal) und
 * nicht zwischengespeichert: /home der Produktion liegt auf zwei rotierenden
 * HDDs, eine Zwischendatei dort zieht die Portale runter.
 */
class RemoteMariaDb
{
    public function __construct(
        private readonly string $sshHost,
        private readonly ?string $sshUser,
        private readonly ?string $sshPort,
        private readonly ?string $sshKeyPath,
        private readonly string $username,
        private readonly string $password,
        private readonly string $storagePath,
        private readonly string $dumpBin,
        private readonly string $clientBin,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        $username = (string) config('db-pull.db_user');
        $password = (string) config('db-pull.db_password');

        if ($username === '' || $password === '') {
            throw new RuntimeException(
                'SYNC_DB_USER oder SYNC_DB_PASSWORD fehlt in der lokalen .env (siehe docs/db-pull.md).'
            );
        }

        return new self(
            sshHost: (string) config('db-pull.ssh_host'),
            sshUser: config('db-pull.ssh_user'),
            sshPort: config('db-pull.ssh_port') === null ? null : (string) config('db-pull.ssh_port'),
            sshKeyPath: config('db-pull.ssh_key_path'),
            username: $username,
            password: $password,
            storagePath: rtrim((string) config('db-pull.remote_storage_path'), '/'),
            dumpBin: (string) config('db-pull.remote_dump_bin'),
            clientBin: (string) config('db-pull.remote_client_bin'),
            timeout: (int) config('db-pull.timeout'),
        );
    }

    /**
     * Name der zentralen Datenbank, gelesen aus der .env der Produktion —
     * nicht geraten und nicht lokal gepflegt.
     */
    public function centralDatabase(): string
    {
        $name = trim($this->shell(sprintf(
            '{ grep -m1 "^DB_DATABASE=" %s/.env || true; } | cut -d= -f2- | tr -d \'"\'',
            escapeshellarg($this->appDir()),
        )));

        if ($name === '') {
            throw new RuntimeException("DB_DATABASE nicht in {$this->appDir()}/.env gefunden.");
        }

        return $name;
    }

    /**
     * Die LEBENDEN Tenant-Datenbanken. Bewusst `show databases` und nicht die
     * `tenants`-Tabelle oder CloudPanels db.sq3: am 08.10.2026 gehoerten neun
     * registrierte Datenbanken zu geloeschten Tenants (#39). Fremde
     * Datenbanken (analytics, leads, minber*) sieht `sun_sync` ohnehin nicht.
     *
     * @return array<int, string>
     */
    public function tenantDatabases(string $prefix): array
    {
        $out = $this->sql(sprintf("show databases like '%s\\_%%'", rtrim($prefix, '_')));

        return array_values(array_filter(array_map('trim', explode("\n", $out))));
    }

    /**
     * Tabellen je Datenbank in einem Rutsch.
     *
     * @param  array<int, string>  $databases
     * @return array<string, array<int, string>>
     */
    public function tablesByDatabase(array $databases): array
    {
        if ($databases === []) {
            return [];
        }

        $list = implode(',', array_map(
            fn (string $db): string => "'".str_replace("'", "''", $db)."'",
            $databases
        ));

        $out = $this->sql(
            'select table_schema, table_name from information_schema.tables '
            ."where table_type = 'BASE TABLE' and table_schema in ({$list}) order by table_schema, table_name"
        );

        $tables = [];

        foreach (explode("\n", trim($out)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$schema, $table] = array_pad(explode("\t", $line, 2), 2, '');
            $tables[$schema][] = $table;
        }

        return $tables;
    }

    /**
     * Prueft vor dem ersten Dump, dass die MariaDB-Programme auf der
     * Produktion im Pfad liegen — sonst liefert die Pipeline stillschweigend
     * einen leeren Dump.
     */
    public function ensureBinaries(): void
    {
        $this->shell(sprintf(
            'command -v %s > /dev/null && command -v %s > /dev/null',
            escapeshellarg($this->clientBin),
            escapeshellarg($this->dumpBin),
        ));
    }

    /**
     * Baut das Remote-Skript fuer einen Dump. Zurueck kommt nur das Skript —
     * ausgefuehrt wird es von DbPullCommand in einer Pipeline, die direkt in
     * den lokalen Client laeuft.
     *
     * Flags nach dem Befund in #2 (MariaDB 11.4): `--no-tablespaces` macht
     * das PROCESS-Recht entbehrlich, `--routines --triggers` laufen trotz
     * fehlender TRIGGER-/EVENT-Rechte durch (es gibt auf keiner der 24
     * Datenbanken welche), `--set-gtid-purged` und `--column-statistics`
     * existieren hier nicht und werden nie gesetzt.
     *
     * @param  array<int, string>  $tables  leere Liste = ganze Datenbank
     * @param  array<int, string>  $ignore  Tabellen, die ganz ausgelassen werden
     */
    public function dumpScript(string $database, array $tables = [], array $ignore = [], bool $noData = false): string
    {
        $arguments = [
            '-u', $this->username,
            '--single-transaction',
            '--skip-lock-tables',
            '--quick',
            '--no-tablespaces',
            '--skip-add-locks',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
        ];

        if ($noData) {
            $arguments[] = '--no-data';
        }

        foreach ($ignore as $table) {
            $arguments[] = '--ignore-table='.$database.'.'.$table;
        }

        $arguments[] = $database;

        foreach ($tables as $table) {
            $arguments[] = $table;
        }

        $command = implode(' ', array_map('escapeshellarg', $arguments));

        // nice/ionice: der Dump liest von denselben HDDs, von denen die
        // Portale ihre Anfragen beantworten.
        return $this->script(
            'nice -n 10 ionice -c2 -n7 '.escapeshellarg($this->dumpBin)." {$command} | gzip -1"
        );
    }

    /**
     * Der SSH-Aufruf als Kommando-Array. Das Skript kommt ueber STDIN, der
     * Dump kommt auf STDOUT zurueck.
     *
     * @return array<int, string>
     */
    public function sshCommand(): array
    {
        $command = ['ssh', '-o', 'BatchMode=yes'];

        if ($this->sshPort !== null && $this->sshPort !== '') {
            $command[] = '-p';
            $command[] = $this->sshPort;
        }

        if ($this->sshKeyPath !== null && $this->sshKeyPath !== '') {
            $command[] = '-i';
            $command[] = $this->expandHome($this->sshKeyPath);
        }

        $command[] = $this->sshTarget();
        $command[] = 'bash';
        $command[] = '-s';

        return $command;
    }

    public function sshTarget(): string
    {
        return $this->sshUser === null || $this->sshUser === ''
            ? $this->sshHost
            : $this->sshUser.'@'.$this->sshHost;
    }

    public function sshHost(): string
    {
        return $this->sshHost;
    }

    /** Live-`storage/`, Basis des Storage-Syncs (#6). */
    public function storagePath(): string
    {
        return $this->storagePath;
    }

    /** Die Live-.env liegt ein Verzeichnis ueber `storage/`. */
    public function appDir(): string
    {
        return dirname($this->storagePath);
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * Fuer Ausgaben im Probelauf: das Passwort aus einem Skript nehmen.
     * Gedruckt wird nur maskierter Text.
     */
    public function maskSecrets(string $text): string
    {
        return $this->password === '' ? $text : str_replace($this->password, '***', $text);
    }

    /** Eine SQL-Abfrage, Ergebnis als Rohtext (Tabulator-getrennt, ohne Kopf). */
    private function sql(string $sql): string
    {
        return $this->run(sprintf(
            '%s -u %s -N -B -e %s',
            escapeshellarg($this->clientBin),
            escapeshellarg($this->username),
            escapeshellarg($sql),
        ));
    }

    /** Ein lesendes Shell-Kommando auf der Produktion (Live-.env, Programmpruefung). */
    private function shell(string $command): string
    {
        return $this->run($command);
    }

    private function run(string $body): string
    {
        $result = Process::timeout(120)
            ->input($this->script($body))
            ->run($this->sshCommand());

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'Live nicht erreichbar oder Abfrage abgelehnt (%s): %s',
                $this->sshTarget(),
                trim($result->errorOutput()) !== '' ? trim($result->errorOutput()) : 'Exit '.$result->exitCode(),
            ));
        }

        return $result->output();
    }

    /**
     * Rahmen jedes Remote-Skripts: strikte Shell, Passwort nur in der
     * Umgebung. Der Inhalt geht ueber STDIN, damit nichts davon in der
     * Prozessliste der Produktion landet.
     */
    private function script(string $body): string
    {
        $password = str_replace("'", "'\\''", $this->password);

        return implode("\n", [
            'set -euo pipefail',
            "MYSQL_PWD='{$password}'",
            'export MYSQL_PWD',
            $body,
            '',
        ]);
    }

    private function expandHome(string $path): string
    {
        if (! str_starts_with($path, '~')) {
            return $path;
        }

        $home = (string) (getenv('HOME') ?: '');

        return $home === '' ? $path : $home.substr($path, 1);
    }
}
