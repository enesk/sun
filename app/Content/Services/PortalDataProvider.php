<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\FactSnippetWriter;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Sources\TenantContext;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Eigendaten des Portals als Faktenschnipsel (#11).
 *
 * Das Alleinstellungsmerkmal der Ratgeber sind Zahlen, die kein Wettbewerber
 * hat: wie viele Betriebe im Ort gelistet sind, wie sie bewertet werden,
 * welche Leistungen dort gefragt sind. Der Provider aggregiert das je Stadt
 * und je Bundesland und legt es als fact_snippets ab, damit der Generator
 * (#14) den Regionalblock daraus schreiben kann.
 *
 * Datenschutz ist hier die eigentliche Anforderung, nicht die Aggregation:
 *
 *   - top_rated enthaelt hoechstens drei Betriebe, und von denen nur Name und
 *     Ort. Keine Telefonnummer, keine E-Mail, keine Adresse — die Kontaktdaten
 *     stehen im Portal, nicht im Ratgeber.
 *   - Bewertungen gehen nur aggregiert ein (Anzahl, Mittelwert). Kein
 *     Rezensionstext, kein Verfassername.
 *   - Freitexte aus Leads werden hier gar nicht angefasst; die laufen ueber
 *     ClusterLeadQuestionsJob, der vorher personenbezogene Angaben entfernt.
 *
 * Alle Methoden laufen im Tenant-Kontext.
 */
final class PortalDataProvider
{
    public function __construct(
        private readonly FactSnippetWriter $facts,
    ) {}

    /**
     * Aggregiert Staedte und Bundeslaender, schreibt die Faktenschnipsel und
     * gibt je Region ein Rohsignal zurueck.
     *
     * @return Collection<int, SourceItemDto>
     */
    public function refresh(TenantContext $context): Collection
    {
        if (! $this->tablesExist()) {
            return collect();
        }

        $items = collect();
        $retrievedAt = CarbonImmutable::now();

        if ($context->allowsRegionScope('city')) {
            foreach ($this->topCities($context) as $city) {
                $item = $this->store($this->forCity($city), $context, $retrievedAt);

                if ($item !== null) {
                    $items->push($item);
                }
            }
        }

        if ($context->allowsRegionScope('state')) {
            foreach ($this->statesInUse($context) as $iso) {
                $item = $this->store($this->forState($iso), $context, $retrievedAt);

                if ($item !== null) {
                    $items->push($item);
                }
            }
        }

        return $items;
    }

    /**
     * Kennzahlen einer Stadt.
     *
     * @return array<string, mixed>
     */
    public function forCity(City $city): array
    {
        return $this->aggregate(
            companies: fn (): Builder => Company::query()
                ->where('is_active', true)
                ->where('city_id', $city->getKey()),
            regionScope: 'city',
            regionCode: (string) ($city->slug ?: Str::slug((string) $city->name)),
            regionName: (string) $city->name,
        );
    }

    /**
     * Kennzahlen eines Bundeslands.
     *
     * @return array<string, mixed>
     */
    public function forState(string $iso): array
    {
        $stateName = StateCatalog::name($iso) ?? $iso;

        return $this->aggregate(
            companies: fn (): Builder => Company::query()
                ->where('is_active', true)
                ->whereIn('city_id', City::query()
                    ->where('administrative_area_level_1', $stateName)
                    ->select('id')),
            regionScope: 'state',
            regionCode: $iso,
            regionName: $stateName,
        );
    }

    /**
     * Der Betriebsbestand einer Region wird als Unterabfrage weitergereicht,
     * nicht als ID-Liste: ein Ballungsraum hat schnell einige tausend
     * Betriebe, und ein IN() mit tausenden Werten ist weder indexfreundlich
     * noch fuer den Query-Puffer zumutbar.
     *
     * @param  \Closure(): Builder  $companies
     * @return array<string, mixed>
     */
    private function aggregate(\Closure $companies, string $regionScope, string $regionCode, string $regionName): array
    {
        $count = (int) $companies()->count();

        return [
            'region_scope' => $regionScope,
            'region_code' => $regionCode,
            'region_name' => $regionName,
            'provider_count' => $count,
            'avg_rating' => $count === 0 ? null : $this->averageRating($companies),
            'review_count' => $count === 0 ? 0 : $this->reviewCount($companies),
            'top_rated' => $count === 0 ? [] : $this->topRated($companies),
            'common_services' => $count === 0 ? [] : $this->commonServices($companies),
        ];
    }

    /**
     * @param  \Closure(): Builder  $companies
     */
    private function averageRating(\Closure $companies): ?float
    {
        $average = $companies()->where('rating_count', '>', 0)->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    /**
     * @param  \Closure(): Builder  $companies
     */
    private function reviewCount(\Closure $companies): int
    {
        return (int) Review::query()
            ->whereIn('company_id', $companies()->select('companies.id'))
            ->where('moderation_status', Review::STATUS_APPROVED)
            ->count();
    }

    /**
     * Hoechstens drei Betriebe, nur Name und Ort. Ein Betrieb muss genug
     * Bewertungen haben, damit eine einzelne Fuenf-Sterne-Rezension ihn nicht
     * an die Spitze setzt.
     *
     * @param  \Closure(): Builder  $companies
     * @return array<int, array{name: string, city: ?string, rating: float, rating_count: int}>
     */
    private function topRated(\Closure $companies): array
    {
        $minReviews = max(1, (int) config('content.sources.portal_data.min_reviews_for_top', 3));
        $limit = max(1, (int) config('content.sources.portal_data.top_rated_limit', 3));

        return $companies()
            ->with('city:id,name')
            ->where('rating_count', '>=', $minReviews)
            ->orderByDesc('rating')
            ->orderByDesc('rating_count')
            ->limit($limit)
            ->get(['id', 'name', 'city_id', 'rating', 'rating_count'])
            ->map(fn (Company $company): array => [
                'name' => (string) $company->name,
                'city' => $company->city?->getAttribute('name'),
                'rating' => (float) $company->rating,
                'rating_count' => (int) $company->rating_count,
            ])
            ->all();
    }

    /**
     * Die haeufigsten Kategorien der Betriebe der Region — das ist die
     * Leistungspalette, die der Ratgeber ansprechen sollte.
     *
     * @param  \Closure(): Builder  $companies
     * @return array<int, array{name: string, count: int}>
     */
    private function commonServices(\Closure $companies): array
    {
        if (! Schema::connection((new Company)->getConnectionName())->hasTable('category_company')) {
            return [];
        }

        $limit = max(1, (int) config('content.sources.portal_data.services_limit', 5));

        return DB::connection((new Company)->getConnectionName())
            ->table('category_company')
            ->join('categories', 'categories.id', '=', 'category_company.category_id')
            ->whereIn('category_company.company_id', $companies()->select('companies.id')->toBase())
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get(['categories.name as name', DB::raw('COUNT(*) as total')])
            ->map(fn ($row): array => ['name' => (string) $row->name, 'count' => (int) $row->total])
            ->all();
    }

    /**
     * Schreibt die Kennzahlen einer Region als fact_snippets und liefert das
     * zugehoerige Rohsignal.
     *
     * @param  array<string, mixed>  $data
     */
    private function store(array $data, TenantContext $context, CarbonImmutable $retrievedAt): ?SourceItemDto
    {
        if ((int) $data['provider_count'] === 0) {
            return null;
        }

        $scope = (string) $data['region_scope'];
        $code = (string) $data['region_code'];
        $name = (string) $data['region_name'];
        $validDays = max(1, (int) config('content.sources.portal_data.valid_days', 30));

        $common = [
            'region_scope' => $scope,
            'region_code' => $code,
            'source_name' => $context->name,
            'source_url' => $context->baseUrl(),
            'retrieved_at' => $retrievedAt,
            'valid_days' => $validDays,
            'period' => $retrievedAt->format('Y-m'),
        ];

        $snippets = [array_merge($common, [
            'fact_key' => $this->factKey('provider_count', $scope, $code),
            'statement' => "In {$name} sind {$data['provider_count']} Betriebe im Portal gelistet.",
            'value' => (int) $data['provider_count'],
            'unit' => 'Betriebe',
        ])];

        if ($data['avg_rating'] !== null) {
            $rating = number_format((float) $data['avg_rating'], 1, ',', '.');

            $snippets[] = array_merge($common, [
                'fact_key' => $this->factKey('avg_rating', $scope, $code),
                'statement' => "Die gelisteten Betriebe in {$name} sind im Mittel mit {$rating} von 5 Sternen bewertet ({$data['review_count']} Bewertungen).",
                'value' => (float) $data['avg_rating'],
                'unit' => 'von 5',
            ]);
        }

        if ($data['top_rated'] !== []) {
            $names = implode(', ', array_map(
                static fn (array $entry): string => $entry['city'] !== null && $entry['city'] !== $name
                    ? "{$entry['name']} ({$entry['city']})"
                    : $entry['name'],
                $data['top_rated'],
            ));

            $snippets[] = array_merge($common, [
                'fact_key' => $this->factKey('top_rated', $scope, $code),
                'statement' => "Am besten bewertet in {$name}: {$names}.",
                'value' => count($data['top_rated']),
                'unit' => 'Betriebe',
            ]);
        }

        if ($data['common_services'] !== []) {
            $services = implode(', ', array_map(
                static fn (array $entry): string => $entry['name'],
                $data['common_services'],
            ));

            $snippets[] = array_merge($common, [
                'fact_key' => $this->factKey('common_services', $scope, $code),
                'statement' => "Haeufigste Leistungen in {$name}: {$services}.",
                'value' => count($data['common_services']),
                'unit' => 'Leistungen',
            ]);
        }

        $this->facts->writeMany($snippets);

        return new SourceItemDto(
            type: 'internal',
            title: "Portaldaten {$name}: {$data['provider_count']} Betriebe",
            url: $context->baseUrl(),
            snippet: $snippets[0]['statement'],
            regionScope: $scope,
            regionCode: $code,
            keywords: array_map(
                static fn (array $entry): string => $entry['name'],
                $data['common_services'],
            ),
            signalStrength: (float) config('content.sources.portal_data.signal_strength', 0.5),
            publishedAt: $retrievedAt,
            raw: [
                'provider_count' => $data['provider_count'],
                'avg_rating' => $data['avg_rating'],
                'review_count' => $data['review_count'],
                'top_rated' => $data['top_rated'],
                'common_services' => $data['common_services'],
            ],
            externalId: "portal:{$scope}:{$code}",
            // Ein Datensatz je Region und Monat; taegliche Laeufe schreiben
            // dieselbe Zeile fort statt neue anzulegen.
            fingerprintSeed: "portal|{$scope}|{$code}|".$retrievedAt->format('Y-m'),
        );
    }

    /**
     * Die Staedte mit den meisten Betrieben — dort lohnt ein Regionalblock.
     *
     * @return Collection<int, City>
     */
    private function topCities(TenantContext $context): Collection
    {
        $limit = max(1, (int) config('content.sources.portal_data.max_cities', 25));
        $minCompanies = max(1, (int) config('content.sources.portal_data.min_companies_per_city', 3));

        return City::query()
            ->withCount(['companies' => fn ($query) => $query->where('is_active', true)])
            ->having('companies_count', '>=', $minCompanies)
            ->orderByDesc('companies_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Bundeslaender, in denen das Portal ueberhaupt Betriebe hat. Bevorzugte
     * Laender des Mandanten gehen vor, sonst zaehlt die Datenlage.
     *
     * @return array<int, string>
     */
    private function statesInUse(TenantContext $context): array
    {
        $preferred = array_values(array_filter(
            $context->preferredStates(),
            static fn ($iso): bool => is_string($iso) && StateCatalog::exists($iso),
        ));

        if ($preferred !== []) {
            return $preferred;
        }

        $limit = max(1, (int) config('content.sources.portal_data.max_states', 4));

        return City::query()
            ->whereNotNull('administrative_area_level_1')
            ->select('administrative_area_level_1')
            ->groupBy('administrative_area_level_1')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->pluck('administrative_area_level_1')
            ->map(static fn ($name): ?string => StateCatalog::fromText((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function factKey(string $metric, string $scope, string $code): string
    {
        return "portal.{$metric}.{$scope}.".Str::slug($code);
    }

    private function tablesExist(): bool
    {
        $schema = Schema::connection((new Company)->getConnectionName());

        return $schema->hasTable('companies') && $schema->hasTable('cities');
    }
}
