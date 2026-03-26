<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantImport\SqlDumpProcessor;
use App\Services\TenantImport\TenantImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessTenantImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 Minuten
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $filePath,
        public readonly int $sourceTenantId,
        public readonly array $options = [],
    ) {
        $this->onConnection('database');
    }

    public function handle(TenantImportService $importService, SqlDumpProcessor $processor): void
    {
        $cacheKey = $this->getCacheKey();
        $startTime = microtime(true);

        try {
            // ── Schritt 1: Validierung ──
            $this->pushLog($cacheKey, 'step', 'Schritt 1/4: SQL-Dump validieren');
            $this->pushLog($cacheKey, 'detail', 'Prüfe Dump-Struktur auf erwartete Tabellen...');
            $this->pushLog($cacheKey, 'detail', 'Prüfe auf gefährliche SQL-Statements (DROP, GRANT, DELETE)...');
            $this->updateProgress($cacheKey, 2, 'Validiere SQL-Dump...', 'running');

            $validation = $processor->validate($this->filePath);

            if (! $validation->valid) {
                $errorMsg = 'Validierung fehlgeschlagen: ' . implode(', ', $validation->errors);
                $this->pushLog($cacheKey, 'error', $errorMsg);
                $this->updateProgress($cacheKey, 0, $errorMsg, 'failed');
                return;
            }

            foreach ($validation->foundTables as $table) {
                $this->pushLog($cacheKey, 'detail', "Tabelle gefunden: {$table}");
            }
            $this->pushLog($cacheKey, 'ok', 'Gefundene Tabellen: ' . implode(', ', $validation->foundTables));
            $this->pushLog($cacheKey, 'ok', 'Sicherheitsprüfung bestanden — keine gefährlichen Statements');

            if (! empty($validation->warnings)) {
                foreach ($validation->warnings as $warning) {
                    $this->pushLog($cacheKey, 'warn', $warning);
                }
            }

            // ── Schritt 2: Temp-DB erstellen ──
            $this->pushLog($cacheKey, 'step', 'Schritt 2/4: SQL-Dump in Temp-DB importieren');
            $this->pushLog($cacheKey, 'detail', 'Erstelle temporäre Datenbank...');
            $this->updateProgress($cacheKey, 8, 'Erstelle Temp-DB...', 'running');

            $tempDbInfo = $processor->process($this->filePath);

            $this->pushLog($cacheKey, 'detail', "DB-Name: {$tempDbInfo->databaseName}");
            $this->pushLog($cacheKey, 'detail', "Connection: {$tempDbInfo->connectionName}");
            $this->pushLog($cacheKey, 'ok', "Temp-DB erstellt: {$tempDbInfo->databaseName}");

            // ── Schritt 3: Vorschau (Dry-Run) ──
            $this->pushLog($cacheKey, 'step', 'Schritt 3/4: Vorschau (Dry-Run)');
            $this->pushLog($cacheKey, 'detail', 'Initialisiere Tenancy...');
            $this->updateProgress($cacheKey, 12, 'Dry-Run / Vorschau...', 'running');

            tenancy()->initialize($this->tenant);
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);

            $this->pushLog($cacheKey, 'detail', 'Zähle importierbare Datensätze...');
            $this->pushLog($cacheKey, 'detail', 'Prüfe Duplikate gegen bestehende Daten...');
            $this->pushLog($cacheKey, 'detail', 'Analysiere Kategorie-Mappings...');

            $preview = $importService->dryRun($this->tenant, $tempDbInfo->connectionName, $this->sourceTenantId);

            tenancy()->end();
            $this->pushLog($cacheKey, 'detail', 'Tenancy beendet');

            $this->pushLog($cacheKey, 'preview', json_encode([
                'Firmen (importierbar)' => $preview->placesCount,
                'Kategorien' => $preview->categoriesCount,
                'Bewertungen' => $preview->reviewsCount,
                'Fotos' => $preview->photosCount,
                'Öffnungszeiten' => $preview->openingHoursCount,
                'Duplikate' => $preview->duplicatesCount,
                'Erwartete neue Einträge' => $preview->expectedNewCompanies,
            ], JSON_UNESCAPED_UNICODE));

            if (! empty($preview->missingCategories)) {
                $this->pushLog($cacheKey, 'warn', 'Fehlende Kategorie-Mappings: ' . implode(', ', $preview->missingCategories));
            }
            $this->pushLog($cacheKey, 'ok', 'Dry-Run abgeschlossen');

            // ── Schritt 4: Import ──
            $this->pushLog($cacheKey, 'step', 'Schritt 4/4: Import durchführen');
            $this->updateProgress($cacheKey, 15, 'Starte Datenimport...', 'running');

            $force = $this->options['force'] ?? false;
            $skipReviews = $this->options['skip_reviews'] ?? false;
            $skipPhotos = $this->options['skip_photos'] ?? false;

            $this->pushLog($cacheKey, 'detail', 'Optionen:');
            $this->pushLog($cacheKey, 'detail', '  Force-Modus: ' . ($force ? 'JA — Duplikate werden überschrieben' : 'NEIN — Duplikate werden übersprungen'));
            $this->pushLog($cacheKey, 'detail', '  Reviews: ' . ($skipReviews ? 'ÜBERSPRUNGEN' : 'werden importiert'));
            $this->pushLog($cacheKey, 'detail', '  Fotos: ' . ($skipPhotos ? 'ÜBERSPRUNGEN' : 'werden importiert'));

            $this->pushLog($cacheKey, 'detail', 'Initialisiere Tenancy für Import...');
            tenancy()->initialize($this->tenant);
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);
            $this->pushLog($cacheKey, 'detail', 'Tenancy initialisiert — starte Datenimport...');

            $lastLoggedEntity = '';

            $result = $importService->import(
                $this->tenant,
                $tempDbInfo->connectionName,
                $this->sourceTenantId,
                $this->options,
                function (int $processed, int $total, int $percent, string $currentEntity) use ($cacheKey, &$lastLoggedEntity) {
                    // Fortschritt skalieren: 15-95%
                    $scaledPercent = 15 + (int) round($percent * 0.8);

                    // Jede neue Company loggen
                    if ($currentEntity !== $lastLoggedEntity) {
                        $this->pushLog($cacheKey, 'detail', "[{$processed}/{$total}] Importiere: {$currentEntity}");
                        $lastLoggedEntity = $currentEntity;
                    }

                    $this->updateProgress(
                        $cacheKey,
                        $scaledPercent,
                        "Importiere: {$currentEntity} ({$processed}/{$total})",
                        'running',
                    );
                },
            );

            // ── Cleanup ──
            $this->pushLog($cacheKey, 'detail', 'Beende Tenancy...');
            tenancy()->end();
            $this->pushLog($cacheKey, 'detail', 'Räume Temp-DB und Dump-Datei auf...');
            $processor->cleanup();
            $this->pushLog($cacheKey, 'ok', 'Temp-DB und Dump-Datei aufgeräumt');

            // ── Ergebnis ──
            $elapsed = round(microtime(true) - $startTime, 1);

            $this->pushLog($cacheKey, 'result', json_encode([
                'Firmen importiert' => $result->companiesImported,
                'Firmen übersprungen' => $result->companiesSkipped,
                'Firmen fehlgeschlagen' => $result->companiesFailed,
                'Kategorien gemappt' => $result->categoriesMapped,
                'Öffnungszeiten' => $result->openingHoursImported,
                'Bewertungen' => $result->reviewsImported,
                'Fotos' => $result->photosImported,
                'Dauer' => "{$elapsed}s",
            ], JSON_UNESCAPED_UNICODE));

            if (! empty($result->errors)) {
                $this->pushLog($cacheKey, 'warn', 'Fehler (' . count($result->errors) . '):');
                foreach (array_slice($result->errors, 0, 20) as $error) {
                    $this->pushLog($cacheKey, 'warn', "  {$error}");
                }
                if (count($result->errors) > 20) {
                    $this->pushLog($cacheKey, 'warn', '  ... und ' . (count($result->errors) - 20) . ' weitere.');
                }
            }

            $this->pushLog($cacheKey, 'ok', "Import abgeschlossen in {$elapsed}s");

            $this->updateProgress($cacheKey, 100, 'Import abgeschlossen', 'completed', $result->toArray());

            Log::info("TenantImport Job: Abgeschlossen für Tenant \"{$this->tenant->name}\"", $result->toArray());
        } catch (\Exception $e) {
            $this->pushLog($cacheKey, 'error', "FEHLER: {$e->getMessage()}");
            $this->updateProgress($cacheKey, 0, "Fehler: {$e->getMessage()}", 'failed');

            Log::error('TenantImport Job: Fehler', [
                'tenant' => $this->tenant->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            tenancy()->end();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $cacheKey = $this->getCacheKey();
        $this->pushLog($cacheKey, 'error', "Import endgültig fehlgeschlagen: {$exception->getMessage()}");
        $this->updateProgress($cacheKey, 0, "Import fehlgeschlagen: {$exception->getMessage()}", 'failed');

        Log::error('TenantImport Job: Endgültig fehlgeschlagen', [
            'tenant' => $this->tenant->name,
            'error' => $exception->getMessage(),
        ]);
    }

    public function getCacheKey(): string
    {
        return "tenant_import_progress_{$this->tenant->id}";
    }

    public static function getLogKey(string $tenantId): string
    {
        return "tenant_import_log_{$tenantId}";
    }

    private function updateProgress(string $cacheKey, int $percent, string $message, string $status, array $result = []): void
    {
        Cache::put($cacheKey, [
            'percent' => $percent,
            'message' => $message,
            'status' => $status,
            'result' => $result,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));
    }

    /**
     * Pusht eine Log-Zeile in den Cache-basierten Log-Stream.
     * Der Watch-Command liest diesen Stream und zeigt ihn live an.
     */
    private function pushLog(string $cacheKey, string $level, string $message): void
    {
        $logKey = self::getLogKey($this->tenant->id);

        $logs = Cache::get($logKey, []);
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'level' => $level,
            'message' => $message,
        ];

        // Max 500 Einträge behalten (älteste raus)
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }

        Cache::put($logKey, $logs, now()->addHours(24));
    }
}
