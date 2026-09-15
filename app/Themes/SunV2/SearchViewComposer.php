<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Themes\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Suchergebnisseite /firmen im Theme sun-v2: Ueberschrift, Filterzustand,
 * Filter-Links, Stadt-Chips, Kartenpins und Seitenfenster.
 *
 * Gefiltert wird im CompanyController (fuer alle Themes); hier entsteht nur,
 * was die Vorlage darstellt. Links behalten alle uebrigen Parameter und
 * werfen die Seitenzahl weg, damit ein Filterwechsel auf Seite 1 landet.
 */
final class SearchViewComposer
{
    /** Sortierschluessel => Sprachschluessel des Labels (lang/de/portal.php) */
    public const SORTS = [
        'rating' => 'portal.layout.filters.sort_rating',
        'newest' => 'portal.layout.filters.sort_newest',
        'az' => 'portal.layout.filters.sort_name',
    ];

    private const FILTER_KEYS = ['q', 'ort', 'umkreis', 'sort', 'city', 'min_rating', 'rated', 'open_now'];

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $config = config('themes.sun-v2.search');
        $data = $view->getData();

        /** @var LengthAwarePaginator $companies */
        $companies = $data['companies'];
        $filters = $this->filters();
        $origin = $data['searchOrigin'] ?? null;
        $radius = $data['searchRadius'] ?? null;
        $distances = $data['distanceByCity'] ?? [];

        $sortKey = array_key_exists($filters['sort'], self::SORTS) ? $filters['sort'] : 'az';
        $place = $filters['city'] ?: $filters['ort'];

        $sortLabels = self::sortLabels();

        $view->with('search', [
            'filters' => $filters,
            'heading' => $this->heading($companies->total(), $filters['q'], $place),
            'crumb' => $this->crumb($filters['q'], $place),
            'subtitle' => $this->subtitle($sortKey, $origin, $radius),
            'sortLabel' => $sortLabels[$sortKey],
            'sortLinks' => collect($sortLabels)->map(fn (string $label, string $key): array => [
                'label' => $label,
                'url' => $this->url(['sort' => $key]),
                'active' => $key === $sortKey,
            ])->values()->all(),
            'toggles' => $this->toggles($filters, $config['quick_term']),
            'cityLinks' => $this->cityLinks($data['cities'] ?? collect(), $filters['city'], (int) $config['city_chips']),
            'resetCityUrl' => $this->url(['city' => null]),
            'distances' => $distances,
            'pins' => $this->pins($companies->getCollection(), $distances),
            'pages' => PageWindow::for($companies),
            'resetUrl' => route('portal.companies.index'),
            // Leerer Zustand: nur die einschraenkenden Filter entfernen, Suchbegriff und Ort bleiben
            'hasNarrowingFilters' => $filters['city'] !== '' || $filters['min_rating'] !== '' || $filters['rated'] !== '' || $filters['open_now'] !== '',
            'relaxUrl' => $this->url(['city' => null, 'min_rating' => null, 'rated' => null, 'open_now' => null]),
            'seo' => $config['seo'],
        ]);
    }

    /**
     * Uebersetzte Sortierlabels, bei jedem Aufruf frisch — nie in einer
     * Konstante oder statischen Variable halten, sonst traegt ein
     * Tenant-Wechsel im selben Prozess die Begriffe des Vorgaengers weiter.
     *
     * @return array<string, string>
     */
    public static function sortLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::SORTS);
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return collect(self::FILTER_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => trim((string) $this->request->query($key, ''))])
            ->all();
    }

    /**
     * Link auf die Suche mit geaenderten Parametern; null entfernt einen Parameter.
     *
     * @param  array<string, string|int|null>  $changes
     */
    private function url(array $changes): string
    {
        $params = array_filter(
            array_merge($this->filters(), $changes),
            fn ($value): bool => $value !== null && $value !== '',
        );

        return route('portal.companies.index', $params);
    }

    /**
     * H1 und Title-Kern: "6 Elektriker für „Test“", "12 Elektriker in Hamburg".
     * Einzahl/Mehrzahl waehlt trans_choice() am Rohwert, :anzahl ist formatiert.
     */
    private function heading(int $total, string $term, string $place): string
    {
        $variant = match (true) {
            $term !== '' && $place !== '' => 'term_place',
            $term !== '' => 'term',
            $place !== '' => 'place',
            default => 'all',
        };

        return trans_choice("portal.search.heading.{$variant}", $total, [
            'anzahl' => number_format($total, 0, ',', '.'),
            'begriff' => $term,
            'ort' => $place,
        ]);
    }

    private function crumb(string $term, string $place): ?string
    {
        return match (true) {
            $term !== '' => __('portal.layout.breadcrumb.search', ['begriff' => $term]),
            $place !== '' => $place,
            default => null,
        };
    }

    private function subtitle(string $sortKey, ?City $origin, ?int $radius): string
    {
        $parts = [__('portal.search.subtitle_sort', ['sortierung' => __(self::SORTS[$sortKey])])];

        if ($origin && $radius) {
            $parts[] = __('portal.search.subtitle_radius', ['km' => $radius, 'ort' => $origin->name]);
        }

        return implode(' · ', $parts);
    }

    /**
     * Umschalter der Filterleiste. Aktiv heisst: der Parameter ist gesetzt,
     * ein Klick nimmt ihn wieder heraus.
     *
     * @param  array<string, string>  $filters
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function toggles(array $filters, string $quickTerm): array
    {
        $toggle = fn (string $label, string $key, string $value): array => [
            'label' => $label,
            'url' => $this->url([$key => $filters[$key] === $value ? null : $value]),
            'active' => $filters[$key] === $value,
        ];

        return [
            $toggle(__('portal.layout.filters.min_rating'), 'min_rating', '4'),
            $toggle(__('portal.layout.filters.open_now'), 'open_now', '1'),
            $toggle($quickTerm, 'q', $quickTerm),
            $toggle(__('portal.layout.filters.rated'), 'rated', '1'),
        ];
    }

    /**
     * Stadt-Chips: die Orte mit den meisten Betrieben, ohne Importreste
     * wie "None". Der Rest wird nur gezaehlt.
     *
     * @param  Collection<int, City>  $cities
     * @return array{chips: array<int, array{label: string, count: int, url: string, active: bool}>, more: int}
     */
    private function cityLinks(Collection $cities, string $activeCity, int $limit): array
    {
        $valid = $cities->reject(fn (City $city): bool => City::isPlaceholderName($city->name))->values();

        return [
            'chips' => $valid->take($limit)->map(fn (City $city): array => [
                'label' => $city->name,
                'count' => (int) $city->companies_count,
                'url' => $this->url(['city' => $activeCity === $city->name ? null : $city->name, 'ort' => null]),
                'active' => $activeCity === $city->name,
            ])->all(),
            'more' => max(0, $valid->count() - $limit),
        ];
    }

    /**
     * Kartenpins aus den Ortskoordinaten der Treffer dieser Seite, in eine
     * 400×300-Flaeche projiziert. Ohne Koordinaten gibt es keine Pins und die
     * Seite bleibt einspaltig.
     *
     * @param  Collection<int, Company>  $companies
     * @param  array<int, float>  $distances
     * @return array{items: array<int, array{number: int, left: float, top: float}>, center: array{lat: float, lng: float}|null}
     */
    private function pins(Collection $companies, array $distances): array
    {
        if ($distances === []) {
            return ['items' => [], 'center' => null];
        }

        $points = $companies->values()
            ->map(function (Company $company, int $index): ?array {
                /** @var City|null $city */
                $city = $company->city;

                return $city?->latitude && $city->longitude
                    ? ['number' => $index + 1, 'lat' => (float) $city->latitude, 'lng' => (float) $city->longitude]
                    : null;
            })
            ->filter()
            ->values();

        if ($points->isEmpty()) {
            return ['items' => [], 'center' => null];
        }

        [$minLat, $maxLat] = [$points->min('lat'), $points->max('lat')];
        [$minLng, $maxLng] = [$points->min('lng'), $points->max('lng')];
        $latSpan = max($maxLat - $minLat, 0.0001);
        $lngSpan = max($maxLng - $minLng, 0.0001);

        return [
            'items' => $points->map(fn (array $point): array => [
                'number' => $point['number'],
                // 12 % Rand, Norden oben
                'left' => round(12 + 76 * ($point['lng'] - $minLng) / $lngSpan, 1),
                'top' => round(12 + 76 * ($maxLat - $point['lat']) / $latSpan, 1),
            ])->all(),
            'center' => ['lat' => ($minLat + $maxLat) / 2, 'lng' => ($minLng + $maxLng) / 2],
        ];
    }
}
