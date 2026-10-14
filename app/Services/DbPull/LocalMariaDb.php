<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Das lokale Ziel des Pulls (#1).
 *
 * Hier liegt die Richtungssperre: alles, was diese Klasse tut, ist
 * schreibend — also darf sie nur auf einem lokalen Server arbeiten. Zeigt
 * die lokale Datenbankverbindung nicht auf diesen Rechner oder laeuft die
 * Anwendung in der Umgebung "production", bricht `assertLocal()` ab. Eine
 * Option, die das aufhebt, gibt es absichtlich nicht.
 */
class LocalMariaDb
{
    /** Verbindungsname, unter dem die jeweils gezogene Datenbank angesprochen wird. */
    public const CONNECTION = 'db_pull_target';

    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', ''];

    public function __construct(
        private readonly string $host,
        private readonly string $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $clientBin,
        private readonly string $dumpBin,
    ) {}

    public static function fromConfig(): self
    {
        $central = config('database.connections.central');

        return new self(
            host: (string) ($central['host'] ?? ''),
            port: (string) ($central['port'] ?? '3306'),
            username: (string) ($central['username'] ?? ''),
            password: (string) ($central['password'] ?? ''),
            clientBin: (string) config('db-pull.local_mysql_cmd'),
            dumpBin: (string) config('db-pull.local_mysqldump_cmd'),
        );
    }

    /**
     * Richtungssperre. Fehlermeldung statt Exception, damit der Befehl mit
     * einem eigenen Exit-Code abbrechen kann.
     */
    public function localityProblem(): ?string
    {
        if (! app()->environment('local')) {
            return sprintf(
                'APP_ENV ist "%s" — sun:db:pull laeuft ausschliesslich mit APP_ENV=local.',
                (string) app()->environment(),
            );
        }

        if (! in_array($this->host, self::LOCAL_HOSTS, true)) {
            return "Die Verbindung \"central\" zeigt auf {$this->host}, nicht auf diesen Rechner.";
        }

        return null;
    }

    /**
     * Legt die Zieldatenbank neu an. Nur beim vollen Pull einer Datenbank:
     * ein Teil-Pull (--tables) darf den Rest des lokalen Standes nicht
     * wegwerfen.
     */
    public function recreateDatabase(string $database): void
    {
        $this->central()->statement('drop database if exists `'.$this->quoteName($database).'`');
        $this->createDatabase($database);
    }

    public function createDatabase(string $database): void
    {
        $this->central()->statement(
            'create database if not exists `'.$this->quoteName($database).'` '
            .'character set utf8mb4 collate utf8mb4_unicode_ci'
        );
    }

    /** Kommando fuer das Ende der Import-Pipeline, fertig gequotet. */
    public function importCommand(string $database): string
    {
        $arguments = [
            $this->clientBin,
            '--host='.($this->host === '' ? '127.0.0.1' : $this->host),
            '--port='.$this->port,
            '--user='.$this->username,
            '--default-character-set=utf8mb4',
            $database,
        ];

        return implode(' ', array_map('escapeshellarg', $arguments));
    }

    /**
     * Kommando fuer die Sicherung des lokalen Standes (#8), fertig
     * gequotet. `--databases` bringt CREATE DATABASE und USE in den Dump,
     * zusammen mit `--add-drop-database` laesst sich die Sicherung also mit
     * `gunzip -c <datei> | mysql` ohne Vorarbeit zurueckspielen.
     *
     * Lokal laeuft MySQL 9 aus Homebrew, nicht MariaDB: `--set-gtid-purged`
     * und `--column-statistics` gibt es hier (anders als auf Live) und sie
     * muessen aus, sonst landen GTID- bzw. ANALYZE-Anweisungen im Dump.
     * `--events` bleibt weg: es gibt keine, und ohne EVENT-Recht bricht
     * mysqldump daran ab.
     */
    public function dumpCommand(string $database): string
    {
        $arguments = [
            $this->dumpBin,
            '--host='.($this->host === '' ? '127.0.0.1' : $this->host),
            '--port='.$this->port,
            '--user='.$this->username,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--routines',
            '--triggers',
            '--set-gtid-purged=OFF',
            '--column-statistics=0',
            '--default-character-set=utf8mb4',
            '--add-drop-database',
            '--databases',
            $this->quoteName($database),
        ];

        return implode(' ', array_map('escapeshellarg', $arguments));
    }

    public function dumpBin(): string
    {
        return $this->dumpBin;
    }

    /** @return array<string, string> */
    public function clientEnv(): array
    {
        return ['MYSQL_PWD' => $this->password];
    }

    /** Verbindung auf eine beliebige lokale Datenbank (Anonymisierung, Statistik). */
    public function connection(string $database): Connection
    {
        config([
            'database.connections.'.self::CONNECTION => array_merge(
                (array) config('database.connections.central'),
                ['database' => $database]
            ),
        ]);

        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    /**
     * Tabellenzahl und Groesse nach dem Import — die Zahlen der
     * Zusammenfassung.
     *
     * @return array{tables: int, bytes: int}
     */
    public function statistics(string $database): array
    {
        $row = $this->central()->selectOne(
            'select count(*) as tables_count, coalesce(sum(data_length + index_length), 0) as bytes '
            .'from information_schema.tables where table_schema = ? and table_type = ?',
            [$database, 'BASE TABLE']
        );

        return [
            'tables' => (int) ($row->tables_count ?? 0),
            'bytes' => (int) ($row->bytes ?? 0),
        ];
    }

    public function databaseExists(string $database): bool
    {
        return $this->central()->selectOne(
            'select 1 as found from information_schema.schemata where schema_name = ?',
            [$database]
        ) !== null;
    }

    /**
     * Nach `drop database` der ZENTRALEN Datenbank hat die offene Sitzung
     * keine Standarddatenbank mehr — ein spaeteres `select * from tenants`
     * laeuft dann in "No database selected". Deshalb nach dem Import der
     * Zentrale die Verbindung wegwerfen.
     */
    public function reconnectCentral(): void
    {
        DB::purge('central');
        DB::reconnect('central');
    }

    private function central(): Connection
    {
        return DB::connection('central');
    }

    /**
     * Datenbanknamen koennen in MySQL nicht gebunden werden. Erlaubt sind
     * hier nur die Namen, die von Live kommen — `sun` und
     * `tenant_<uuid>`, also auch Bindestriche. Alles andere ist ein Fehler,
     * kein zu escapender Wert.
     */
    private function quoteName(string $database): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $database) !== 1) {
            throw new RuntimeException("Unerwarteter Datenbankname: {$database}");
        }

        return $database;
    }
}
