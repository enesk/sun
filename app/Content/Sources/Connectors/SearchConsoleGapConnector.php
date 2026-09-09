<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Models\ArticleMetricRaw;
use App\Content\Providers\SearchConsoleClient;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\RegionResolver;
use App\Content\Sources\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Content-Luecken aus der Google Search Console (#9).
 *
 * Holt taeglich die Suchanfragen der letzten 28 Tage je Tenant-Property
 * (Dimensionen query + page, dataState 'final', 3 Tage Verzoegerung wegen der
 * Datenlatenz), cacht die Rohzeilen in article_metrics_raw und leitet daraus
 * drei Arten von Luecken ab:
 *
 *   ranking_chance  Impressionen >= 50 und Position 8-30 — sichtbar, aber
 *                   knapp hinter Seite 1. Ein eigener Ratgeber hebt das.
 *   snippet_issue   Impressionen >= 100 und CTR < 1 % — die Seite rankt, das
 *                   Snippet oder der Inhalt trifft die Absicht nicht.
 *   content_gap     Nachfrage, deren beste Seite kein Ratgeber ist.
 *
 * Die Klassifikation ist eindeutig: content_gap gewinnt, weil ohne eigenen
 * Ratgeber die beiden anderen Massnahmen ins Leere laufen. Alle zutreffenden
 * Typen stehen zusaetzlich im Rohpayload (gap_types).
 */
class SearchConsoleGapConnector implements SourceConnector
{
    public const SOURCE_TYPE = 'gsc_gap';

    public const GAP_RANKING_CHANCE = 'ranking_chance';

    public const GAP_SNIPPET_ISSUE = 'snippet_issue';

    public const GAP_CONTENT_GAP = 'content_gap';

    public function __construct(
        private readonly SearchConsoleClient $client,
        private readonly RegionResolver $regions,
    ) {}

    public function key(): string
    {
        return 'gsc_gap';
    }

    public function schedule(): string
    {
        return SourceFrequency::DAILY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $property = $this->property($context);

        if ($property === null) {
            // Fehlende Property ist ein Konfigurationsmangel des Mandanten,
            // kein Providerfehler — sonst wuerde ein einziger unkonfigurierter
            // Tenant den Circuit Breaker fuer alle oeffnen.
            Log::warning('Search Console: keine Property hinterlegt, Mandant wird uebersprungen.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
            ]);

            return collect();
        }

        if (! $this->client->isConfigured()) {
            Log::warning('Search Console: kein Service-Account-Schluessel hinterlegt.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
            ]);

            return collect();
        }

        $windowEnd = CarbonImmutable::today()->subDays((int) $this->option('lagDays'));
        $windowDays = (int) $this->option('windowDays');
        $windowStart = $windowEnd->subDays($windowDays - 1);

        $rows = $this->client->searchAnalytics($property, $windowStart, $windowEnd, ['query', 'page']);

        $this->cacheRawRows($rows, $property, $windowEnd, $windowDays);

        return $this->toGapItems($rows, $context);
    }

    /**
     * Property-URL des Mandanten, z. B. 'sc-domain:example.de'.
     */
    private function property(TenantContext $context): ?string
    {
        $property = $context->settings->gsc_property ?? null;

        return is_string($property) && trim($property) !== '' ? trim($property) : null;
    }

    /**
     * Rohzeilen als Cache fuer #23/#24 wegschreiben. Ein Snapshot je
     * Fensterende; ein zweiter Lauf am selben Tag aktualisiert ihn.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function cacheRawRows(array $rows, string $property, CarbonImmutable $windowEnd, int $windowDays): void
    {
        $prefix = (string) $this->option('articlePrefix');
        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $keys = $row['keys'] ?? [];
            $query = is_string($keys[0] ?? null) ? trim($keys[0]) : '';
            $page = is_string($keys[1] ?? null) ? trim($keys[1]) : '';

            if ($query === '' || $page === '') {
                continue;
            }

            $path = $this->path($page);

            $records[] = [
                'window_end' => $windowEnd->toDateString(),
                'window_days' => $windowDays,
                'property' => Str::limit($property, 255, ''),
                'page' => Str::limit($page, 2048, ''),
                'page_path' => Str::limit($path, 512, ''),
                'page_hash' => ArticleMetricRaw::hash($page),
                'query' => Str::limit($query, 512, ''),
                'query_hash' => ArticleMetricRaw::hash($query),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'ctr' => round((float) ($row['ctr'] ?? 0), 4),
                'position' => round((float) ($row['position'] ?? 0), 2),
                'is_article' => str_starts_with($path, $prefix),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            ArticleMetricRaw::query()->upsert(
                $chunk,
                ['window_end', 'page_hash', 'query_hash'],
                ['page', 'page_path', 'query', 'property', 'window_days', 'impressions', 'clicks', 'ctr', 'position', 'is_article', 'updated_at'],
            );
        }

        $this->pruneRaw($windowEnd);
    }

    private function pruneRaw(CarbonImmutable $windowEnd): void
    {
        $days = (int) $this->option('rawRetentionDays');

        if ($days <= 0) {
            return;
        }

        ArticleMetricRaw::query()
            ->where('window_end', '<', $windowEnd->subDays($days)->toDateString())
            ->delete();
    }

    /**
     * Zeilen je Suchanfrage verdichten und die Luecken herausfiltern.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, SourceItemDto>
     */
    private function toGapItems(array $rows, TenantContext $context): Collection
    {
        $prefix = (string) $this->option('articlePrefix');
        $minWords = (int) $this->option('minQueryWords');
        $aggregated = [];

        foreach ($rows as $row) {
            $keys = $row['keys'] ?? [];
            $query = is_string($keys[0] ?? null) ? trim($keys[0]) : '';
            $page = is_string($keys[1] ?? null) ? trim($keys[1]) : '';

            if ($query === '' || $page === '') {
                continue;
            }

            $impressions = (int) ($row['impressions'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $position = (float) ($row['position'] ?? 0);
            $isArticle = str_starts_with($this->path($page), $prefix);

            $bucket = $aggregated[$query] ?? [
                'query' => $query,
                'impressions' => 0,
                'clicks' => 0,
                'position_weighted' => 0.0,
                'best_page' => null,
                'best_page_impressions' => 0,
                'has_article' => false,
                'article_page' => null,
                'pages' => 0,
            ];

            $bucket['impressions'] += $impressions;
            $bucket['clicks'] += $clicks;
            // Position ueber die Seiten mit den Impressionen gewichten; eine
            // Seite mit 3 Impressionen auf Position 90 darf den Schnitt nicht
            // kippen.
            $bucket['position_weighted'] += $position * $impressions;
            $bucket['pages']++;

            if ($impressions >= $bucket['best_page_impressions']) {
                $bucket['best_page'] = $page;
                $bucket['best_page_impressions'] = $impressions;
            }

            if ($isArticle) {
                $bucket['has_article'] = true;
                $bucket['article_page'] ??= $page;
            }

            $aggregated[$query] = $bucket;
        }

        $items = [];

        foreach ($aggregated as $bucket) {
            // Einwortsuchen sind fast immer Marken- oder Navigationsanfragen
            // und taugen nicht als Ratgeberthema. str_word_count zaehlt bei
            // UTF-8 falsch, deshalb ueber Leerzeichen trennen.
            if (count(preg_split('/\s+/u', $bucket['query'], -1, PREG_SPLIT_NO_EMPTY) ?: []) < $minWords) {
                continue;
            }

            $impressions = (int) $bucket['impressions'];
            $clicks = (int) $bucket['clicks'];
            $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
            $position = $impressions > 0 ? $bucket['position_weighted'] / $impressions : 0.0;

            $types = $this->gapTypes($impressions, $ctr, $position, (bool) $bucket['has_article']);

            if ($types === []) {
                continue;
            }

            $items[] = $this->toItem($bucket, $types, $impressions, $clicks, $ctr, $position, $context);
        }

        // Die staerkste Nachfrage zuerst, dann kappen.
        usort($items, fn (SourceItemDto $a, SourceItemDto $b) => $b->signalStrength <=> $a->signalStrength);

        return collect(array_slice($items, 0, (int) $this->option('maxItems')));
    }

    /**
     * Alle zutreffenden Luecken-Typen; der erste ist der fuehrende.
     *
     * @return array<int, string>
     */
    private function gapTypes(int $impressions, float $ctr, float $position, bool $hasArticle): array
    {
        $types = [];

        $gap = (array) $this->option('contentGap');

        if (! $hasArticle && $impressions >= (int) ($gap['min_impressions'] ?? 30)) {
            $types[] = self::GAP_CONTENT_GAP;
        }

        $chance = (array) $this->option('rankingChance');

        if ($impressions >= (int) ($chance['min_impressions'] ?? 50)
            && $position >= (float) ($chance['min_position'] ?? 8.0)
            && $position <= (float) ($chance['max_position'] ?? 30.0)) {
            $types[] = self::GAP_RANKING_CHANCE;
        }

        $snippet = (array) $this->option('snippetIssue');

        if ($impressions >= (int) ($snippet['min_impressions'] ?? 100)
            && $ctr < (float) ($snippet['max_ctr'] ?? 0.01)) {
            $types[] = self::GAP_SNIPPET_ISSUE;
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array<int, string>  $types
     */
    private function toItem(
        array $bucket,
        array $types,
        int $impressions,
        int $clicks,
        float $ctr,
        float $position,
        TenantContext $context,
    ): SourceItemDto {
        $query = (string) $bucket['query'];
        $gapType = $types[0];
        $demandScore = $this->demandScore($impressions);
        $region = $this->regions->resolve($query, $context);

        return new SourceItemDto(
            type: self::SOURCE_TYPE,
            title: $query,
            // Bewusst ohne URL: der Fingerprint des SourceItemDto laeuft ueber
            // URL + Titel. Die beste Seite einer Suchanfrage wechselt von Lauf
            // zu Lauf, das wuerde jedes Mal ein neues source_item erzeugen.
            // best_page steht im Payload.
            url: null,
            snippet: $this->snippet($gapType, $query, $impressions, $ctr, $position, $bucket['best_page']),
            regionScope: $region['scope'],
            regionCode: $region['code'],
            keywords: [$query],
            signalStrength: $demandScore,
            publishedAt: now(),
            raw: [
                'query' => $query,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => round($ctr, 4),
                'position' => round($position, 2),
                'best_page' => $bucket['best_page'],
                'article_page' => $bucket['article_page'],
                'gap_type' => $gapType,
                'gap_types' => $types,
                'demand_score' => $demandScore,
                'region_label' => $region['label'],
                'pages' => $bucket['pages'],
            ],
            externalId: 'gsc:'.substr(hash('sha256', mb_strtolower($query)), 0, 32),
        );
    }

    /**
     * demand_score = log10(1 + Impressionen) / log10(1 + Referenz), auf 0..1
     * begrenzt. Logarithmisch, weil die Impressionsverteilung einer Property
     * einem Potenzgesetz folgt: linear normiert waeren 95 % der Suchanfragen
     * ununterscheidbar nahe null.
     */
    private function demandScore(int $impressions): float
    {
        $reference = max(10, (int) $this->option('demandReference'));

        if ($impressions <= 0) {
            return 0.0;
        }

        return round(min(1.0, log10(1 + $impressions) / log10(1 + $reference)), 3);
    }

    private function snippet(
        string $gapType,
        string $query,
        int $impressions,
        float $ctr,
        float $position,
        ?string $bestPage,
    ): string {
        $numbers = sprintf(
            '%d Impressionen, CTR %.2f %%, Position %.1f',
            $impressions,
            $ctr * 100,
            $position,
        );

        $page = $bestPage !== null ? " Beste Seite: {$bestPage}." : '';

        return match ($gapType) {
            self::GAP_CONTENT_GAP => "Nachfrage ohne eigenen Ratgeber zu \"{$query}\": {$numbers}.{$page}",
            self::GAP_RANKING_CHANCE => "Ranking-Chance fuer \"{$query}\": {$numbers}.{$page}",
            self::GAP_SNIPPET_ISSUE => "Viele Impressionen, kaum Klicks fuer \"{$query}\": {$numbers}.{$page}",
            default => "{$query}: {$numbers}.{$page}",
        };
    }

    /**
     * Pfadanteil einer Seiten-URL, immer mit fuehrendem Schraegstrich.
     */
    private function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    private function option(string $name): mixed
    {
        $config = (array) config('content.sources.gsc_gap', []);

        return match ($name) {
            'articlePrefix' => (string) ($config['article_path_prefix'] ?? '/ratgeber/'),
            'rankingChance' => $config['ranking_chance'] ?? [],
            'snippetIssue' => $config['snippet_issue'] ?? [],
            'contentGap' => $config['content_gap'] ?? [],
            'demandReference' => $config['demand_reference_impressions'] ?? 10000,
            'maxItems' => $config['max_items'] ?? 300,
            'minQueryWords' => $config['min_query_words'] ?? 2,
            'rawRetentionDays' => $config['raw_retention_days'] ?? 120,
            'windowDays' => config('content.providers.search_console.lookback_days', 28),
            'lagDays' => config('content.providers.search_console.lag_days', 3),
            default => null,
        };
    }
}
