<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\TenantImport\TenantImportResult;
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
        // Immer über database-Queue ausführen, nie synchron.
        $this->onConnection('database');
    }

    public function handle(TenantImportService $importService, SqlDumpProcessor $processor): void
    {
        $cacheKey = $this->getCacheKey();

        try {
            $this->updateProgress($cacheKey, 0, 'Starte Import...', 'running');

            // SQL-Dump ZUERST verarbeiten (vor tenancy init, damit Connection registriert wird)
            $tempDbInfo = $processor->process($this->filePath);

            // Tenant-Context aktivieren
            tenancy()->initialize($this->tenant);

            // Connection nach Tenancy-Init erneut sicherstellen
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);

            $this->updateProgress($cacheKey, 5, 'SQL-Dump importiert, starte Datenmigration...', 'running');

            // Import durchführen
            $result = $importService->import(
                $this->tenant,
                $tempDbInfo->connectionName,
                $this->sourceTenantId,
                $this->options,
                function (int $processed, int $total, int $percent, string $currentEntity) use ($cacheKey) {
                    // Fortschritt skalieren: 5-95%
                    $scaledPercent = 5 + (int) round($percent * 0.9);
                    $this->updateProgress(
                        $cacheKey,
                        $scaledPercent,
                        "Verarbeite: {$currentEntity} ({$processed}/{$total})",
                        'running',
                    );
                },
            );

            // Cleanup bei Erfolg
            $processor->cleanup();

            $this->updateProgress($cacheKey, 100, 'Import abgeschlossen', 'completed', $result->toArray());

            Log::info("TenantImport Job: Abgeschlossen für Tenant \"{$this->tenant->name}\"", $result->toArray());
        } catch (\Exception $e) {
            // Bei Fehler: Temp-DB 24h behalten
            $this->updateProgress($cacheKey, 0, "Fehler: {$e->getMessage()}", 'failed');

            Log::error("TenantImport Job: Fehler", [
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
        $this->updateProgress($cacheKey, 0, "Import fehlgeschlagen: {$exception->getMessage()}", 'failed');

        Log::error("TenantImport Job: Endgültig fehlgeschlagen", [
            'tenant' => $this->tenant->name,
            'error' => $exception->getMessage(),
        ]);
    }

    public function getCacheKey(): string
    {
        return "tenant_import_progress_{$this->tenant->id}";
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
}
