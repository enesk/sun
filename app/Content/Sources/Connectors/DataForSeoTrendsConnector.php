<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\LlmContext;
use App\Content\Providers\DataForSeoClient;
use App\Content\Providers\Exceptions\ProviderQuotaException;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Google Trends Explore ueber DataForSEO (#8), einmal taeglich je Mandant.
 *
 * Der RSS-Feed sagt, was gerade bundesweit gefragt ist. Dieser Connector
 * beantwortet die andere Haelfte: was innerhalb der Branche des Mandanten
 * waechst, und zwar getrennt nach Bundesland. Genau daraus entstehen spaeter
 * die regionalen Ratgeber (#12).
 *
 * Je Anfrage gehen hoechstens fuenf Seed-Keywords fuer eine Region an den
 * Endpunkt (API-Limit), abgefragt werden 'google_trends_queries_list' fuer
 * die Rising Related Queries und 'google_trends_graph' fuer die
 * Interessekurve der letzten 90 Tage. Aus jeder Rising Query wird ein
 * source_item; die Kurve des zugehoerigen Seed-Keywords haengt im Rohpayload,
 * damit das Scoring den Verlauf sieht, ohne erneut abzufragen.
 *
 * Kontingent und Zwischenspeicher liegen im DataForSeoClient. Ist das
 * Tageskontingent erschoepft, liefert der Lauf, was er bis dahin hat — ein
 * erschoepftes Kontingent ist kein Provider-Ausfall und soll den Connector
 * nicht auf 'degraded' setzen.
 */
class DataForSeoTrendsConnector implements SourceConnector
{
    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function key(): string
    {
        return 'dataforseo_trends';
    }

    public function schedule(): string
    {
        return SourceFrequency::DAILY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $seeds = array_slice(
            $context->branchKeywords(),
            0,
            max(1, (int) $this->option('max_seed_keywords', 5)),
        );

        if ($seeds === [] || ! $this->client->isConfigured()) {
            Log::info('Trends-Explore uebersprungen.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
                'reason' => $seeds === [] ? 'keine Branchen-Keywords' : 'keine Zugangsdaten',
            ]);

            return collect();
        }

        $plan = $this->plan($context, $seeds);

        if ($plan === []) {
            return collect();
        }

        $items = collect();
        $llmContext = LlmContext::current(DataForSeoClient::OPERATION_TRENDS)->withTenantId($context->tenantId);

        foreach ($plan as [$region, $batch]) {
            try {
                $result = $this->client->trendsExplore($batch, $region['location_code'], $llmContext);
            } catch (ProviderQuotaException|BudgetExceededException $exception) {
                Log::warning('Trends-Explore vorzeitig beendet.', [
                    'connector' => $this->key(),
                    'tenant_id' => $context->tenantId,
                    'reason' => $exception->getMessage(),
                    'collected' => $items->count(),
                ]);

                return $items;
            }

            $items = $items->merge($this->toItems($result, $region));
        }

        return $items;
    }

    /**
     * Region-/Keyword-Paare des Laufs, bereits auf das verbleibende
     * Tageskontingent zugeschnitten. Ohne diesen Schnitt liefe der Connector
     * jeden Tag in dieselbe Grenze und wuerde die letzten Regionen nie sehen.
     *
     * @param  array<int, string>  $seeds
     * @return array<int, array{0: array<string, mixed>, 1: array<int, string>}>
     */
    private function plan(TenantContext $context, array $seeds): array
    {
        $batches = array_chunk($seeds, max(1, (int) config('content.providers.dataforseo.trends.keywords_per_request', 5)));

        $plan = [];

        foreach ($this->regions($context) as $region) {
            foreach ($batches as $batch) {
                $plan[] = [$region, $batch];
            }
        }

        $remaining = $this->client->remainingRequests(DataForSeoClient::OPERATION_TRENDS);

        return $remaining >= count($plan) ? $plan : array_slice($plan, 0, max(0, $remaining));
    }

    /**
     * Bundesweit und je Bundesland. Hat der Mandant preferred_states
     * gepflegt, gelten nur die; sonst die ersten max_states_per_run aus
     * config('content.regions.states').
     *
     * @return array<int, array<string, mixed>>
     */
    private function regions(TenantContext $context): array
    {
        $regions = [];

        if ($this->option('include_national', true) && $context->allowsRegionScope('national')) {
            $country = (array) config('content.regions.country', []);

            $regions[] = [
                'scope' => 'national',
                'code' => (string) ($country['iso'] ?? 'DE'),
                'geo' => (string) ($country['trends_geo'] ?? 'DE'),
                'name' => 'Deutschland',
                'location_code' => (int) ($country['location_code'] ?? 2276),
            ];
        }

        if (! $context->allowsRegionScope('state')) {
            return $regions;
        }

        $states = (array) config('content.regions.states', []);
        // preferred_states liefert bereits ISO-Codes (#33).
        $preferred = $context->preferredStates();

        $codes = $preferred !== []
            ? $preferred
            : array_slice(array_keys($states), 0, max(0, (int) $this->option('max_states_per_run', 16)));

        foreach ($codes as $code) {
            $state = (array) ($states[$code] ?? []);
            $locationCode = (int) ($state['location_code'] ?? 0);

            if ($locationCode === 0) {
                continue;
            }

            $regions[] = [
                'scope' => 'state',
                'code' => $code,
                'geo' => $code,
                'name' => (string) ($state['name'] ?? $code),
                'location_code' => $locationCode,
            ];
        }

        return $regions;
    }

    /**
     * Rising Queries einer Antwort in Rohsignale uebersetzen.
     *
     * @param  array<int, array<string, mixed>>  $result
     * @param  array<string, mixed>  $region
     * @return array<int, SourceItemDto>
     */
    private function toItems(array $result, array $region): array
    {
        $items = [];

        foreach ($result as $entry) {
            $blocks = (array) ($entry['items'] ?? []);
            $curves = $this->curves($blocks);

            foreach ($blocks as $block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'google_trends_queries_list') {
                    continue;
                }

                $seed = $this->seedOf($block);
                $rising = (array) (($block['data'] ?? [])['rising'] ?? []);

                foreach (array_slice($rising, 0, max(1, (int) $this->option('max_queries_per_keyword', 10))) as $query) {
                    $item = $this->toItem($query, $seed, $region, $curves[$seed] ?? null);

                    if ($item !== null) {
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $region
     * @param  array<string, mixed>|null  $curve
     */
    private function toItem(mixed $query, string $seed, array $region, ?array $curve): ?SourceItemDto
    {
        if (! is_array($query)) {
            return null;
        }

        $text = trim((string) ($query['query'] ?? ''));

        if ($text === '') {
            return null;
        }

        // DataForSEO reicht Googles 'value' durch: eine Wachstumsangabe in
        // Prozent. Ein fehlender Wert bedeutet "Breakout", also mehr als
        // 5000% Zuwachs — das ist die Vollausschlag-Marke.
        $raw = $query['value'] ?? null;
        $breakoutAt = max(1, (int) config('content.providers.dataforseo.trends.breakout_value', 5000));
        $growthPercent = is_numeric($raw) ? (int) $raw : null;
        $isBreakout = $growthPercent === null || $growthPercent >= $breakoutAt;
        $strength = $isBreakout ? 1.0 : min(1.0, $growthPercent / $breakoutAt);

        if ($strength < (float) $this->option('min_growth', 0.1)) {
            return null;
        }

        $growthLabel = $isBreakout ? 'Breakout' : '+'.number_format($growthPercent, 0, ',', '.').' %';

        return new SourceItemDto(
            type: 'api',
            title: $text,
            // Beleg-URL, wird nie abgerufen: sie unterscheidet dieselbe
            // Suchanfrage in verschiedenen Bundeslaendern im Fingerprint.
            url: 'https://trends.google.com/trends/explore?q='.rawurlencode($text).'&geo='.rawurlencode((string) $region['geo']).'&date=today%203-m',
            snippet: sprintf(
                'Stark wachsende Suchanfrage zu "%s" in %s (%s, letzte 90 Tage).',
                $seed,
                (string) $region['name'],
                $growthLabel,
            ),
            regionScope: (string) $region['scope'],
            regionCode: (string) $region['code'],
            keywords: [$text, $seed],
            signalStrength: $strength,
            publishedAt: now(),
            raw: [
                'seed_keyword' => $seed,
                'growth_percent' => $growthPercent,
                'is_breakout' => $isBreakout,
                'location_code' => $region['location_code'],
                'region_code' => $region['code'],
                'interest_curve' => $curve,
            ],
            externalId: $region['code'].':'.mb_strtolower($seed).':'.mb_strtolower($text),
        );
    }

    /**
     * Interessekurve je Seed-Keyword aus dem Graph-Block.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<string, array<string, mixed>>
     */
    private function curves(array $blocks): array
    {
        $curves = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'google_trends_graph') {
                continue;
            }

            $keywords = array_values((array) ($block['keywords'] ?? []));
            $points = [];

            foreach ((array) ($block['data'] ?? []) as $point) {
                if (! is_array($point)) {
                    continue;
                }

                $values = array_values((array) ($point['values'] ?? []));

                foreach ($keywords as $index => $keyword) {
                    $value = $values[$index] ?? null;

                    if ($value === null) {
                        continue;
                    }

                    $points[(string) $keyword][] = [
                        'date' => (string) ($point['date_from'] ?? ''),
                        'value' => (int) $value,
                    ];
                }
            }

            foreach ($points as $keyword => $series) {
                $values = array_column($series, 'value');
                $average = $values === [] ? 0.0 : array_sum($values) / count($values);
                $latest = $values === [] ? 0 : (int) end($values);

                $curves[$keyword] = [
                    'points' => $series,
                    'average' => round($average, 1),
                    'latest' => $latest,
                    // Positiv = das Interesse liegt ueber dem Schnitt der
                    // letzten 90 Tage, das Thema zieht gerade an.
                    'delta' => round($latest - $average, 1),
                ];
            }
        }

        return $curves;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function seedOf(array $block): string
    {
        $keywords = array_values((array) ($block['keywords'] ?? []));

        return trim((string) ($keywords[0] ?? ($block['keyword'] ?? '')));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.dataforseo_trends.{$key}", $default);
    }
}
