<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\Models\Portal\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryMapper
{
    /** @var array<string, int> source_key → target category_id */
    private array $sourceKeyMap = [];

    /** @var array<string, int> slug → target category_id */
    private array $slugMap = [];

    /** @var array<string, int> lowercase name → target category_id */
    private array $nameMap = [];

    private ?int $fallbackCategoryId = null;

    /** @var string[] Ignorierte generische Kategorien */
    private array $ignoredCategories = [];

    private bool $initialized = false;

    /**
     * Initialisiert die Mapping-Tabellen (einmal pro Import-Run).
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->ignoredCategories = config('category-mapping.ignored', []);

        // Alle Kategorien mit source_key laden
        $categories = Category::whereNotNull('source_key')
            ->where('source_key', '!=', '_parent')
            ->get(['id', 'name', 'slug', 'source_key']);

        foreach ($categories as $cat) {
            if ($cat->source_key === '_fallback') {
                $this->fallbackCategoryId = $cat->id;
                continue;
            }

            $keys = explode(',', $cat->source_key);
            foreach ($keys as $key) {
                $trimmed = trim($key);
                $this->sourceKeyMap[$trimmed] = $cat->id;
            }

            $this->slugMap[mb_strtolower($cat->slug)] = $cat->id;
            $this->nameMap[mb_strtolower($cat->name)] = $cat->id;
        }

        $this->initialized = true;
    }

    /**
     * Mappt eine alte Kategorie auf eine neue category_id.
     * Reihenfolge: source_key → Name → Slug → Fallback "Sonstiges"
     */
    public function mapCategory(string $oldCategoryName, ?string $oldSlug = null): ?int
    {
        $this->initialize();

        // 1. source_key Match (der Slug aus dem Altsystem = EN Google Places Type)
        if ($oldSlug && isset($this->sourceKeyMap[$oldSlug])) {
            return $this->sourceKeyMap[$oldSlug];
        }

        // Auch den Namen als source_key probieren
        $lowerName = mb_strtolower(trim($oldCategoryName));
        if (isset($this->sourceKeyMap[$lowerName])) {
            return $this->sourceKeyMap[$lowerName];
        }

        // 2. Name-Vergleich (case-insensitive)
        if (isset($this->nameMap[$lowerName])) {
            return $this->nameMap[$lowerName];
        }

        // 3. Slug-Vergleich
        if ($oldSlug) {
            $lowerSlug = mb_strtolower(trim($oldSlug));
            if (isset($this->slugMap[$lowerSlug])) {
                return $this->slugMap[$lowerSlug];
            }
        }

        // 4. Prüfen ob ignorierte Kategorie
        if ($oldSlug && in_array($oldSlug, $this->ignoredCategories)) {
            return null;
        }

        // 5. Fallback "Sonstiges"
        Log::info("TenantImport CategoryMapper: Nicht zuordenbar — \"{$oldCategoryName}\" (slug: {$oldSlug}). Fallback: Sonstiges.");

        return $this->fallbackCategoryId;
    }

    /**
     * Migriert die Kategorie-Pivot-Beziehungen für einen Place.
     *
     * @return int Anzahl tatsächlich gemappter Kategorien
     */
    public function mapPivot(int $oldPlaceId, int $newCompanyId, string $tempConnection): int
    {
        $this->initialize();

        $pivotRows = DB::connection($tempConnection)
            ->table('place_category')
            ->where('place_id', $oldPlaceId)
            ->get();

        $sourceCategoryIds = $pivotRows->pluck('place_category_id')->unique()->all();
        if (empty($sourceCategoryIds)) {
            return 0;
        }

        $sourceCategories = DB::connection($tempConnection)
            ->table('place_categories')
            ->whereIn('id', $sourceCategoryIds)
            ->get()
            ->keyBy('id');

        $mapped = 0;
        $hasNonIgnored = false;
        $assignedCategories = [];

        foreach ($pivotRows as $pivot) {
            $sourceCat = $sourceCategories->get($pivot->place_category_id);
            if (! $sourceCat) {
                continue;
            }

            $slug = $sourceCat->slug ?? null;
            $name = $sourceCat->name ?? '';

            if ($slug && in_array($slug, $this->ignoredCategories)) {
                continue;
            }

            $hasNonIgnored = true;
            $targetCategoryId = $this->mapCategory($name, $slug);

            if ($targetCategoryId && ! isset($assignedCategories[$targetCategoryId])) {
                $assignedCategories[$targetCategoryId] = true;

                DB::table('category_company')->insertOrIgnore([
                    'company_id' => $newCompanyId,
                    'category_id' => $targetCategoryId,
                ]);
                $mapped++;
            }
        }

        // Wenn nur ignorierte Kategorien → Fallback "Sonstiges"
        if (! $hasNonIgnored && $this->fallbackCategoryId && ! isset($assignedCategories[$this->fallbackCategoryId])) {
            DB::table('category_company')->insertOrIgnore([
                'company_id' => $newCompanyId,
                'category_id' => $this->fallbackCategoryId,
            ]);
            $mapped++;
        }

        return $mapped;
    }

    public function getFallbackCategoryId(): ?int
    {
        $this->initialize();

        return $this->fallbackCategoryId;
    }
}
