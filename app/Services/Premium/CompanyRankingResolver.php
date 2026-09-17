<?php

namespace App\Services\Premium;

use App\Constants\FeaturedPlacementStatus;
use App\Models\Tenant;
use App\Themes\ThemeManager;
use Illuminate\Support\Facades\DB;

/**
 * Platz eines Betriebs in seiner Haupt-Liste (#15).
 *
 * Ein Portal ist genau eine Branche, die Haupt-Liste ist deshalb die
 * Stadtseite /staedte/{slug} ohne Filter. Die Reihenfolge entspricht
 * PublicCityController@show: aktive Top-Platzierungen der Stadt nach Slot
 * (#6, Company::orderFeaturedFirst), dann is_premium, dann die Voreinstellung
 * des Themes (config themes.<slug>.city.default_sort). Aendert sich dort die
 * Sortierung, muss sie hier nachgezogen werden.
 */
class CompanyRankingResolver
{
    public function __construct(
        private ThemeManager $themes,
    ) {}

    /**
     * @param  list<int>  $companyIds
     * @return array<int, int> company_id => Platz (ab 1); Betriebe ohne Stadt fehlen
     */
    public function positions(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $connection = DB::connection('tenant');
        $positions = [];

        foreach (array_chunk($companyIds, 1000) as $chunk) {
            $cityIds = $connection->table('companies')
                ->whereIn('id', $chunk)
                ->whereNotNull('city_id')
                ->distinct()
                ->pluck('city_id')
                ->all();

            if ($cityIds === []) {
                continue;
            }

            $ranked = $connection->table('companies')
                ->select('id', 'city_id')
                ->selectRaw("ROW_NUMBER() OVER (PARTITION BY city_id ORDER BY {$this->orderBy()}) AS position")
                ->where('is_active', true)
                ->whereIn('city_id', $cityIds);

            $rows = $connection->query()
                ->fromSub($ranked, 'ranked')
                ->whereIn('id', $chunk)
                ->pluck('position', 'id');

            foreach ($rows as $id => $position) {
                $positions[(int) $id] = (int) $position;
            }
        }

        return $positions;
    }

    private function orderBy(): string
    {
        $tenant = tenant();
        $themeSlug = $tenant instanceof Tenant ? $this->themes->getTenantTheme($tenant) : null;

        $sort = match (config("themes.{$themeSlug}.city.default_sort", 'name')) {
            'rating' => 'rating DESC, rating_count DESC',
            'newest' => 'created_at DESC',
            default => 'name ASC',
        };

        $active = FeaturedPlacementStatus::ACTIVE->value;
        $featured = "COALESCE((SELECT MIN(fp.slot) FROM featured_placements fp WHERE fp.company_id = companies.id AND fp.city_id = companies.city_id AND fp.status = '{$active}'), 255)";

        return "{$featured} ASC, is_premium DESC, {$sort}, id ASC";
    }
}
