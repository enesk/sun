<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\CompanyLocationSearch;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Staedteuebersicht /staedte im Theme sun-v2, im Aufbau der Stadtseite:
 * Einleitung aus echten Zahlen, groesste Staedte, Bundeslaender als Pillen
 * und bei gewaehltem Land (?land=bayern) alle Orte von A bis Z.
 *
 * Ohne Land waeren es ueber 5.000 Links auf einer Seite, deshalb gliedert
 * das Bundesland (cities.administrative_area_level_1). Importreste wie
 * "None" und Orte ausserhalb der 16 Bundeslaender fallen ganz heraus.
 */
final class CityIndexViewComposer
{
    public const STATES = [
        'Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg',
        'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen',
        'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen',
    ];

    public const SEARCH_LIMIT = 60;

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $config = config('themes.sun-v2');
        $plural = $config['search']['branch_plural'];
        $cities = $this->cities();
        $number = fn (int $value): string => number_format($value, 0, ',', '.');

        $landSlug = trim((string) $this->request->query('land', ''));
        $land = collect(self::STATES)->first(fn (string $state): bool => Str::slug($state) === $landSlug);

        abort_if($landSlug !== '' && $land === null, 404);

        $term = trim((string) $this->request->query('suche', ''));

        $inLand = $land ? $cities->where('state', $land)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values() : collect();
        $groups = $inLand->groupBy(fn (array $city): string => Str::upper(Str::substr(Str::ascii($city['name']), 0, 1)));

        $totalCities = $land ? $inLand->count() : $cities->count();
        $totalCompanies = (int) ($land ? $inLand->sum('count') : $cities->sum('count'));

        $view->with('citiesSun', [
            'land' => $land,
            'totalCities' => $totalCities,
            'totalCompanies' => $totalCompanies,
            'landSlug' => $land ? Str::slug($land) : null,
            'heading' => $land
                ? "{$plural} in {$land}"
                : "{$plural} in {$number($totalCities)} Städten",
            'intro' => $totalCities > 0
                ? ($land
                    ? "In {$land} sind {$number($totalCompanies)} Elektrobetriebe in {$number($totalCities)} Orten eingetragen. Wähl deinen Ort – oder such direkt nach Leistung."
                    : "{$number($totalCompanies)} Elektrobetriebe in {$number($totalCities)} Städten und Gemeinden. Wähl deine Stadt oder dein Bundesland – oder such direkt nach Leistung.")
                : null,
            'top' => ($land ? $inLand->sortByDesc('count') : $cities)->take(12)->values(),
            'states' => collect(self::STATES)->map(fn (string $state): array => [
                'label' => $state,
                'count' => $cities->where('state', $state)->count(),
                'url' => $land === $state
                    ? route('portal.cities.index')
                    : route('portal.cities.index', ['land' => Str::slug($state)]),
                'active' => $land === $state,
            ])->filter(fn (array $state): bool => $state['count'] > 0)->values()->all(),
            'groups' => $groups,
            'term' => $term,
            'results' => $term !== '' ? $this->search($land ? $inLand : $cities, $term) : null,
            'searchPlaceholder' => $config['search']['placeholder'],
            'seo' => [
                'headline' => strtr($config['cities_index']['seo']['headline'], [':land' => $land ?? 'Deutschland']),
                'paragraphs' => array_map(
                    fn (string $text): string => strtr($text, [':land' => $land ?? 'Deutschland']),
                    $config['cities_index']['seo']['paragraphs'],
                ),
            ],
        ]);
    }

    /**
     * Alle Orte mit aktiven Betrieben, groesste zuerst. 1 h gecacht, als
     * schlanke Arrays (ueber 5.000 Zeilen).
     *
     * @return Collection<int, array{id: int, name: string, slug: string, zip: string, state: string, count: int}>
     */
    private function cities(): Collection
    {
        return collect(Cache::remember(TenantCache::key('sun-v2.cities.index.v3'), 3600, fn (): array => City::query()
            ->select(['id', 'name', 'slug', 'zipcode', 'administrative_area_level_1'])
            ->withCount(['companies' => fn ($query) => $query->where('is_active', true)])
            ->whereNotIn('name', ['', 'None'])
            ->whereIn('administrative_area_level_1', self::STATES)
            ->having('companies_count', '>', 0)
            ->orderByDesc('companies_count')
            ->get()
            ->map(fn (City $city): array => [
                'id' => (int) $city->getKey(),
                'name' => (string) $city->getAttribute('name'),
                'slug' => (string) $city->getAttribute('slug'),
                'zip' => (string) $city->getAttribute('zipcode'),
                'state' => (string) $city->getAttribute('administrative_area_level_1'),
                'count' => (int) $city->getAttribute('companies_count'),
            ])
            ->all()));
    }

    /**
     * Staedtesuche: Eine PLZ ("80331", "80331 München", "1067") sucht in den
     * PLZ der Betriebe und der Orte (cities fuehrt nur eine PLZ je Ort,
     * Hamburg z. B. 22761), Text den Ortsnamen ohne Gross-/Kleinschreibung,
     * "Muenchen" wie "München".
     * Treffer am Wortanfang zuerst, dann die groessten Orte; hoechstens 60.
     *
     * @param  Collection<int, array<string, mixed>>  $cities  Eintraege aus cities()
     */
    private function search(Collection $cities, string $term): Collection
    {
        $patterns = CompanyLocationSearch::zipPatterns($term);

        if ($patterns !== []) {
            $ids = Company::active()
                ->where(function ($query) use ($patterns): void {
                    foreach ($patterns as $pattern) {
                        $query->orWhere('zipcode', 'like', $pattern);
                    }
                })
                ->distinct()
                ->pluck('city_id')
                ->flip();

            return $cities
                ->filter(fn (array $city): bool => $ids->has($city['id'])
                    || collect($patterns)->contains(fn (string $pattern): bool => Str::is(str_replace('%', '*', $pattern), $city['zip'])))
                ->take(self::SEARCH_LIMIT)
                ->values();
        }

        $needle = self::normalize($term);

        return $cities
            ->map(fn (array $city): array => [...$city, 'pos' => mb_strpos(self::normalize($city['name']), $needle)])
            ->filter(fn (array $city): bool => $city['pos'] !== false)
            ->sortBy([
                fn (array $a, array $b): int => ($a['pos'] === 0 ? 0 : 1) <=> ($b['pos'] === 0 ? 0 : 1),
                fn (array $a, array $b): int => $b['count'] <=> $a['count'],
            ])
            ->take(self::SEARCH_LIMIT)
            ->values();
    }

    private static function normalize(string $value): string
    {
        return strtr(mb_strtolower($value), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    }
}
