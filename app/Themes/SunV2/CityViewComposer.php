<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Post;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Stadtseite /staedte/{slug} im Theme sun-v2 (Vorlage elektrikerportal-stadtseite.html):
 * Einleitung aus echten Zahlen, Filterleiste, Leistungen in der Stadt,
 * Orte in der Naehe, Ratgeber und SEO-Text.
 *
 * Firmenliste und Filter kommen aus PublicCityController::show() (fuer alle
 * Themes); Stadtteile gibt es im Datenmodell nicht, deshalb fehlen der
 * Stadtteil-Filter und die Stadtteil-Chips der Vorlage.
 */
final class CityViewComposer
{
    private const FILTER_KEYS = ['q', 'sort', 'min_rating', 'rated', 'open_now'];

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $data = $view->getData();
        /** @var City $city */
        $city = $data['city'];
        /** @var LengthAwarePaginator $companies */
        $companies = $data['companies'];
        $config = config('themes.sun-v2');
        $filters = $this->filters();

        $stats = $this->stats($city, $config['services']);
        $sortKey = $filters['sort'] !== '' ? $filters['sort'] : $config['city']['default_sort'];
        $sortKey = array_key_exists($sortKey, SearchViewComposer::SORTS) ? $sortKey : 'az';
        $plural = $config['search']['branch_plural'];
        $total = $companies->total();

        $view->with('citySun', [
            'filters' => $filters,
            'heading' => $total > 0
                ? number_format($total, 0, ',', '.')." {$plural} in {$city->name}"
                : "Keine {$plural} in {$city->name} gefunden",
            'intro' => $this->intro($city, $stats, (int) $city->companies_count),
            'sortLabel' => SearchViewComposer::SORTS[$sortKey],
            'sortLinks' => collect(SearchViewComposer::SORTS)->map(fn (string $label, string $key): array => [
                'label' => $label,
                'url' => $this->url($city, ['sort' => $key]),
                'active' => $key === $sortKey,
            ])->values()->all(),
            'toggles' => [
                $this->toggle($city, $filters, 'Ab 4 Sterne', 'min_rating', '4'),
                $this->toggle($city, $filters, 'Jetzt geöffnet', 'open_now', '1'),
                $this->toggle($city, $filters, $config['search']['quick_term'], 'q', $config['search']['quick_term']),
            ],
            'hasFilters' => collect($filters)->except('sort')->filter()->isNotEmpty(),
            'resetUrl' => route('portal.cities.show', $city->slug),
            'pages' => PageWindow::for($companies),
            'services' => collect($config['services'])
                ->map(fn (array $service): array => [
                    ...$service,
                    'count' => (int) ($stats['services'][$service['query']] ?? 0),
                    'url' => route('portal.cities.show', ['slug' => $city->slug, 'q' => $service['query'], 'sort' => 'rating']),
                ])
                ->filter(fn (array $service): bool => $service['count'] > 0)
                ->values()
                ->all(),
            'nearby' => $this->nearby($city),
            'posts' => $this->posts($city),
            'seo' => $this->seo($city, $config['city']['seo']),
            'searchPlaceholder' => $config['search']['placeholder'],
        ]);
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
     * @param  array<string, string|null>  $changes
     */
    private function url(City $city, array $changes): string
    {
        $params = array_filter(
            array_merge($this->filters(), $changes),
            fn ($value): bool => $value !== null && $value !== '',
        );

        return route('portal.cities.show', ['slug' => $city->slug, ...$params]);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{label: string, url: string, active: bool}
     */
    private function toggle(City $city, array $filters, string $label, string $key, string $value): array
    {
        return [
            'label' => $label,
            'url' => $this->url($city, [$key => $filters[$key] === $value ? null : $value]),
            'active' => $filters[$key] === $value,
        ];
    }

    /**
     * Kennzahlen der Stadt: Durchschnitt, Bewertungssumme und Betriebe je
     * Leistung (dieselbe Volltextsuche wie die Leistungslinks). 6 h gecacht.
     *
     * @param  array<int, array{label: string, query: string, icon: string}>  $services
     * @return array{avg: float, reviews: int, services: array<string, int>}
     */
    private function stats(City $city, array $services): array
    {
        return Cache::remember(TenantCache::key("sun-v2.city.stats.{$city->id}"), 21600, function () use ($city, $services): array {
            $base = fn () => Company::active()->where('city_id', $city->id);

            return [
                'avg' => round((float) $base()->where('rating_count', '>', 0)->avg('rating'), 1),
                'reviews' => (int) $base()->sum('rating_count'),
                'services' => collect($services)
                    ->mapWithKeys(fn (array $service): array => [
                        $service['query'] => $base()->search($service['query'])->count(),
                    ])
                    ->all(),
            ];
        });
    }

    /**
     * Einleitung der Vorlage aus echten Zahlen; Teilsaetze ohne Wert entfallen.
     *
     * @param  array{avg: float, reviews: int, services: array<string, int>}  $stats
     */
    private function intro(City $city, array $stats, int $count): ?string
    {
        if ($count === 0) {
            return null;
        }

        $number = fn (int $value): string => number_format($value, 0, ',', '.');
        $wallbox = (int) ($stats['services']['Wallbox'] ?? 0);
        $emergency = (int) ($stats['services']['Notdienst'] ?? 0);

        $first = "In {$city->name} sind {$number($count)} Elektrobetriebe eingetragen";
        $extras = array_filter([
            $wallbox > 0 ? "{$number($wallbox)} davon montieren Wallboxen" : null,
            $emergency > 0 ? "{$number($emergency)} fahren Notdienst" : null,
        ]);
        $sentence = $first.($extras ? ', '.implode(' und ', $extras) : '').'.';

        if ($stats['reviews'] > 0 && $stats['avg'] > 0) {
            $sentence .= ' Der Durchschnitt liegt bei '.number_format($stats['avg'], 1, ',', '')." Sternen aus {$number($stats['reviews'])} Bewertungen.";
        }

        return $sentence.' Such nach Leistung – oder ruf direkt an.';
    }

    /**
     * Orte in der Naehe: gleiche PLZ-Region (erste zwei Ziffern), sonst
     * dasselbe Bundesland. Ohne Geodaten die naechstbeste Naeherung.
     *
     * @return Collection<int, City>
     */
    private function nearby(City $city): Collection
    {
        return Cache::remember(TenantCache::key("sun-v2.city.nearby.{$city->id}"), 3600, function () use ($city): Collection {
            $query = City::query()
                ->whereKeyNot($city->id)
                ->whereNotIn('name', ['', 'None'])
                ->withCount(['companies' => fn ($companies) => $companies->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(8);

            $region = substr((string) $city->zipcode, 0, 2);

            return strlen($region) === 2
                ? $query->where('zipcode', 'like', $region.'%')->get()
                : $query->where('administrative_area_level_1', $city->administrative_area_level_1)->get();
        });
    }

    /**
     * Ratgeber: zuerst Artikel mit dem Stadtnamen im Titel, sonst die neuesten.
     *
     * @return Collection<int, Post>
     */
    private function posts(City $city): Collection
    {
        return Cache::remember(TenantCache::key("sun-v2.city.posts.{$city->id}"), 1800, function () use ($city): Collection {
            $local = Post::published()->with('category')->where('title', 'like', '%'.$city->name.'%')->latest('published_at')->take(2)->get();

            return $local->isNotEmpty()
                ? $local
                : Post::published()->with('category')->latest('published_at')->take(2)->get();
        });
    }

    /**
     * SEO-Text: gepflegter Stadttext (city_contents.intro_text), sonst der
     * Branchentext aus der Theme-Konfiguration.
     *
     * @param  array{headline: string, paragraphs: array<int, string>}  $fallback
     * @return array{headline: string, paragraphs: array<int, string>}
     */
    private function seo(City $city, array $fallback): array
    {
        $headline = strtr($fallback['headline'], [':city' => $city->name]);
        $intro = trim(strip_tags((string) $city->cityContent?->getAttribute('intro_text')));

        if ($intro !== '') {
            return [
                'headline' => $headline,
                'paragraphs' => array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $intro) ?: []))),
            ];
        }

        return [
            'headline' => $headline,
            'paragraphs' => array_map(fn (string $text): string => strtr($text, [':city' => $city->name]), $fallback['paragraphs']),
        ];
    }
}
