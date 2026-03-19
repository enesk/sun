<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\DTOs\TenantImport\DumpValidationResult;
use App\DTOs\TenantImport\TempDatabaseInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlDumpProcessor
{
    private const EXPECTED_TABLES = [
        'places',
        'place_categories',
        'place_category',
        'place_opening_hours',
        'place_photos',
        'place_reviews',
    ];

    private const DANGEROUS_PATTERNS = [
        '/\bDROP\s+(TABLE|DATABASE|INDEX|VIEW)\b/i',
        '/\bGRANT\b/i',
        '/\bCREATE\s+USER\b/i',
        '/\bALTER\s+USER\b/i',
        '/\bDELETE\s+FROM\b/i',
        '/\bUPDATE\s+\w+\s+SET\b/i',
        '/\bTRUNCATE\b/i',
        '/\bLOAD\s+DATA\b/i',
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
    ];

    private ?string $databaseName = null;
    private ?string $connectionName = null;
    private ?string $filePath = null;

    /**
     * Stellt sicher, dass die temp_import Connection registriert ist.
     * Kann von überall aufgerufen werden — idempotent.
     */
    public static function ensureConnection(string $databaseName, string $connectionName = 'temp_import'): void
    {
        // Prüfe ob Connection schon konfiguriert UND auf die richtige DB zeigt
        $existing = config("database.connections.{$connectionName}");
        if ($existing && ($existing['database'] ?? null) === $databaseName) {
            return;
        }

        $centralConfig = config('database.connections.central');
        config(["database.connections.{$connectionName}" => array_merge($centralConfig, [
            'database' => $databaseName,
            'strict' => false,
        ])]);

        // Alte PDO-Instanz verwerfen falls vorhanden
        DB::purge($connectionName);
    }

    /**
     * Validiert die SQL-Dump-Datei auf Struktur und Sicherheit.
     */
    public function validate(string $filePath): DumpValidationResult
    {
        $realPath = $this->getReadablePath($filePath);
        if (! $realPath) {
            return DumpValidationResult::failed(['SQL-Datei nicht gefunden oder nicht lesbar.']);
        }

        $content = $this->readDumpContent($realPath);
        if ($content === null) {
            return DumpValidationResult::failed(['SQL-Datei konnte nicht gelesen werden.']);
        }

        // Sicherheitsprüfung (NFA-06)
        $securityErrors = $this->checkSecurity($content);
        if (! empty($securityErrors)) {
            return DumpValidationResult::failed($securityErrors);
        }

        // Tabellenprüfung (FA-02)
        $foundTables = [];
        $missingTables = [];
        $warnings = [];

        foreach (self::EXPECTED_TABLES as $table) {
            if (preg_match('/CREATE\s+TABLE\s+(?:`?' . preg_quote($table, '/') . '`?)\s/i', $content)) {
                $foundTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }

        if (in_array('places', $missingTables)) {
            return DumpValidationResult::failed(['Die Tabelle "places" fehlt im Dump. Diese ist zwingend erforderlich.']);
        }

        foreach ($missingTables as $table) {
            $warnings[] = "Tabelle \"{$table}\" nicht im Dump gefunden — wird übersprungen.";
        }

        return DumpValidationResult::ok($foundTables, $missingTables, $warnings);
    }

    /**
     * Verarbeitet den SQL-Dump: erstellt temporäre DB, importiert Daten, registriert Connection.
     */
    public function process(string $filePath): TempDatabaseInfo
    {
        $realPath = $this->getReadablePath($filePath);
        if (! $realPath) {
            throw new \RuntimeException('SQL-Datei nicht gefunden: ' . $filePath);
        }

        $this->filePath = $filePath;
        $this->databaseName = 'temp_import_' . time();
        $this->connectionName = 'temp_import';

        // 1. Temporäre Datenbank erstellen
        $this->createTemporaryDatabase();

        // 2. SQL-Dump importieren (via mysql CLI für Performance, NFA-02)
        $this->importDump($realPath);

        // 3. Dynamische Laravel-Connection registrieren (read-only, NFA-07)
        $this->registerConnection();

        // 4. Verifizieren, dass die Temp-DB tatsächlich Tabellen enthält
        $tables = DB::connection($this->connectionName)->select('SHOW TABLES');
        if (empty($tables)) {
            $this->cleanup();
            throw new \RuntimeException(
                'SQL-Dump-Import fehlgeschlagen: Die temporäre Datenbank enthält keine Tabellen. '
                . 'Möglicherweise ist das Dump-Format inkompatibel.'
            );
        }

        Log::info("TenantImport: Temp-DB enthält " . count($tables) . " Tabellen");

        return new TempDatabaseInfo(
            databaseName: $this->databaseName,
            connectionName: $this->connectionName,
            uploadPath: $this->filePath,
            createdAt: time(),
        );
    }

    /**
     * Liest verfügbare Tenant-IDs aus der temporären DB (FA-04).
     */
    public function getAvailableTenantIds(): array
    {
        if (! $this->connectionName) {
            throw new \RuntimeException('Keine temporäre Datenbank aktiv.');
        }

        try {
            return DB::connection($this->connectionName)
                ->table('places')
                ->select('tenant_id')
                ->distinct()
                ->whereNotNull('tenant_id')
                ->pluck('tenant_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::warning('TenantImport: Konnte tenant_id nicht auslesen — Spalte existiert möglicherweise nicht.', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Bereinigt nur die temporäre Datenbank (ohne Upload-Datei).
     * Für Vorschau-Cleanup, wenn die Datei für den Import noch benötigt wird.
     */
    public function cleanupDatabase(): void
    {
        if ($this->databaseName) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$this->databaseName}`");
                Log::info("TenantImport: Temporäre DB gelöscht: {$this->databaseName}");
            } catch (\Exception $e) {
                Log::error("TenantImport: Fehler beim Löschen der Temp-DB: {$e->getMessage()}");
            }
        }

        // Connection aus Config entfernen
        if ($this->connectionName) {
            app('config')->set("database.connections.{$this->connectionName}", null);
            DB::purge($this->connectionName);
        }

        $this->databaseName = null;
        $this->connectionName = null;
    }

    /**
     * Bereinigt temporäre Datenbank und Upload-Datei (FA-31).
     */
    public function cleanup(): void
    {
        $this->cleanupDatabase();

        if ($this->filePath && file_exists($this->filePath)) {
            @unlink($this->filePath);
            Log::info("TenantImport: Upload-Datei gelöscht: {$this->filePath}");
        }

        $this->filePath = null;
    }

    /**
     * Statisches Cleanup einer temporären Datenbank (für Scheduled Task).
     */
    public static function dropTemporaryDatabase(string $databaseName): void
    {
        DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
        Log::info("TenantImport Cleanup: DB gelöscht: {$databaseName}");
    }

    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    // ── Private Methods ──

    private function getReadablePath(string $filePath): ?string
    {
        // .sql.gz entpacken
        if (str_ends_with($filePath, '.gz') && file_exists($filePath)) {
            return $this->decompressGzip($filePath);
        }

        return file_exists($filePath) ? $filePath : null;
    }

    private function decompressGzip(string $gzPath): ?string
    {
        $sqlPath = preg_replace('/\.gz$/', '', $gzPath);

        $gz = gzopen($gzPath, 'rb');
        if (! $gz) {
            return null;
        }

        $out = fopen($sqlPath, 'wb');
        if (! $out) {
            gzclose($gz);
            return null;
        }

        while (! gzeof($gz)) {
            fwrite($out, gzread($gz, 1024 * 512));
        }

        gzclose($gz);
        fclose($out);

        // Original .gz löschen, .sql behalten
        @unlink($gzPath);
        $this->filePath = $sqlPath;

        return $sqlPath;
    }

    private function readDumpContent(string $filePath): ?string
    {
        // Für Validierung: nur die ersten 5 MB lesen (Struktur + Sicherheit)
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return null;
        }

        $content = fread($handle, 5 * 1024 * 1024);
        fclose($handle);

        return $content ?: null;
    }

    private function checkSecurity(string $content): array
    {
        $errors = [];

        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Sicherheitsverletzung: Gefährliches SQL-Statement erkannt ({$pattern}). Import abgelehnt.";
            }
        }

        return $errors;
    }

    private function createTemporaryDatabase(): void
    {
        $dbName = $this->databaseName;

        DB::statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        Log::info("TenantImport: Temporäre DB erstellt: {$dbName}");
    }

    private function importDump(string $filePath): void
    {
        $dbName = $this->databaseName;
        $host = config('database.connections.central.host', '127.0.0.1');
        $port = config('database.connections.central.port', '3306');
        $username = config('database.connections.central.username', 'root');
        $password = config('database.connections.central.password', '');

        // mysql CLI-Import für Performance (NFA-02: max 60s für 256MB)
        $mysqlBin = config('database.mysql_binary_path') ?: $this->findMysqlBinary();

        // USE und CREATE DATABASE Statements aus dem Dump entfernen,
        // damit die Daten in die Temp-DB importiert werden (nicht in die Original-DB)
        $cmd = sprintf(
            'sed -e %s -e %s -e %s %s | %s --host=%s --port=%s --user=%s %s %s 2>&1',
            escapeshellarg('/^USE /d'),
            escapeshellarg('/^CREATE DATABASE/d'),
            escapeshellarg('/^\\\\connect /d'),
            escapeshellarg($filePath),
            escapeshellarg($mysqlBin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($dbName),
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            // Cleanup bei Fehler
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            throw new \RuntimeException(
                'SQL-Dump-Import fehlgeschlagen: ' . implode("\n", $output)
            );
        }

        Log::info("TenantImport: SQL-Dump importiert in {$dbName}");
    }

    private function findMysqlBinary(): string
    {
        $paths = [
            '/opt/homebrew/opt/mysql/bin/mysql',
            '/opt/homebrew/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/usr/bin/mysql',
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Letzter Versuch: which
        $which = trim((string) shell_exec('which mysql 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        throw new \RuntimeException(
            'mysql-Binary nicht gefunden. Bitte MYSQL_BINARY_PATH in .env setzen oder mysql im PATH verfügbar machen.'
        );
    }

    private function registerConnection(): void
    {
        $centralConfig = config('database.connections.central');

        config(["database.connections.{$this->connectionName}" => array_merge($centralConfig, [
            'database' => $this->databaseName,
            'strict' => false, // Legacy-Daten können inkompatibel mit strict mode sein
        ])]);

        // Connection testen
        DB::connection($this->connectionName)->getPdo();
    }
}
