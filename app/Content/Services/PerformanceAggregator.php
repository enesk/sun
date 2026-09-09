<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleMetric;
use App\Content\Models\KeywordCluster;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Lernschleife: was hat sich bei diesem Mandanten bisher gelohnt? (#23)
 *
 * Aggregiert die Tageszeilen aus article_metrics je Themencluster,
 * Region-Scope und Branche zu einer Rangliste nach Klicks je Artikel. Das
 * Ergebnis steht in tenant_content_settings.cluster_performance_json und geht
 * als Teilscore `performance` ins Themen-Scoring (#12) ein.
 *
 * Bewusst ohne SQL-View: eine View je Tenant-DB waere ueber 20 Mandanten
 * hinweg Migrationsballast fuer eine Abfrage, die einmal die Woche laeuft.
 * Der Query-Builder mit Cache reicht.
 *
 * Massgeblich ist je Artikel die juengste Metrikzeile im Fenster, nicht die
 * Summe aller Zeilen: eine Zeile traegt bereits die Klicks eines
 * 28-Tage-Fensters der Search Console: Aufsummieren wuerde denselben Klick
 * 28-mal zaehlen. Damit ist "Klicks je Artikel" ueber alle Gruppen hinweg
 * dieselbe Groesse — Klicks eines Artikels in seinen letzten 28 Tagen.
 */
final class PerformanceAggregator
{
    /**
     * Aggregation des aktuellen Mandanten. Laeuft im Tenant-Kontext.
     *
     * @return array<string, mixed> wie es in cluster_performance_json steht
     */
    public function aggregate(Tenant $tenant, bool $force = false): array
    {
        // Der Cache-Schluessel traegt den Mandanten, auch wenn der Dateicache
        // ohnehin mandantengetrennt liegt: mit Redis waere er es nicht.
        $key = 'content:performance:'.$tenant->getTenantKey();
        $minutes = max(1, (int) $this->option('cache_minutes', 360));

        if ($force) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes($minutes), fn (): array => $this->build($tenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Tenant $tenant): array
    {
        $windowDays = max(7, (int) $this->option('window_days', 90));
        $articles = $this->articles($windowDays);

        $branch = BranchResolver::resolve($tenant);

        $result = [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'window_days' => $windowDays,
            'articles' => count($articles),
            'branch' => [
                'key' => $branch,
                'label' => $branch === null ? null : BranchResolver::label($branch),
            ],
            'clusters' => [],
            'regions' => [],
            'baseline' => ['clicks_per_article' => 0.0],
        ];

        if ($articles === []) {
            return $result;
        }

        $clicks = array_map(static fn (array $article): float => (float) $article['clicks'], $articles);
        $result['baseline']['clicks_per_article'] = round(array_sum($clicks) / count($clicks), 2);

        $result['clusters'] = $this->rank($this->group($articles, 'cluster'), $this->clusterNames());
        $result['regions'] = $this->rank($this->group($articles, 'region'), []);

        // Die Branche ist je Mandant konstant; sie steht als Kennzahl daneben,
        // damit der spaetere Quervergleich ueber die Portale (#25) dieselbe
        // Zahl benutzt und nicht neu rechnet.
        $result['branch']['clicks_per_article'] = $result['baseline']['clicks_per_article'];
        $result['branch']['articles'] = count($articles);

        return $result;
    }

    /**
     * Juengste Metrikzeile je Artikel im Fenster, mit Cluster und Region des
     * zugehoerigen Entwurfs.
     *
     * @return array<int, array{article_id: int, cluster_id: ?int, region_scope: string, region_code: ?string, clicks: int, impressions: int, position: ?float, revenue: ?float}>
     */
    private function articles(int $windowDays): array
    {
        $from = CarbonImmutable::today()->subDays($windowDays)->toDateString();
        $maxPublished = CarbonImmutable::today()
            ->subDays(max(0, (int) $this->option('min_age_days', 14)))
            ->toDateTimeString();

        $latest = (new ArticleMetric)->getConnection()
            ->table('article_metrics')
            ->selectRaw('article_id, MAX(`date`) as max_date')
            ->where('date', '>=', $from)
            ->groupBy('article_id');

        // Bewusst der Query-Builder und nicht das Modell: die Zeilen tragen
        // Spalten aus drei Tabellen und sind keine ArticleMetric mehr.
        $rows = (new ArticleMetric)->getConnection()
            ->table('article_metrics')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('latest.article_id', '=', 'article_metrics.article_id')
                    ->on('latest.max_date', '=', 'article_metrics.date');
            })
            ->join('article_drafts', 'article_drafts.id', '=', 'article_metrics.article_draft_id')
            ->leftJoin('topic_candidates', 'topic_candidates.id', '=', 'article_drafts.topic_candidate_id')
            ->whereNotNull('article_drafts.published_at')
            ->where('article_drafts.published_at', '<=', $maxPublished)
            ->get([
                'article_metrics.article_id',
                'article_metrics.clicks',
                'article_metrics.impressions',
                'article_metrics.position',
                'article_metrics.adsense_revenue_usd',
                'article_drafts.region_scope',
                'article_drafts.region_code',
                'topic_candidates.cluster_id',
            ]);

        $articles = [];

        foreach ($rows as $row) {
            $articles[] = [
                'article_id' => (int) $row->article_id,
                'cluster_id' => $row->cluster_id === null ? null : (int) $row->cluster_id,
                'region_scope' => (string) ($row->region_scope ?: 'national'),
                'region_code' => $row->region_code === null ? null : (string) $row->region_code,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => $row->position === null ? null : (float) $row->position,
                'revenue' => $row->adsense_revenue_usd === null ? null : (float) $row->adsense_revenue_usd,
            ];
        }

        return $articles;
    }

    /**
     * @param  array<int, array<string, mixed>>  $articles
     * @return array<string, array<string, mixed>>
     */
    private function group(array $articles, string $dimension): array
    {
        $groups = [];

        foreach ($articles as $article) {
            [$key, $meta] = $dimension === 'cluster'
                ? $this->clusterKey($article)
                : $this->regionKey($article);

            if ($key === null) {
                continue;
            }

            $group = $groups[$key] ?? $meta + [
                'articles' => 0,
                'clicks' => 0,
                'impressions' => 0,
                'revenue_usd' => null,
            ];

            $group['articles']++;
            $group['clicks'] += (int) $article['clicks'];
            $group['impressions'] += (int) $article['impressions'];

            if ($article['revenue'] !== null) {
                $group['revenue_usd'] = round((float) ($group['revenue_usd'] ?? 0.0) + (float) $article['revenue'], 4);
            }

            $groups[$key] = $group;
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function clusterKey(array $article): array
    {
        $id = $article['cluster_id'];

        // Artikel ohne Cluster tragen nichts zur Cluster-Rangliste bei; sie
        // stecken weiter in der Grundlinie.
        return $id === null ? [null, []] : ['c'.$id, ['cluster_id' => (int) $id, 'name' => null]];
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function regionKey(array $article): array
    {
        $scope = (string) $article['region_scope'];
        $code = $article['region_code'];

        return [
            $scope.':'.($code ?? '-'),
            ['scope' => $scope, 'code' => $code],
        ];
    }

    /**
     * Rangliste nach Klicks je Artikel. Der Score ist der Anteil am besten
     * Wert (0..100) und damit direkt der Teilscore, den der TopicScorer
     * einsetzt. Gruppen unterhalb der Mindestgroesse bekommen keinen Score —
     * zwei Artikel sind kein Beleg.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<int, string>  $clusterNames
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $groups, array $clusterNames): array
    {
        $minArticles = max(1, (int) $this->option('min_articles', 3));
        $ranked = [];

        foreach ($groups as $group) {
            $group['clicks_per_article'] = $group['articles'] > 0
                ? round($group['clicks'] / $group['articles'], 2)
                : 0.0;

            if (isset($group['cluster_id'])) {
                $group['name'] = $clusterNames[(int) $group['cluster_id']] ?? null;
            }

            $ranked[] = $group;
        }

        $best = 0.0;

        foreach ($ranked as $group) {
            if ($group['articles'] >= $minArticles) {
                $best = max($best, (float) $group['clicks_per_article']);
            }
        }

        foreach ($ranked as $index => $group) {
            $qualifies = $group['articles'] >= $minArticles && $best > 0.0;

            $ranked[$index]['score'] = $qualifies
                ? round(min(100.0, (float) $group['clicks_per_article'] / $best * 100.0), 2)
                : null;
        }

        usort($ranked, static fn (array $a, array $b): int => $b['clicks_per_article'] <=> $a['clicks_per_article']);

        foreach ($ranked as $index => $group) {
            $ranked[$index]['rank'] = $index + 1;
        }

        return $ranked;
    }

    /**
     * @return array<int, string>
     */
    private function clusterNames(): array
    {
        return KeywordCluster::query()
            ->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)
            ->all();
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.metrics.learning.{$key}", $default);
    }
}
