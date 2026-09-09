<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\LlmContext;
use App\Content\Models\KeywordCluster;
use App\Content\Providers\DataForSeoClient;
use App\Content\Providers\Exceptions\ProviderQuotaException;
use App\Content\Sources\Support\RegionResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Suchergebnisbild zu einem Zielkeyword (#10).
 *
 * Anders als die Connectoren (#7-#11) haengt dieser Dienst nicht am
 * Scheduler: Scoring (#12) und Generator (#14) fragen ihn zu genau dem
 * Keyword, an dem sie gerade arbeiten. Ein Aufruf kostet drei
 * DataForSEO-Anfragen (SERP, Suchvolumen, Autocomplete) plus den eigenen
 * Abruf der ersten Wettbewerberseiten.
 *
 * Damit dieselbe Frage nicht zweimal bezahlt wird, liegt das Ergebnis
 * cache_days Tage (Standard 14) in keyword_clusters.serp_json. Gibt es zum
 * Keyword noch keinen Cluster — beim Scoring der Normalfall, weil Cluster
 * erst in #12 entstehen —, liegt derselbe Stand im Anwendungscache. Beide
 * Wege gelten gleichermassen: innerhalb der Frist entsteht kein zweiter
 * API-Aufruf fuer dasselbe Keyword und dieselbe Region.
 *
 * Muss im Tenant-Kontext laufen: keyword_clusters liegt in der Tenant-DB.
 */
final class SerpInsightService
{
    private const CACHE_PREFIX = 'content:serp:insight:';

    private const OWN_NETWORK_CACHE_KEY = 'content:serp:own-network-domains';

    public function __construct(
        private readonly DataForSeoClient $client,
        private readonly CompetitorOutlineExtractor $outlines,
    ) {}

    /**
     * Das Suchergebnisbild zu $keyword in der Region $regionCode
     * (ISO-3166-2, z. B. 'DE-BY'; null = bundesweit).
     *
     * @param  bool  $force  Zwischenspeicher uebergehen und neu abrufen
     */
    public function for(string $keyword, ?string $regionCode = null, bool $force = false): SerpInsightDto
    {
        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword === '') {
            throw new InvalidArgumentException('Ein Zielkeyword darf nicht leer sein.');
        }

        [$scope, $code, $locationCode] = $this->region($regionCode);

        $cluster = $this->cluster($keyword, $scope, $code);

        if (! $force) {
            $cached = $this->cached($keyword, $code, $cluster);

            if ($cached instanceof SerpInsightDto) {
                return $cached;
            }
        }

        $insight = $this->fetch($keyword, $code, $locationCode);

        if (! $insight->isEmpty()) {
            $this->store($insight, $cluster);
        }

        return $insight;
    }

    /**
     * Bequemer Einstieg fuer #12/#14: das Suchergebnisbild zum
     * Hauptkeyword eines Clusters, in dessen Region.
     */
    public function forCluster(KeywordCluster $cluster, bool $force = false): SerpInsightDto
    {
        return $this->for(
            (string) $cluster->primary_keyword,
            $cluster->region_scope === RegionResolver::SCOPE_STATE ? $cluster->region_code : null,
            $force,
        );
    }

    /**
     * Gespeicherter Stand, sofern er die Frist einhaelt.
     */
    private function cached(string $keyword, ?string $code, ?KeywordCluster $cluster): ?SerpInsightDto
    {
        $days = $this->cacheDays();

        if ($cluster !== null) {
            $insight = SerpInsightDto::fromArray($cluster->serp_json);

            if ($insight !== null && $insight->isFresh($days)) {
                return $insight;
            }
        }

        return SerpInsightDto::fromArray(Cache::get($this->cacheKey($keyword, $code)));
    }

    private function store(SerpInsightDto $insight, ?KeywordCluster $cluster): void
    {
        if ($cluster !== null) {
            $cluster->forceFill([
                'serp_json' => $insight->toArray(),
                'serp_fetched_at' => $insight->fetchedAt,
            ])->save();

            return;
        }

        Cache::put(
            $this->cacheKey($insight->keyword, $insight->regionCode),
            $insight->toArray(),
            CarbonImmutable::now()->addDays($this->cacheDays()),
        );
    }

    /**
     * Ein Abruf: SERP, Suchvolumen, Autocomplete, danach die Gliederung der
     * ersten fremden Treffer.
     *
     * Kontingent- und Budgetgrenzen beenden den Abruf, ohne zu scheitern —
     * der Aufrufer bekommt, was bis dahin da ist. Ein leeres Ergebnis wird
     * nicht gespeichert, damit der naechste Lauf es erneut versucht.
     */
    private function fetch(string $keyword, ?string $code, int $locationCode): SerpInsightDto
    {
        $context = LlmContext::current();

        $organic = [];
        $paa = [];
        $related = [];
        $autocomplete = [];
        $volume = [];

        try {
            [$organic, $paa, $related] = $this->serp($keyword, $locationCode, $context);
            $volume = $this->volume($keyword, $locationCode, $context);
            $autocomplete = $this->autocomplete($keyword, $locationCode, $context);
        } catch (ProviderQuotaException|BudgetExceededException $exception) {
            Log::warning('SERP-Analyse vorzeitig beendet.', [
                'keyword' => $keyword,
                'region_code' => $code,
                'reason' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('SERP-Analyse gescheitert.', [
                'keyword' => $keyword,
                'region_code' => $code,
                'error' => $exception->getMessage(),
            ]);
        }

        $insight = new SerpInsightDto(
            keyword: $keyword,
            regionCode: $code,
            locationCode: $locationCode,
            paa: $this->limit($paa, (int) config('content.serp_insight.max_paa', 12)),
            related: $this->limit($related, (int) config('content.serp_insight.max_related', 12)),
            autocomplete: $this->limit($autocomplete, (int) config('content.serp_insight.max_autocomplete', 15)),
            topResults: $organic,
            searchVolume: isset($volume['search_volume']) ? (int) $volume['search_volume'] : null,
            cpc: isset($volume['cpc']) ? round((float) $volume['cpc'], 2) : null,
            competition: isset($volume['competition']) ? (string) $volume['competition'] : null,
            competitionIndex: isset($volume['competition_index']) ? (int) $volume['competition_index'] : null,
            fetchedAt: CarbonImmutable::now(),
        );

        return $insight->withTopResults($this->withOutlines($insight->topResults));
    }

    /**
     * Organische Treffer, PAA-Fragen und Related Searches aus einer
     * SERP-Antwort.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, string>}
     */
    private function serp(string $keyword, int $locationCode, LlmContext $context): array
    {
        $result = $this->client->serpOrganic($keyword, $locationCode, $context);
        $items = (array) (($result[0] ?? [])['items'] ?? []);

        $own = $this->ownNetworkDomains();
        $maxResults = max(1, (int) config('content.serp_insight.top_results', 10));

        $organic = [];
        $paa = [];
        $related = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');

            if ($type === 'organic') {
                $organic = $this->appendOrganic($organic, $item, $own, $maxResults);

                continue;
            }

            if ($type === 'people_also_ask') {
                $paa = array_merge($paa, $this->questions($item));

                continue;
            }

            if ($type === 'related_searches') {
                $related = array_merge($related, $this->strings((array) ($item['items'] ?? [])));
            }
        }

        return [$organic, $paa, $related];
    }

    /**
     * @param  array<int, array<string, mixed>>  $organic
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $own
     * @return array<int, array<string, mixed>>
     */
    private function appendOrganic(array $organic, array $item, array $own, int $maxResults): array
    {
        $url = is_string($item['url'] ?? null) ? trim($item['url']) : '';

        if ($url === '' || count($organic) >= $maxResults) {
            return $organic;
        }

        $domain = $this->domain($url);

        $organic[] = [
            'position' => count($organic) + 1,
            'title' => Str::limit((string) ($item['title'] ?? ''), 255, ''),
            'url' => Str::limit($url, 2048, ''),
            'domain' => $domain,
            'own_network' => $this->isOwnNetwork($domain, $own),
            'h2s' => [],
            'word_count' => 0,
        ];

        return $organic;
    }

    /**
     * PAA-Box: die Frage selbst und, bei Klicktiefe 1, die nachgeladenen
     * Folgefragen.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function questions(array $item): array
    {
        $questions = [];

        foreach ((array) ($item['items'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (['title', 'seed_question'] as $key) {
                if (is_string($entry[$key] ?? null) && trim($entry[$key]) !== '') {
                    $questions[] = trim($entry[$key]);
                }
            }
        }

        return $questions;
    }

    /**
     * Suchvolumen-Zeile zum Keyword. Der Endpunkt liefert eine Liste, auch
     * wenn nur ein Keyword gefragt war.
     *
     * @return array<string, mixed>
     */
    private function volume(string $keyword, int $locationCode, LlmContext $context): array
    {
        foreach ($this->client->searchVolume([$keyword], $locationCode, $context) as $row) {
            if (mb_strtolower((string) ($row['keyword'] ?? '')) === mb_strtolower($keyword)) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function autocomplete(string $keyword, int $locationCode, LlmContext $context): array
    {
        $result = $this->client->autocomplete($keyword, $locationCode, $context);

        $suggestions = [];

        foreach ((array) (($result[0] ?? [])['items'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['suggestion'] ?? null)) {
                $suggestions[] = trim($entry['suggestion']);
            }
        }

        return $suggestions;
    }

    /**
     * H2-Gliederung und Wortzahl der ersten fremden Treffer. Eigene Treffer
     * werden uebersprungen: deren Gliederung steht schon in der Datenbank.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function withOutlines(array $results): array
    {
        $budget = max(0, (int) config('content.serp_insight.outline_top_n', 3));

        foreach ($results as $index => $result) {
            if ($budget === 0) {
                break;
            }

            if ($result['own_network'] === true) {
                continue;
            }

            $budget--;
            $outline = $this->outlines->extract((string) $result['url']);

            if ($outline === null) {
                continue;
            }

            $results[$index]['h2s'] = $outline['h2s'];
            $results[$index]['word_count'] = $outline['word_count'];
        }

        return $results;
    }

    /**
     * Region auf [scope, code, location_code] aufloesen. $regionCode ist der
     * ISO-3166-2-Code aus config('content.regions.states'); null bedeutet
     * bundesweit.
     *
     * @return array{0: string, 1: ?string, 2: int}
     */
    private function region(?string $regionCode): array
    {
        $code = $regionCode !== null ? strtoupper(trim($regionCode)) : null;

        if ($code === null || $code === '' || $code === 'DE') {
            return [
                RegionResolver::SCOPE_NATIONAL,
                null,
                (int) config('content.regions.country.location_code', 2276),
            ];
        }

        $state = (array) config("content.regions.states.{$code}", []);

        if ($state === []) {
            $known = implode(', ', array_keys((array) config('content.regions.states', [])));

            throw new InvalidArgumentException("Unbekannte Region '{$code}'. Erlaubt sind: DE, {$known}.");
        }

        return [RegionResolver::SCOPE_STATE, $code, (int) $state['location_code']];
    }

    /**
     * Cluster zum Keyword in dieser Region, sofern es ihn schon gibt. Dieser
     * Dienst legt keinen an — Cluster entstehen im Scoring (#12), und ein
     * hier erzeugter Rumpf-Cluster ohne Zentroid wuerde dort als Kandidat
     * mitlaufen.
     */
    private function cluster(string $keyword, string $scope, ?string $code): ?KeywordCluster
    {
        try {
            return KeywordCluster::query()
                ->forRegion($scope, $code)
                ->whereRaw('LOWER(primary_keyword) = ?', [mb_strtolower($keyword)])
                ->first();
        } catch (Throwable $exception) {
            // Ausserhalb des Tenant-Kontexts oder vor der Migration: der
            // Anwendungscache uebernimmt die Frist.
            Log::info('SERP-Analyse ohne Cluster-Zwischenspeicher.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Domains des eigenen Portalnetzes: alle gepflegten Mandanten-Domains
     * plus die Liste aus der Konfiguration.
     *
     * @return array<int, string>
     */
    public function ownNetworkDomains(): array
    {
        return Cache::remember(self::OWN_NETWORK_CACHE_KEY, 3600, function (): array {
            $domains = array_map(
                fn ($domain) => $this->normalizeDomain((string) $domain),
                (array) config('content.serp_insight.own_network_domains', []),
            );

            foreach (Tenant::query()->pluck('domain') as $domain) {
                if (is_string($domain) && trim($domain) !== '') {
                    $domains[] = $this->normalizeDomain($domain);
                }
            }

            return array_values(array_filter(array_unique($domains)));
        });
    }

    /**
     * Eigene Domain oder Subdomain davon.
     *
     * @param  array<int, string>  $own
     */
    private function isOwnNetwork(string $domain, array $own): bool
    {
        if ($domain === '') {
            return false;
        }

        foreach ($own as $candidate) {
            if ($domain === $candidate || str_ends_with($domain, ".{$candidate}")) {
                return true;
            }
        }

        return false;
    }

    private function domain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $this->normalizeDomain($host) : '';
    }

    /**
     * Hostname in Kleinschreibung, ohne Schema, Pfad und fuehrendes 'www.'.
     */
    private function normalizeDomain(string $value): string
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            return '';
        }

        $host = parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST);
        $host = trim(is_string($host) ? $host : $value, './');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Dedupliziert (ohne Ruecksicht auf Gross-/Kleinschreibung) und begrenzt.
     *
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function limit(array $values, int $max): array
    {
        $unique = [];

        foreach ($values as $value) {
            $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

            if ($clean === '') {
                continue;
            }

            $unique[mb_strtolower($clean)] ??= $clean;
        }

        return array_slice(array_values($unique), 0, max(1, $max));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn ($value) => is_string($value) ? trim($value) : '', $values),
            static fn (string $value) => $value !== '',
        ));
    }

    private function normalizeKeyword(string $keyword): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($keyword)) ?? '');
    }

    private function cacheDays(): int
    {
        return max(1, (int) config('content.serp_insight.cache_days', 14));
    }

    private function cacheKey(string $keyword, ?string $code): string
    {
        return self::CACHE_PREFIX.sha1($keyword.'|'.($code ?? 'DE'));
    }
}
