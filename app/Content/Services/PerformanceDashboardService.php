<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DisplayStatus;
use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\ArticleMetric;
use App\Content\Models\Central\LlmUsageLog;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zahlen der Leistungsansicht (#25), design/content-dashboard.md, §5.
 *
 * Alles kommt aus `article_metrics` (Tenant) und `llm_usage_logs` (Central) —
 * aus dem Panel heraus wird keine Schnittstelle abgefragt. Die Tageszeilen
 * legt der Metrik-Collector (#23) um 06:30 ab; das Panel liest nur.
 *
 * Massgeblich ist je Artikel die **juengste** Metrikzeile im Zeitraum, nicht
 * die Summe aller Zeilen: eine Zeile traegt bereits die Klicks eines
 * 28-Tage-Fensters der Search Console, Aufsummieren wuerde denselben Klick
 * mehrfach zaehlen. Dieselbe Regel gilt im PerformanceAggregator (#23) — die
 * beiden Ansichten sollen nicht unterschiedlich rechnen. Der Zeitraum
 * 7/30/90 waehlt also aus, **welche** Erhebungszeilen betrachtet werden,
 * nicht ueber welche Tage summiert wird.
 *
 * Es gibt keine zentrale Artikeltabelle: je Portal laeuft ein
 * tenancy()-Durchgang, ein Portal ohne Pipeline-Tabellen wird uebersprungen
 * statt die Seite abzubrechen. Das Ergebnis liegt 60 Sekunden im Cache, damit
 * Seite und die drei Diagramme desselben Aufrufs nicht viermal rechnen.
 */
final class PerformanceDashboardService
{
    /**
     * Waehlbare Zeitraeume in Tagen (design/content-dashboard.md, §5).
     */
    public const PERIODS = [7, 30, 90];

    public const PERIOD_DEFAULT = 30;

    public const CACHE_SECONDS = 60;

    /**
     * Zeilen je Seite der Artikeltabelle — wie in der Artikelliste (#36).
     */
    public const PAGE_SIZE = 50;

    public const SORTS = ['titel', 'portal', 'status', 'impressionen', 'klicks', 'position', 'ertrag'];

    public const SORT_DEFAULT = 'impressionen';

    public const DIRECTION_ASC = 'auf';

    public const DIRECTION_DESC = 'ab';

    /**
     * Laenge der Ranglisten Top/Flop.
     */
    public const RANK_LIMIT = 10;

    /**
     * Hoechstzahl gemessener Artikel je Portal und Aufruf.
     */
    private const PER_TENANT_LIMIT = 500;

    /**
     * Refresh-Kandidaten je Portal.
     */
    private const REFRESH_LIMIT = 25;

    public function __construct(private readonly ContentPipelineService $pipeline) {}

    /**
     * Gueltiger Zeitraum; alles ausserhalb faellt auf die Vorgabe zurueck.
     */
    public function period(int $days): int
    {
        return in_array($days, self::PERIODS, true) ? $days : self::PERIOD_DEFAULT;
    }

    /**
     * Vollstaendige Momentaufnahme der Leistungsansicht.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function snapshot(int $days = self::PERIOD_DEFAULT, array $filters = []): array
    {
        $days = $this->period($days);
        $tenants = $this->pipeline->tenants($filters);

        // Der Schluessel traegt die Portalauswahl: ohne sie saehe ein
        // Redakteur mit zwei Portalen den Cache des Netzwerks.
        $ids = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->sort()->values()->implode(',');
        $key = 'content:performance:dashboard:'.$days.':'.md5($ids);

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->build($tenants, $days));
    }

    /**
     * Sortierte und geblaetterte Artikeltabelle.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(
        int $days = self::PERIOD_DEFAULT,
        array $filters = [],
        string $sort = self::SORT_DEFAULT,
        string $direction = self::DIRECTION_DESC,
        int $page = 1,
    ): array {
        $snapshot = $this->snapshot($days, $filters);
        $rows = $this->sortRows($snapshot['articles'], $sort, $direction);

        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * self::PAGE_SIZE;

        return [
            'rows' => array_slice($rows, $offset, self::PAGE_SIZE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($offset + self::PAGE_SIZE, $total),
            'sort' => in_array($sort, self::SORTS, true) ? $sort : self::SORT_DEFAULT,
            'direction' => $direction === self::DIRECTION_ASC ? self::DIRECTION_ASC : self::DIRECTION_DESC,
        ];
    }

    /**
     * Erstrichtung einer Spalte: Zahlen absteigend, Text aufsteigend,
     * Position aufsteigend (Platz 1 ist der beste).
     */
    public static function initialDirection(string $sort): string
    {
        return in_array($sort, ['titel', 'portal', 'status', 'position'], true)
            ? self::DIRECTION_ASC
            : self::DIRECTION_DESC;
    }

    /**
     * Die vollstaendige Tabelle als CSV — Semikolon und BOM, damit Excel die
     * Datei ohne Importdialog richtig oeffnet.
     *
     * @param  array<string, mixed>  $filters
     */
    public function csv(
        int $days = self::PERIOD_DEFAULT,
        array $filters = [],
        string $sort = self::SORT_DEFAULT,
        string $direction = self::DIRECTION_DESC,
    ): string {
        $snapshot = $this->snapshot($days, $filters);
        $rows = $this->sortRows($snapshot['articles'], $sort, $direction);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            __('Titel'),
            __('Portal'),
            __('Status'),
            __('Region'),
            __('Cluster'),
            __('Impressionen'),
            __('Klicks'),
            __('CTR'),
            __('Position'),
            __('Ertrag (USD)'),
            __('Datenstand'),
        ], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['title'],
                $row['portal'],
                $row['status_label'],
                $row['region'],
                $row['cluster'] ?? '',
                $row['impressions'],
                $row['clicks'],
                $row['ctr'] === null ? '' : $this->decimal($row['ctr'] * 100, 2),
                $row['position'] === null ? '' : $this->decimal($row['position'], 1),
                $row['revenue_usd'] === null ? '' : $this->decimal($row['revenue_usd'], 2),
                $row['date'] ?? '',
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function build(Collection $tenants, int $days): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subDays($days - 1);
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $articles = [];
        $previous = [];
        $refresh = [];
        $revenueByTenant = [];
        $unreadable = [];
        $asOf = null;

        foreach ($tenants as $tenant) {
            $data = $this->tenantData($tenant, $from, $today, $previousFrom, $previousTo);

            if ($data === null) {
                $unreadable[] = (string) $tenant->name;

                continue;
            }

            $articles = array_merge($articles, $data['articles']);
            $previous = array_merge($previous, $data['previous']);
            $refresh = array_merge($refresh, $data['refresh']);

            $revenueByTenant[(int) $tenant->getKey()] = $data['revenue_usd'];

            if ($data['as_of'] !== null && ($asOf === null || $data['as_of'] > $asOf)) {
                $asOf = $data['as_of'];
            }
        }

        $totals = $this->totals($articles);
        $previousTotals = $this->totals($previous);

        usort($refresh, fn (array $a, array $b): int => ($b['marked_at'] ?? '') <=> ($a['marked_at'] ?? ''));

        $ranked = $this->sortRows($articles, 'klicks', self::DIRECTION_DESC);

        return [
            'days' => $days,
            'generated_at' => CarbonImmutable::now(),
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'as_of' => $asOf,
            'portals' => $tenants->count(),
            'unavailable' => $unreadable,
            'totals' => $totals,
            'previous' => $previousTotals,
            'change' => $this->change($totals, $previousTotals),
            'articles' => $articles,
            'clusters' => $this->groupClusters($articles),
            'regions' => $this->groupRegions($articles),
            'scopes' => $this->groupScopes($articles),
            'costs' => $this->costs($tenants, $from, $today, $revenueByTenant),
            'refresh_candidates' => $refresh,
            'top' => array_slice($ranked, 0, self::RANK_LIMIT),
            'flop' => array_slice(array_reverse($ranked), 0, self::RANK_LIMIT),
        ];
    }

    /**
     * Ein Portal. Rueckgabe null, wenn die Tenant-Datenbank die
     * Pipeline-Tabellen nicht hat — auf Staging der Normalfall.
     *
     * @return array<string, mixed>|null
     */
    private function tenantData(
        Tenant $tenant,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $previousFrom,
        CarbonImmutable $previousTo,
    ): ?array {
        try {
            return $tenant->run(function () use ($tenant, $from, $to, $previousFrom, $previousTo): array {
                $articles = $this->rows($tenant, $from, $to);
                $previous = $this->rows($tenant, $previousFrom, $previousTo);

                $revenue = null;

                foreach ($articles as $row) {
                    if ($row['revenue_usd'] !== null) {
                        $revenue = round((float) ($revenue ?? 0.0) + (float) $row['revenue_usd'], 4);
                    }
                }

                $dates = array_filter(array_column($articles, 'date'));

                return [
                    'articles' => $articles,
                    'previous' => $previous,
                    'refresh' => $this->refreshCandidates($tenant),
                    'revenue_usd' => $revenue,
                    'as_of' => $dates === [] ? null : max($dates),
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Content-Leistung: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Juengste Metrikzeile je Artikel im Fenster, angereichert um Entwurf,
     * Region und Cluster.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $connection = (new ArticleMetric)->getConnection();

        $latest = $connection->table('article_metrics')
            ->selectRaw('article_id, MAX(`date`) as max_date')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('article_id');

        // Bewusst der Query-Builder: die Zeilen tragen Spalten aus vier
        // Tabellen und sind kein ArticleMetric mehr.
        $records = $connection->table('article_metrics')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('latest.article_id', '=', 'article_metrics.article_id')
                    ->on('latest.max_date', '=', 'article_metrics.date');
            })
            ->join('article_drafts', 'article_drafts.id', '=', 'article_metrics.article_draft_id')
            ->leftJoin('topic_candidates', 'topic_candidates.id', '=', 'article_drafts.topic_candidate_id')
            ->leftJoin('keyword_clusters', 'keyword_clusters.id', '=', 'topic_candidates.cluster_id')
            ->orderByDesc('article_metrics.impressions')
            ->limit(self::PER_TENANT_LIMIT)
            ->get([
                'article_metrics.article_id',
                'article_metrics.article_draft_id',
                'article_metrics.date',
                'article_metrics.impressions',
                'article_metrics.clicks',
                'article_metrics.ctr',
                'article_metrics.position',
                'article_metrics.adsense_revenue_usd',
                'article_drafts.title',
                'article_drafts.status',
                'article_drafts.withdrawn_at',
                'article_drafts.region_scope',
                'article_drafts.region_code',
                'article_drafts.published_at',
                'article_drafts.needs_refresh',
                'topic_candidates.cluster_id',
                'keyword_clusters.name as cluster_name',
            ]);

        $rows = [];

        foreach ($records as $record) {
            $status = DisplayStatus::fromDraft(
                DraftStatus::tryFrom((string) $record->status) ?? DraftStatus::PUBLISHED,
                $record->withdrawn_at !== null,
            );

            $rows[] = [
                'tenant_id' => (int) $tenant->getKey(),
                'portal' => (string) $tenant->name,
                'article_id' => (int) $record->article_id,
                'draft_id' => (int) $record->article_draft_id,
                'title' => (string) $record->title,
                'status' => $status->value,
                'status_label' => $status->label(),
                'scope' => (string) ($record->region_scope ?: 'national'),
                'region' => $this->pipeline->regionLabel(
                    $record->region_scope === null ? null : (string) $record->region_scope,
                    $record->region_code === null ? null : (string) $record->region_code,
                ),
                'cluster_id' => $record->cluster_id === null ? null : (int) $record->cluster_id,
                'cluster' => $record->cluster_name === null ? null : (string) $record->cluster_name,
                'impressions' => (int) $record->impressions,
                'clicks' => (int) $record->clicks,
                'ctr' => $record->ctr === null ? null : (float) $record->ctr,
                'position' => $record->position === null ? null : (float) $record->position,
                'revenue_usd' => $record->adsense_revenue_usd === null ? null : (float) $record->adsense_revenue_usd,
                'needs_refresh' => (bool) $record->needs_refresh,
                'date' => $record->date === null ? null : CarbonImmutable::parse((string) $record->date)->toDateString(),
                'published_at' => $record->published_at === null
                    ? null
                    : CarbonImmutable::parse((string) $record->published_at)->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * Artikel, die der Metrik-Collector zur Auffrischung vorgemerkt hat
     * (#23/#24), mit dem Grund im Klartext.
     *
     * @return array<int, array<string, mixed>>
     */
    private function refreshCandidates(Tenant $tenant): array
    {
        $drafts = ArticleDraft::query()
            ->select(['id', 'article_id', 'title', 'needs_refresh_at', 'refresh_reason_json', 'published_at'])
            ->where('needs_refresh', true)
            ->orderByDesc('needs_refresh_at')
            ->limit(self::REFRESH_LIMIT)
            ->get();

        $candidates = [];

        foreach ($drafts as $draft) {
            $reason = (array) ($draft->refresh_reason_json ?? []);

            $candidates[] = [
                'tenant_id' => (int) $tenant->getKey(),
                'portal' => (string) $tenant->name,
                'draft_id' => (int) $draft->getKey(),
                'article_id' => $draft->article_id === null ? null : (int) $draft->article_id,
                'title' => (string) $draft->title,
                'marked_at' => $draft->needs_refresh_at === null
                    ? null
                    : CarbonImmutable::parse($draft->needs_refresh_at)->toDateTimeString(),
                'reasons' => $this->reasonLabels((array) ($reason['reasons'] ?? [])),
                'compared_with' => $reason['compared_with'] ?? null,
            ];
        }

        return $candidates;
    }

    /**
     * @param  array<int, mixed>  $reasons
     * @return array<int, string>
     */
    private function reasonLabels(array $reasons): array
    {
        $labels = [];

        foreach ($reasons as $reason) {
            $reason = (array) $reason;
            $type = (string) ($reason['type'] ?? '');

            $labels[] = match ($type) {
                'position_drop' => __('Position von :from auf :to gefallen', [
                    'from' => $this->decimal((float) ($reason['from'] ?? 0), 1),
                    'to' => $this->decimal((float) ($reason['to'] ?? 0), 1),
                ]),
                'ctr_drop' => __('CTR von :from % auf :to % gefallen', [
                    'from' => $this->decimal(((float) ($reason['from'] ?? 0)) * 100, 2),
                    'to' => $this->decimal(((float) ($reason['to'] ?? 0)) * 100, 2),
                ]),
                default => __('Unbekannter Grund'),
            };
        }

        return $labels;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $impressions = 0;
        $clicks = 0;
        $positionWeight = 0.0;
        $positionImpressions = 0;
        $revenue = null;

        foreach ($rows as $row) {
            $impressions += (int) $row['impressions'];
            $clicks += (int) $row['clicks'];

            if ($row['position'] !== null && (int) $row['impressions'] > 0) {
                $positionWeight += (float) $row['position'] * (int) $row['impressions'];
                $positionImpressions += (int) $row['impressions'];
            }

            if ($row['revenue_usd'] !== null) {
                $revenue = round((float) ($revenue ?? 0.0) + (float) $row['revenue_usd'], 4);
            }
        }

        return [
            'articles' => count($rows),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : null,
            // Nach Impressionen gewichtet: der ungewichtete Mittelwert
            // liesse einen Artikel mit drei Impressionen so schwer wiegen
            // wie einen mit dreitausend.
            'position' => $positionImpressions > 0 ? round($positionWeight / $positionImpressions, 2) : null,
            'revenue_usd' => $revenue,
        ];
    }

    /**
     * Veraenderung gegen die Vorperiode in Prozent. Ohne Vergleichswert
     * bleibt der Eintrag null — eine Kachel ohne Pfeil ist ehrlicher als
     * "+100 %" gegen null.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, ?float>
     */
    private function change(array $current, array $previous): array
    {
        $change = [];

        foreach (['impressions', 'clicks', 'ctr', 'position', 'revenue_usd'] as $key) {
            $before = $previous[$key] ?? null;
            $now = $current[$key] ?? null;

            $change[$key] = ($before === null || $now === null || (float) $before === 0.0)
                ? null
                : round((((float) $now - (float) $before) / abs((float) $before)) * 100, 1);
        }

        return $change;
    }

    /**
     * Klicks je Themencluster, absteigend.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupClusters(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            // Artikel ohne Cluster bekommen eine eigene Gruppe statt zu
            // verschwinden — sonst summieren sich die Balken nicht auf die
            // Kachelzahl und niemand versteht die Differenz.
            $key = $row['cluster_id'] === null ? 'none' : 'c'.$row['cluster_id'];
            $label = $row['cluster'] ?? __('Ohne Cluster');

            $groups[$key] = $this->addTo($groups[$key] ?? ['label' => $label], $row);
        }

        return $this->byClicks($groups);
    }

    /**
     * Klicks je Region.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupRegions(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = $row['scope'].':'.$row['region'];
            $groups[$key] = $this->addTo(
                $groups[$key] ?? ['label' => $row['region'], 'scope' => $row['scope']],
                $row,
            );
        }

        return $this->byClicks($groups);
    }

    /**
     * Klicks je Region-Scope — bundesweit, Bundesland, Stadt. Das ist die
     * Frage des Diagramms: lohnt sich der Regionalaufwand ueberhaupt?
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupScopes(array $rows): array
    {
        $labels = [
            'national' => __('Bundesweit'),
            'state' => __('Bundesland'),
            'city' => __('Stadt'),
        ];

        $groups = [];

        foreach ($rows as $row) {
            $scope = (string) $row['scope'];
            $groups[$scope] = $this->addTo(
                $groups[$scope] ?? ['label' => $labels[$scope] ?? $scope, 'scope' => $scope],
                $row,
            );
        }

        return $this->byClicks($groups);
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function addTo(array $group, array $row): array
    {
        $group['articles'] = ($group['articles'] ?? 0) + 1;
        $group['clicks'] = ($group['clicks'] ?? 0) + (int) $row['clicks'];
        $group['impressions'] = ($group['impressions'] ?? 0) + (int) $row['impressions'];

        if ($row['revenue_usd'] !== null) {
            $group['revenue_usd'] = round((float) ($group['revenue_usd'] ?? 0.0) + (float) $row['revenue_usd'], 4);
        } else {
            $group['revenue_usd'] ??= null;
        }

        return $group;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function byClicks(array $groups): array
    {
        $list = array_values($groups);

        foreach ($list as $index => $group) {
            $list[$index]['clicks_per_article'] = $group['articles'] > 0
                ? round($group['clicks'] / $group['articles'], 2)
                : 0.0;
        }

        usort($list, fn (array $a, array $b): int => [$b['clicks'], $a['label']] <=> [$a['clicks'], $b['label']]);

        return $list;
    }

    /**
     * Kosten gegen Ertrag je Portal. Kosten aus `llm_usage_logs` (Central),
     * Ertrag aus den AdSense-Spalten der Metrikzeilen. Beides in USD —
     * `cost_usd` und `adsense_revenue_usd` sind in derselben Waehrung
     * gefuehrt, es wird nichts umgerechnet.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @param  array<int, ?float>  $revenueByTenant
     * @return array<int, array<string, mixed>>
     */
    private function costs(Collection $tenants, CarbonImmutable $from, CarbonImmutable $to, array $revenueByTenant): array
    {
        $ids = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->all();

        if ($ids === []) {
            return [];
        }

        $costs = LlmUsageLog::query()
            ->whereIn('tenant_id', $ids)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(cost_usd) as cost_usd, SUM(requests) as requests')
            ->get()
            ->keyBy('tenant_id');

        $rows = [];

        foreach ($tenants as $tenant) {
            $id = (int) $tenant->getKey();
            $cost = round((float) ($costs[$id]->cost_usd ?? 0.0), 4);
            $revenue = $revenueByTenant[$id] ?? null;

            $rows[] = [
                'tenant_id' => $id,
                'portal' => (string) $tenant->name,
                'cost_usd' => $cost,
                'requests' => (int) ($costs[$id]->requests ?? 0),
                'revenue_usd' => $revenue,
                // null heisst "nicht gemessen", nicht "kein Ertrag" — ohne
                // AdSense-Zuordnung waere jede Deckungsaussage erfunden.
                'coverage' => ($revenue === null || $cost <= 0.0) ? null : round($revenue / $cost, 3),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['cost_usd'], $a['portal']] <=> [$a['cost_usd'], $b['portal']]);

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort, string $direction): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : self::SORT_DEFAULT;
        $factor = $direction === self::DIRECTION_ASC ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($sort, $factor): int {
            $result = match ($sort) {
                'titel' => $factor * $this->compareText((string) $a['title'], (string) $b['title']),
                'portal' => $factor * $this->compareText((string) $a['portal'], (string) $b['portal']),
                'status' => $factor * $this->compareText((string) $a['status_label'], (string) $b['status_label']),
                'klicks' => $factor * ($a['clicks'] <=> $b['clicks']),
                // Nullwerte stehen in beiden Richtungen am Ende: eine
                // ungemessene Position ist keine gute und keine schlechte.
                'position' => $this->compareNullsLast($a['position'], $b['position'], $factor),
                'ertrag' => $this->compareNullsLast($a['revenue_usd'], $b['revenue_usd'], $factor),
                default => $factor * ($a['impressions'] <=> $b['impressions']),
            };

            return $result !== 0
                ? $result
                : $this->compareText((string) $a['title'], (string) $b['title']);
        });

        return $rows;
    }

    private function compareNullsLast(mixed $a, mixed $b, int $factor): int
    {
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return 1;
        }

        if ($b === null) {
            return -1;
        }

        return $factor * ($a <=> $b);
    }

    private function compareText(string $a, string $b): int
    {
        return strnatcasecmp($a, $b);
    }

    private function decimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '');
    }
}
