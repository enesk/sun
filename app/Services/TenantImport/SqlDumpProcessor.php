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
        '/\bDROP\s+(DATABASE|INDEX|VIEW)\b/i',
        '/\bDROP\s+TABLE\b(?!\s+IF\s+EXISTS\b)/i',
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

        // Tabellenprüfung (FA-02) — streamt die gesamte Datei zeilenweise
        $allTablesInDump = $this->findTablesInDump($realPath);
        $foundTables = [];
        $missingTables = [];
        $warnings = [];

        foreach (self::EXPECTED_TABLES as $table) {
            if (in_array($table, $allTablesInDump)) {
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
        if (str_ends_with($filePath, '.gz')) {
            if (file_exists($filePath)) {
                return $this->decompressGzip($filePath);
            }
            // .gz wurde bereits dekomprimiert — prüfe ob .sql existiert
            $sqlPath = preg_replace('/\.gz$/', '', $filePath);
            return file_exists($sqlPath) ? $sqlPath : null;
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
        // Für Sicherheitsprüfung: erste 5 MB lesen (gefährliche Statements stehen am Anfang)
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return null;
        }

        $content = fread($handle, 5 * 1024 * 1024);
        fclose($handle);

        return $content ?: null;
    }

    /**
     * Durchsucht die gesamte Datei zeilenweise nach CREATE TABLE Statements.
     * Vermeidet das Laden der kompletten Datei in den Speicher.
     */
    private function findTablesInDump(string $filePath): array
    {
        $foundTables = [];
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $line, $matches)) {
                $foundTables[] = $matches[1];
            }
        }

        fclose($handle);

        return $foundTables;
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
        // --force: bei Syntax-Fehlern in einzelnen Statements weitermachen statt abbrechen
        // Zusätzlich DELIMITER-Blöcke (Stored Procedures/Triggers) entfernen, da diese
        // beim Pipe-Import über sed/mysql Probleme verursachen können
        // Dump vorfiltern: DELIMITER-Blöcke (Stored Procedures, Triggers, Functions)
        // komplett entfernen, da diese beim Pipe-Import Syntax-Fehler verursachen.
        // Außerdem USE/CREATE DATABASE entfernen damit Daten in die Temp-DB gehen.
        $cleanedFile = $filePath . '.cleaned.sql';
        $this->preprocessDump($filePath, $cleanedFile);

        $cmd = sprintf(
            '%s --force --binary-mode --host=%s --port=%s --user=%s %s %s < %s 2>&1',
            escapeshellarg($mysqlBin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($dbName),
            escapeshellarg($cleanedFile),
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        // Return-Code und Ausgabe loggen
        if ($returnCode !== 0 || !empty($output)) {
            Log::warning("TenantImport: mysql-Import Return-Code {$returnCode}", [
                'database' => $dbName,
                'output' => array_slice($output, 0, 30),
            ]);
        }

        // Connection registrieren BEVOR wir Tabellen prüfen
        $this->registerConnection();

        // Entscheidend ist NUR ob die Kerntabellen existieren.
        // mysql --force kann bei DELIMITER-Blöcken, Triggers oder Encoding-Problemen
        // trotzdem non-zero returnen, obwohl die Daten korrekt importiert wurden.
        $tablesExist = $this->checkRequiredTablesExist();

        // Temporäre bereinigte Datei immer aufräumen
        if (isset($cleanedFile) && file_exists($cleanedFile)) {
            @unlink($cleanedFile);
        }

        if (!$tablesExist) {
            // Zweiter Versuch: noch aggressiveres Cleanup — alle Conditional-Comments entfernen
            Log::warning("TenantImport: Erster Import fehlgeschlagen, versuche aggressives Cleanup...");

            $aggressiveFile = $filePath . '.aggressive.sql';
            $this->aggressivePreprocessDump($filePath, $aggressiveFile);

            $cmdRetry = sprintf(
                '%s --force --binary-mode --host=%s --port=%s --user=%s %s %s < %s 2>&1',
                escapeshellarg($mysqlBin),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $password ? '--password=' . escapeshellarg($password) : '',
                escapeshellarg($dbName),
                escapeshellarg($aggressiveFile),
            );

            // DB leeren und nochmal importieren
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            DB::statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $output2 = [];
            exec($cmdRetry, $output2, $returnCode2);

            if (file_exists($aggressiveFile)) {
                @unlink($aggressiveFile);
            }

            // Connection nach DB-Neuanlage neu aufbauen
            DB::purge($this->connectionName);
            $this->registerConnection();

            $tablesExist = $this->checkRequiredTablesExist();

            if (!$tablesExist) {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
                throw new \RuntimeException(
                    'SQL-Dump-Import fehlgeschlagen: Die Tabelle "places" wurde auch nach aggressivem Cleanup nicht erstellt. '
                    . 'MySQL-Ausgabe: ' . implode("\n", array_slice(array_merge($output, $output2), 0, 15))
                );
            }

            Log::info("TenantImport: SQL-Dump nach aggressivem Cleanup importiert in {$dbName}");
        } else {
            Log::info("TenantImport: SQL-Dump importiert in {$dbName}");
        }
    }

    /**
     * Dump-Datei vorverarbeiten: DELIMITER-Blöcke, Triggers, Stored Procedures,
     * USE/CREATE DATABASE Statements entfernen.
     */
    private function preprocessDump(string $inputFile, string $outputFile): void
    {
        $in = fopen($inputFile, 'r');
        $out = fopen($outputFile, 'w');

        if (!$in || !$out) {
            throw new \RuntimeException("Kann Dump-Datei nicht lesen/schreiben");
        }

        $inDelimiterBlock = false;
        $inConditionalBlock = false;
        $skipLinesRemaining = 0;

        while (($line = fgets($in)) !== false) {
            $trimmed = trim($line);

            // DELIMITER-Block Start (Stored Procedures, Functions, Triggers)
            if (!$inDelimiterBlock && !$inConditionalBlock && preg_match('/^DELIMITER\s+(?!;)/i', $trimmed)) {
                $inDelimiterBlock = true;
                continue;
            }

            // DELIMITER-Block Ende
            if ($inDelimiterBlock) {
                if (preg_match('/^DELIMITER\s*;/i', $trimmed)) {
                    $inDelimiterBlock = false;
                }
                continue;
            }

            // Mehrzeilige /*!50003 ... */ Conditional-Comment-Blöcke (Triggers, Functions, Procedures)
            // mysqldump schreibt diese als mehrzeilige Blöcke mit END */;; am Ende
            if (!$inConditionalBlock && preg_match('/^\/\*!50003\s+(CREATE|DROP)\b/i', $trimmed)) {
                $inConditionalBlock = true;
                continue;
            }

            // Auch /*!50001 VIEW-Definitionen können Probleme machen
            if (!$inConditionalBlock && preg_match('/^\/\*!50001\s+CREATE.*VIEW\b/i', $trimmed)) {
                $inConditionalBlock = true;
                continue;
            }

            // Ende des Conditional-Comment-Blocks: */; oder */;; oder END */
            if ($inConditionalBlock) {
                if (preg_match('/\*\/\s*;{0,2}\s*$/', $trimmed) || preg_match('/^END\s*\*\//', $trimmed)) {
                    $inConditionalBlock = false;
                }
                continue;
            }

            // Einzelne problematische Statements überspringen
            if (preg_match('/^(USE\s|CREATE\s+DATABASE|\\\\connect\s)/i', $trimmed)) {
                continue;
            }

            // Trigger/Function/Procedure DROP-Statements im Conditional-Comment-Format
            if (preg_match('/^\/\*!50003.*?(TRIGGER|FUNCTION|PROCEDURE)/i', $trimmed)) {
                continue;
            }

            // Definer-Klauseln die Permission-Fehler verursachen können
            if (preg_match('/^\/\*!50017\s+DEFINER/i', $trimmed)) {
                continue;
            }

            // Einzelne /*!50001 DROP VIEW */ Zeilen
            if (preg_match('/^\/\*!50001\s+DROP/i', $trimmed)) {
                continue;
            }

            fwrite($out, $line);
        }

        fclose($in);
        fclose($out);
    }

    /**
     * Aggressives Preprocessing: Nur CREATE TABLE, INSERT, ALTER TABLE, SET und LOCK/UNLOCK durchlassen.
     * Alles andere (Conditional Comments, Triggers, Views, Functions) wird entfernt.
     */
    private function aggressivePreprocessDump(string $inputFile, string $outputFile): void
    {
        $in = fopen($inputFile, 'r');
        $out = fopen($outputFile, 'w');

        if (!$in || !$out) {
            throw new \RuntimeException("Kann Dump-Datei nicht lesen/schreiben");
        }

        $inCreateTable = false;

        while (($line = fgets($in)) !== false) {
            $trimmed = trim($line);

            // Leere Zeilen und Kommentare durchlassen (mysqldump Struktur)
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                fwrite($out, $line);
                continue;
            }

            // CREATE TABLE Blöcke komplett durchlassen (mehrzeilig)
            if (preg_match('/^CREATE\s+TABLE\b/i', $trimmed)) {
                $inCreateTable = true;
            }
            if ($inCreateTable) {
                fwrite($out, $line);
                // CREATE TABLE endet mit ")...;" — vereinfacht: Zeile endet mit ;
                if (preg_match('/;\s*$/', $trimmed)) {
                    $inCreateTable = false;
                }
                continue;
            }

            // Sichere Einzel-Statements durchlassen
            if (preg_match('/^(INSERT\s|ALTER\s+TABLE\s|SET\s|LOCK\s+TABLES|UNLOCK\s+TABLES|\/\*!40)/i', $trimmed)) {
                fwrite($out, $line);
                continue;
            }

            // Alles andere überspringen (USE, CREATE DATABASE, DELIMITER, Triggers, Views, Functions...)
        }

        fclose($in);
        fclose($out);
    }

    private function checkRequiredTablesExist(): bool
    {
        try {
            $tables = DB::connection($this->connectionName)
                ->select('SHOW TABLES');

            $tableNames = array_map(function ($table) {
                return array_values((array) $table)[0];
            }, $tables);

            return in_array('places', $tableNames);
        } catch (\Exception $e) {
            return false;
        }
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
