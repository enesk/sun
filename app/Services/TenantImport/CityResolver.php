<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\Models\Portal\City;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CityResolver
{
    /** @var array<string, int> Cache: normalized key → city_id */
    private array $cache = [];

    /**
     * Löst eine Freitext-Stadt in eine city_id auf. Erstellt neue City-Einträge bei Bedarf.
     */
    public function resolve(string $city, ?string $zipcode = null, ?string $administrativeAreaLevel1 = null): int
    {
        $normalizedName = $this->normalize($city);
        if (empty($normalizedName)) {
            throw new \InvalidArgumentException('Stadtname darf nicht leer sein.');
        }

        $normalizedState = $administrativeAreaLevel1 ? $this->normalize($administrativeAreaLevel1) : null;

        // Cache-Lookup
        $cacheKey = $this->buildCacheKey($normalizedName, $normalizedState, $zipcode);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // 1. Name + Bundesland (case-insensitive)
        if ($normalizedState) {
            $found = City::whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
                ->whereRaw('LOWER(administrative_area_level_1) = ?', [mb_strtolower($normalizedState)])
                ->first();

            if ($found) {
                return $this->cacheResult($cacheKey, $found->id);
            }
        }

        // 2. Name + PLZ
        if ($zipcode) {
            $found = City::whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
                ->where('zipcode', $zipcode)
                ->first();

            if ($found) {
                return $this->cacheResult($cacheKey, $found->id);
            }
        }

        // 3. Nur Name (erste gewinnt)
        $found = City::whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])->first();
        if ($found) {
            return $this->cacheResult($cacheKey, $found->id);
        }

        // 4. Nicht gefunden → neuen Eintrag erstellen
        $slug = $this->generateUniqueSlug($normalizedName);

        $newCity = City::create([
            'name' => $normalizedName,
            'zipcode' => $zipcode,
            'administrative_area_level_1' => $administrativeAreaLevel1 ? $this->normalize($administrativeAreaLevel1) : null,
            'slug' => $slug,
        ]);

        Log::info("TenantImport CityResolver: Neue Stadt erstellt: {$normalizedName} (ID: {$newCity->id})");

        return $this->cacheResult($cacheKey, $newCity->id);
    }

    /**
     * Füllt den Cache mit allen bestehenden Cities (für Bulk-Operationen).
     */
    public function warmCache(): void
    {
        $cities = City::all(['id', 'name', 'zipcode', 'administrative_area_level_1']);

        foreach ($cities as $city) {
            $normalizedName = mb_strtolower(trim($city->name));

            // Cache nach Name + Bundesland
            if ($city->administrative_area_level_1) {
                $key = $this->buildCacheKey($normalizedName, mb_strtolower(trim($city->administrative_area_level_1)));
                $this->cache[$key] = $city->id;
            }

            // Cache nach Name + PLZ
            if ($city->zipcode) {
                $key = $this->buildCacheKey($normalizedName, null, $city->zipcode);
                $this->cache[$key] = $city->id;
            }

            // Cache nach Name allein (erste gewinnt)
            $nameKey = $this->buildCacheKey($normalizedName);
            if (! isset($this->cache[$nameKey])) {
                $this->cache[$nameKey] = $city->id;
            }
        }
    }

    /**
     * Normalisiert einen Namen: Trim, Doppelleerzeichen entfernen, mb_ucfirst.
     */
    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s{2,}/', ' ', $value);
        $value = mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);

        return $value;
    }

    private function buildCacheKey(string $normalizedName, ?string $normalizedState = null, ?string $zipcode = null): string
    {
        $key = mb_strtolower($normalizedName);

        if ($normalizedState) {
            $key .= '|state:' . mb_strtolower($normalizedState);
        }

        if ($zipcode) {
            $key .= '|zip:' . $zipcode;
        }

        return $key;
    }

    private function cacheResult(string $cacheKey, int $cityId): int
    {
        $this->cache[$cacheKey] = $cityId;

        return $cityId;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'stadt-' . time();
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (City::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
