<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\DTOs\TenantImport\TenantImportPreview;
use App\DTOs\TenantImport\TenantImportResult;
use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantImportService
{
    public function __construct(
        private readonly CompanyImporter $companyImporter,
        private readonly CategoryMapper $categoryMapper,
        private readonly CityResolver $cityResolver,
    ) {}

    /**
     * Führt den vollständigen Import aus der Temp-DB in den Ziel-Tenant durch.
     */
    public function import(
        Tenant $tenant,
        string $tempConnection,
        int $sourceTenantId,
        array $options = [],
        ?callable $progressCallback = null,
    ): TenantImportResult {
        $result = new TenantImportResult();
        $logFile = 'tenant-import-' . $tenant->id . '-' . date('Y-m-d-His') . '.log';

        Log::info("TenantImport: Start Import in Tenant \"{$tenant->name}\" (source_tenant_id: {$sourceTenantId})");

        // Category-Mapping vorab laden
        $this->categoryMapper->initialize();
        $this->cityResolver->warmCache();

        // Places chunk-basiert laden (500er Chunks)
        $query = DB::connection($tempConnection)->table('places');

        if ($sourceTenantId > 0) {
            $query->where('tenant_id', $sourceTenantId);
        }

        $totalPlaces = (clone $query)->count();
        $processed = 0;
        $chunkSize = 500;

        $query->orderBy('id')->chunk($chunkSize, function ($places) use (
            $tenant, $tempConnection, $options, $result, &$processed, $totalPlaces, $progressCallback,
        ) {
            foreach ($places as $place) {
                $placeResult = $this->companyImporter->import(
                    $place,
                    (int) $tenant->id,
                    $tempConnection,
                    $options,
                );

                // Ergebnis akkumulieren
                $result->companiesImported += $placeResult->companiesImported;
                $result->companiesSkipped += $placeResult->companiesSkipped;
                $result->companiesFailed += $placeResult->companiesFailed;
                $result->categoriesMapped += $placeResult->categoriesMapped;
                $result->openingHoursImported += $placeResult->openingHoursImported;
                $result->reviewsImported += $placeResult->reviewsImported;
                $result->photosImported += $placeResult->photosImported;
                $result->errors = array_merge($result->errors, $placeResult->errors);

                $processed++;

                if ($progressCallback) {
                    $percent = $totalPlaces > 0 ? (int) round(($processed / $totalPlaces) * 100) : 0;
                    $progressCallback($processed, $totalPlaces, $percent, $place->name ?? '');
                }
            }
        });

        Log::info("TenantImport: Import abgeschlossen", $result->toArray());

        return $result;
    }

    /**
     * Dry-Run: Vorschau ohne Schreibzugriff.
     */
    public function dryRun(
        Tenant $tenant,
        string $tempConnection,
        int $sourceTenantId,
    ): TenantImportPreview {
        $this->categoryMapper->initialize();

        $query = DB::connection($tempConnection)->table('places');
        if ($sourceTenantId > 0) {
            $query->where('tenant_id', $sourceTenantId);
        }

        // Gesamtanzahl Places (ohne Filter)
        $totalPlaces = (clone $query)->count();

        // Gefilterte Places zählen (soft-deleted, rejected, Löschanfragen)
        $filteredQuery = clone $query;
        $filteredQuery->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'rejected');
            })
            ->whereNull('deletion_requested_at');
        $importablePlaces = $filteredQuery->count();

        // Duplikate erkennen
        // Beide Feldnamen unterstützen (g_places_id und google_places_id)
        $columns = DB::connection($tempConnection)->getSchemaBuilder()->getColumnListing('places');
        $gpIdColumn = in_array('google_places_id', $columns) ? 'google_places_id' : 'g_places_id';

        $googleIds = (clone $filteredQuery)
            ->whereNotNull($gpIdColumn)
            ->where($gpIdColumn, '!=', '')
            ->pluck($gpIdColumn)
            ->toArray();

        $duplicatesCount = 0;
        if (!empty($googleIds)) {
            // DB-Name merken — $tenant->run() kann die dynamische Connection zurücksetzen
            $tempDbName = config("database.connections.{$tempConnection}.database");

            // Tenant-Context aktivieren für Company-Query
            $tenant->run(function () use ($googleIds, &$duplicatesCount) {
                $duplicatesCount = Company::whereIn('google_places_id', $googleIds)->count();
            });

            // Connection nach $tenant->run() wiederherstellen
            if ($tempDbName) {
                SqlDumpProcessor::ensureConnection($tempDbName, $tempConnection);
            }
        }

        // Kategorien zählen
        $categoriesCount = DB::connection($tempConnection)->table('place_categories')->count();

        // Reviews zählen
        $reviewsCount = 0;
        try {
            $reviewsCount = DB::connection($tempConnection)->table('place_reviews')->count();
        } catch (\Exception $e) {
            // Tabelle existiert möglicherweise nicht
        }

        // Fotos zählen
        $photosCount = 0;
        try {
            $photosCount = DB::connection($tempConnection)->table('place_photos')->count();
        } catch (\Exception $e) {
            // Tabelle existiert möglicherweise nicht
        }

        // Öffnungszeiten zählen
        $openingHoursCount = 0;
        try {
            $openingHoursCount = DB::connection($tempConnection)->table('place_opening_hours')->count();
        } catch (\Exception $e) {
            // Tabelle existiert möglicherweise nicht
        }

        // Fehlende Kategorien ermitteln
        $missingCategories = [];
        $oldCategories = DB::connection($tempConnection)->table('place_categories')->get(['name', 'slug']);
        $ignoredCategories = config('category-mapping.ignored', []);

        foreach ($oldCategories as $oldCat) {
            if (in_array($oldCat->slug, $ignoredCategories)) {
                continue;
            }
            $mapped = $this->categoryMapper->mapCategory($oldCat->name, $oldCat->slug);
            if ($mapped === null) {
                $missingCategories[] = $oldCat->name;
            }
        }

        // Tenant-IDs
        $tenantIds = [];
        try {
            $tenantIds = DB::connection($tempConnection)
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
            // tenant_id-Spalte existiert möglicherweise nicht
        }

        return new TenantImportPreview(
            placesCount: $importablePlaces,
            categoriesCount: $categoriesCount,
            reviewsCount: $reviewsCount,
            photosCount: $photosCount,
            openingHoursCount: $openingHoursCount,
            duplicatesCount: $duplicatesCount,
            expectedNewCompanies: $importablePlaces - $duplicatesCount,
            missingCategories: array_unique($missingCategories),
            availableTenantIds: $tenantIds,
        );
    }
}
