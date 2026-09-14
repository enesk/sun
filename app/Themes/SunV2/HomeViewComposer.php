<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Ergaenzt die Startseite um die Daten, die nur das Theme sun-v2 zeigt.
 *
 * PortalHomeController bleibt fuer alle Themes gleich; was sun-v2 zusaetzlich
 * braucht (Betriebe je Leistung, Top bewertet, zwoelf Staedte, Texte mit
 * eingesetzten Zahlen), kommt hier dazu — und nur, wenn sun-v2 das aktive
 * Theme ist. Die anderen Themes zahlen dafuer keine Abfrage.
 */
final class HomeViewComposer
{
    public const THEME = 'sun-v2';

    private const TOP_RATED_POOL = 60;

    public function __construct(private readonly ThemeManager $themes) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== self::THEME) {
            return;
        }

        $config = config('themes.sun-v2');
        $data = $view->getData();

        $totalCompanies = (int) ($data['totalCompanies'] ?? 0);
        $totalCities = (int) ($data['totalCities'] ?? 0);

        $replace = [
            ':portal' => (string) (tenant()?->name ?? config('app.name')),
            ':companies' => self::roundedDown($totalCompanies),
            ':cities' => self::roundedDown($totalCities),
        ];

        $view->with('sun', [
            'meta' => [
                'title' => strtr($config['meta']['title'], $replace),
                'description' => strtr($config['meta']['description'], $replace),
            ],
            'hero' => [
                ...$config['hero'],
                'text' => strtr($config['hero']['text'], $replace),
            ],
            'services' => $this->services($config['services']),
            'topRated' => $this->topRated((int) $config['top_rated_min_reviews']),
            'cities' => $this->cities(),
            'cta' => $config['cta'],
            'citiesHeading' => $config['cities_heading'],
            'seo' => [
                'headline' => $config['seo']['headline'],
                'paragraphs' => array_map(fn (string $text): string => strtr($text, $replace), $config['seo']['paragraphs']),
            ],
        ]);
    }

    /**
     * Leistungskacheln mit Betriebszahl. Gezaehlt wird dieselbe Volltextsuche,
     * auf die die Kachel verlinkt — die Zahl stimmt also mit der Trefferliste.
     *
     * @param  array<int, array{label: string, query: string, icon: string}>  $services
     * @return array<int, array{label: string, query: string, icon: string, count: int}>
     */
    private function services(array $services): array
    {
        $counts = Cache::remember(TenantCache::key('sun-v2.home.service_counts'), 21600, fn (): array => collect($services)
            ->mapWithKeys(fn (array $service): array => [
                $service['query'] => Company::active()->search($service['query'])->count(),
            ])
            ->all());

        return array_map(
            fn (array $service): array => [...$service, 'count' => (int) ($counts[$service['query']] ?? 0)],
            $services,
        );
    }

    /**
     * Drei zufaellige Betriebe aus den bestbewerteten. Gecacht wird nur der
     * Pool der IDs (die 60 besten), gezogen wird bei jedem Aufruf neu — so
     * wechselt die Auswahl, ohne ORDER BY RAND() ueber alle Betriebe.
     * Oeffnungszeiten und Status kommen damit ebenfalls immer frisch.
     *
     * @return Collection<int, Company>
     */
    private function topRated(int $minReviews): Collection
    {
        $pool = Cache::remember(TenantCache::key('sun-v2.home.top_rated_pool'), 3600, fn (): array => Company::active()
            ->where('rating_count', '>=', $minReviews)
            ->orderByDesc('rating')
            ->orderByDesc('rating_count')
            ->limit(self::TOP_RATED_POOL)
            ->pluck('id')
            ->all());

        if ($pool === []) {
            return new Collection;
        }

        $ids = collect($pool)->shuffle()->take(3)->all();

        return Company::active()
            ->whereKey($ids)
            ->with(['categories', 'city', 'media', 'openingHours'])
            ->get()
            ->shuffle();
    }

    /**
     * @return Collection<int, City>
     */
    private function cities(): Collection
    {
        return Cache::remember(TenantCache::key('sun-v2.home.cities'), 3600, fn (): Collection => City::query()
            ->withCount(['companies' => fn ($query) => $query->where('is_active', true)])
            // Importreste: Orte ohne echten Namen gehoeren nicht auf die Startseite
            ->whereNotIn('name', ['', 'None'])
            ->having('companies_count', '>', 0)
            ->orderByDesc('companies_count')
            ->take(12)
            ->get());
    }

    /**
     * "29.432" -> "29.000", "5.504" -> "5.500": Werbetext der Vorlage rundet ab.
     */
    private static function roundedDown(int $value): string
    {
        $step = match (true) {
            $value >= 10000 => 1000,
            $value >= 1000 => 100,
            $value >= 100 => 10,
            default => 1,
        };

        return number_format(intdiv($value, $step) * $step, 0, ',', '.');
    }
}
