<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\DTOs\TenantImport\TenantImportResult;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\CityStateResolver;
use App\Support\ForeignCompanyDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompanyImporter
{
    private ?int $lastCompanyId = null;

    public function __construct(
        private readonly CityResolver $cityResolver,
        private readonly CategoryMapper $categoryMapper,
        private readonly OpeningHoursImporter $openingHoursImporter,
        private readonly ReviewImporter $reviewImporter,
        private readonly PhotoImporter $photoImporter,
        private readonly CityStateResolver $cityStates,
    ) {}

    public function import(
        object $place,
        int $tenantId,
        string $tempConnection,
        array $options = [],
    ): TenantImportResult {
        $result = new TenantImportResult();
        $force = $options['force'] ?? false;
        $skipReviews = $options['skip_reviews'] ?? false;
        $skipPhotos = $options['skip_photos'] ?? false;

        try {
            DB::beginTransaction();

            // Filter: soft-deleted, rejected, Löschanfragen
            if ($this->shouldSkip($place)) {
                $result->companiesSkipped++;
                DB::commit();
                return $result;
            }

            // Ausländische Betriebe aus dem Scraper-Dump (US/CH/FR/AT …) gehören
            // nicht in ein deutsches Portal (#16). 'country' => null schaltet ab.
            if (($options['country'] ?? 'DE') === 'DE') {
                $foreignReason = ForeignCompanyDetector::reason($place->tel ?? $place->phone ?? null, $place->zipcode ?? null);

                if ($foreignReason !== null) {
                    $result->companiesSkipped++;
                    Log::info("TenantImport: Place #{$place->id} übersprungen — nicht in Deutschland ({$foreignReason}).");
                    DB::commit();
                    return $result;
                }
            }

            // Duplikaterkennung über ID und google_places_id
            $googlePlacesId = $place->g_places_id ?? $place->google_places_id ?? null;
            $existingCompany = Company::find($place->id);

            if (!$existingCompany && $googlePlacesId) {
                $existingCompany = Company::where('google_places_id', $googlePlacesId)->first();
            }

            if ($existingCompany && !$force) {
                $result->companiesSkipped++;
                Log::info("TenantImport: Place #{$place->id} übersprungen — Duplikat (google_places_id: {$googlePlacesId}).");
                DB::commit();
                return $result;
            }

            // City auflösen
            $cityId = null;
            // Dumps aus dem Python-Scraper tragen fehlende Orte als Text "None" (#4)
            if (! City::isPlaceholderName($place->city ?? null)) {
                // Bundesland nur, wenn es eines ist: US-/FR-Places mit gleicher PLZ
                // haben deutsche Orte sonst mit "North Carolina" angelegt (#18)
                $state = ($options['country'] ?? 'DE') === 'DE'
                    ? $this->cityStates->forGermanCity($place->administrative_area_level_1 ?? null, $place->zipcode ?? null)
                    : ($place->administrative_area_level_1 ?? null);

                $cityId = $this->cityResolver->resolve(
                    $place->city,
                    $place->zipcode ?? null,
                    $state,
                );
            }

            // Company-Daten vorbereiten
            $companyData = [
                'name' => $place->name,
                'slug' => $this->generateUniqueSlug($place->name),
                'description' => $place->description ?? null,
                'description_source' => $googlePlacesId ? 'google' : 'manual',
                'street' => $place->street ?? null,
                'house_no' => $place->houseno ?? $place->house_no ?? null,
                'zipcode' => $place->zipcode ?? null,
                'city_id' => $cityId,
                'tel' => $place->tel ?? $place->phone ?? null,
                'email' => $place->email ?? null,
                'website' => $place->website ?? null,
                'google_places_id' => $googlePlacesId,
                'is_active' => ($place->status ?? 'approved') === 'approved',
                'is_premium' => false,
                'is_verified' => false,
                'created_at' => $place->created_at ?? now(),
                'updated_at' => $place->updated_at ?? now(),
            ];

            if ($existingCompany && $force) {
                $existingCompany->update($companyData);
                $this->lastCompanyId = $existingCompany->id;
                $result->companiesImported++;
            } else {
                $company = new Company($companyData);
                $company->id = $place->id;
                $company->save();
                $this->lastCompanyId = $company->id;
                $result->companiesImported++;
            }

            $companyId = $this->lastCompanyId;
            $companyModel = $existingCompany ?? $company;

            // Kategorie-Mapping
            $result->categoriesMapped += $this->categoryMapper->mapPivot(
                $place->id,
                $companyId,
                $tempConnection,
            );

            // Öffnungszeiten
            $ohResult = $this->openingHoursImporter->import(
                $place->id,
                $companyId,
                $tempConnection,
            );
            $result->openingHoursImported += $ohResult;

            // Reviews
            if (!$skipReviews) {
                $reviewResult = $this->reviewImporter->import(
                    $place->id,
                    $companyId,
                    $tempConnection,
                );
                $result->reviewsImported += $reviewResult;
            }

            // Fotos
            if (!$skipPhotos) {
                $photoResult = $this->photoImporter->import(
                    $place->id,
                    $companyModel,
                    $tempConnection,
                );
                $result->photosImported += $photoResult;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $result->companiesFailed++;
            $result->errors[] = "Place #{$place->id} ({$place->name}): {$e->getMessage()}";
            Log::error("TenantImport: Fehler bei Place #{$place->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    public function getLastCompanyId(): ?int
    {
        return $this->lastCompanyId;
    }

    private function shouldSkip(object $place): bool
    {
        if (!empty($place->deleted_at)) {
            return true;
        }

        if (($place->status ?? '') === 'rejected') {
            return true;
        }

        if (!empty($place->deletion_requested_at)) {
            return true;
        }

        return false;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'company-' . time();
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
