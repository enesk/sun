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

/**
 * Baut die Import-Vorschau (Temp-DB + Dry-Run) im Hintergrund.
 *
 * Lief vorher synchron in TenantImport::loadPreview() und riss bei grossen
 * Dumps die 100-s-Grenze der Cloudflare-Kante (524). Fortschritt und Ergebnis
 * laufen wie beim Import ueber den Cache, die Oberflaeche pollt.
 */
class ProcessTenantPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 Minuten

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $filePath,
        public readonly int $sourceTenantId,
    ) {
        $this->onConnection('database');
    }

    public static function getCacheKey(string $tenantId): string
    {
        return "tenant_import_preview_{$tenantId}";
    }

    public function handle(TenantImportService $importService, SqlDumpProcessor $processor): void
    {
        $cacheKey = self::getCacheKey($this->tenant->id);

        try {
            $this->putState($cacheKey, 'running', 'Erstelle Temp-DB und spiele Dump ein...');

            $tempDbInfo = $processor->process($this->filePath);

            $this->putState($cacheKey, 'running', 'Analysiere Dump (Dry-Run)...');

            tenancy()->initialize($this->tenant);
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);

            $preview = $importService->dryRun($this->tenant, $tempDbInfo->connectionName, $this->sourceTenantId);

            tenancy()->end();

            // Nur Temp-DB aufraeumen, Upload-Datei bleibt fuer den Import erhalten
            $processor->cleanupDatabase();

            $this->putState($cacheKey, 'completed', 'Vorschau fertig', [
                'placesCount' => $preview->placesCount,
                'categoriesCount' => $preview->categoriesCount,
                'reviewsCount' => $preview->reviewsCount,
                'photosCount' => $preview->photosCount,
                'openingHoursCount' => $preview->openingHoursCount,
                'duplicatesCount' => $preview->duplicatesCount,
                'expectedNewCompanies' => $preview->expectedNewCompanies,
                'missingCategories' => array_values($preview->missingCategories),
                'availableTenantIds' => $preview->availableTenantIds,
            ]);
        } catch (\Throwable $e) {
            $this->putState($cacheKey, 'failed', "Fehler bei der Vorschau: {$e->getMessage()}");

            Log::error('TenantPreview Job: Fehler', [
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
        $this->putState(
            self::getCacheKey($this->tenant->id),
            'failed',
            "Vorschau fehlgeschlagen: {$exception->getMessage()}",
        );
    }

    private function putState(string $cacheKey, string $status, string $message, array $preview = []): void
    {
        Cache::put($cacheKey, [
            'status' => $status,
            'message' => $message,
            'preview' => $preview,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));
    }
}
