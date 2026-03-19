<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantImport\SqlDumpProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupTempImportDatabases extends Command
{
    protected $signature = 'import:cleanup {--hours=24 : Stunden nach denen Temp-DBs gelöscht werden}';

    protected $description = 'Bereinigt verwaiste temporäre Import-Datenbanken (temp_import_*)';

    public function handle(): int
    {
        $maxAgeHours = (int) $this->option('hours');
        $maxAgeTimestamp = time() - ($maxAgeHours * 3600);

        $this->info("Suche nach temporären Import-Datenbanken älter als {$maxAgeHours} Stunden...");

        // Alle Datenbanken mit Prefix temp_import_ finden
        $databases = DB::select("SHOW DATABASES LIKE 'temp_import_%'");

        $deleted = 0;

        foreach ($databases as $db) {
            $dbName = current((array) $db);

            // Timestamp aus DB-Namen extrahieren: temp_import_{timestamp}
            if (!preg_match('/^temp_import_(\d+)$/', $dbName, $matches)) {
                $this->warn("  Überspringe \"{$dbName}\" — unerwartetes Format.");
                continue;
            }

            $dbTimestamp = (int) $matches[1];

            if ($dbTimestamp > $maxAgeTimestamp) {
                $this->line("  \"{$dbName}\" — noch aktiv (< {$maxAgeHours}h), übersprungen.");
                continue;
            }

            // Löschen
            SqlDumpProcessor::dropTemporaryDatabase($dbName);
            $deleted++;
            $this->info("  \"{$dbName}\" gelöscht.");
        }

        // Upload-Dateien bereinigen
        $uploadDir = storage_path('app/tenant-imports');
        $filesDeleted = 0;

        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '/*');
            foreach ($files as $file) {
                if (filemtime($file) < $maxAgeTimestamp) {
                    unlink($file);
                    $filesDeleted++;
                }
            }
        }

        $this->info("Ergebnis: {$deleted} Datenbank(en) gelöscht, {$filesDeleted} Datei(en) bereinigt.");
        Log::info("TenantImport Cleanup: {$deleted} DBs gelöscht, {$filesDeleted} Dateien bereinigt.");

        return self::SUCCESS;
    }
}
