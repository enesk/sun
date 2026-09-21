<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Models\ArticleMetric;
use App\Content\Models\ArticleMetricRaw;
use App\Content\Providers\AdSenseClient;
use App\Content\Services\SearchConsoleRawFetcher;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tagesmetriken je veroeffentlichtem Ratgeber (#23).
 *
 * Grundlage sind die Search-Console-Rohzeilen in article_metrics_raw. Bis
 * zum Rueckbau der Themenfindung (#23) legte sie der Gap-Connector ab, seitdem
 * holt der Job sie zu Beginn selbst (SearchConsoleRawFetcher). Der Job
 * verdichtet das juengste Fenster je Ratgeberseite zu genau einer Zeile in
 * article_metrics; `date` ist das Fensterende, `window_days` die Fensterlaenge.
 *
 * Ertragsdaten kommen, wenn freigeschaltet, aus der AdSense Management API.
 * Fehlt die Zuordnung, bleiben `pageviews` und `adsense_revenue_usd` null.
 * Null heisst "nicht gemessen" und nicht "null Euro" — deshalb faellt der Job
 * bei fehlender AdSense-Anbindung auch nicht aus, sondern schreibt weiter.
 *
 * Zwei weitere Dinge passieren hier:
 *
 *  - Snapshot-Flags: die erste Zeile eines Artikels, die 7, 30 bzw. 90 Tage
 *    nach der Veroeffentlichung liegt, wird als d7/d30/d90 markiert. Damit hat
 *    die Performance-Ansicht (#25) feste Vergleichspunkte, ohne jedes Mal ein
 *    Datum ausrechnen zu muessen.
 *  - Aufraeumen: Zeilen jenseits der Aufbewahrungsfrist werden geloescht.
 *
 * Die Zeilen haengen seit #34 nur noch an posts.id. Die Refresh-Markierung
 * der alten Pipeline (needs_refresh am Entwurf) ist mit deren Tabellen
 * entfallen; Aktualisierungen plant das Ratgebersystem selbst.
 *
 * Der Job ist wiederaufsetzbar: er schreibt je Artikel und Datum per upsert,
 * ein zweiter Lauf am selben Tag aendert nichts.
 */
class CollectArticleMetricsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $tenantId,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.metrics', 'content-metrics'));
    }

    public function uniqueId(): string
    {
        return "content-metrics-collect:{$this->tenantId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(AdSenseClient $adsense, SearchConsoleRawFetcher $rawFetcher): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $adsense, $rawFetcher): void {
            $rawFetcher->fetch($this->tenantId);

            $window = ArticleMetricRaw::query()->articlesOnly()->max('window_end');

            if ($window === null) {
                Log::info('Metrik-Collector: keine Search-Console-Rohdaten vorhanden.', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            $windowEnd = CarbonImmutable::parse((string) $window);
            $pages = $this->pageMetrics($windowEnd);

            if ($pages === []) {
                return;
            }

            $revenue = $this->revenue($tenant, $adsense, $windowEnd);
            $written = 0;

            foreach ($this->postsByPath(array_keys($pages)) as $path => $post) {
                $this->store($post, $windowEnd, $pages[$path], $revenue[$path] ?? null);
                $written++;
            }

            $this->prune();

            Log::info('Metrik-Collector: Tageswerte geschrieben.', [
                'tenant_id' => $this->tenantId,
                'window_end' => $windowEnd->toDateString(),
                'articles' => $written,
                'adsense' => $revenue === [] ? 'ohne' : 'mit',
            ]);
        });
    }

    /**
     * Rohzeilen des Fensters je Ratgeberseite verdichten.
     *
     * Die Position wird ueber die Impressionen gewichtet, sonst kippt eine Suchanfrage mit drei Impressionen auf
     * Position 90 den Schnitt.
     *
     * @return array<string, array{impressions: int, clicks: int, ctr: float, position: ?float, window_days: int}>
     */
    private function pageMetrics(CarbonImmutable $windowEnd): array
    {
        $pages = [];

        ArticleMetricRaw::query()
            ->forWindow($windowEnd)
            ->articlesOnly()
            ->select(['id', 'page_path', 'window_days', 'impressions', 'clicks', 'position'])
            ->chunkById(2000, function ($rows) use (&$pages): void {
                foreach ($rows as $row) {
                    $path = rtrim((string) $row->page_path, '/');

                    $bucket = $pages[$path] ?? [
                        'impressions' => 0,
                        'clicks' => 0,
                        'position_weighted' => 0.0,
                        'window_days' => (int) $row->window_days,
                    ];

                    $bucket['impressions'] += (int) $row->impressions;
                    $bucket['clicks'] += (int) $row->clicks;
                    $bucket['position_weighted'] += (float) ($row->position ?? 0) * (int) $row->impressions;

                    $pages[$path] = $bucket;
                }
            });

        foreach ($pages as $path => $bucket) {
            $impressions = (int) $bucket['impressions'];

            $pages[$path] = [
                'impressions' => $impressions,
                'clicks' => (int) $bucket['clicks'],
                'ctr' => $impressions > 0 ? round($bucket['clicks'] / $impressions, 4) : 0.0,
                'position' => $impressions > 0 ? round($bucket['position_weighted'] / $impressions, 2) : null,
                'window_days' => (int) $bucket['window_days'],
            ];
        }

        return $pages;
    }

    /**
     * Ertrag je Pfad. Ein Fehler der AdSense-Schnittstelle kostet den Ertrag,
     * nicht den Lauf.
     *
     * @return array<string, array{pageviews: int, earnings: float, page: string}>
     */
    private function revenue(Tenant $tenant, AdSenseClient $adsense, CarbonImmutable $windowEnd): array
    {
        if (! $adsense->isConfigured()) {
            return [];
        }

        $days = max(1, (int) config('content.providers.search_console.lookback_days', 28));

        try {
            $rows = $adsense->pageReport(
                $windowEnd->subDays($days - 1),
                $windowEnd,
                trim((string) $tenant->domain) ?: null,
            );
        } catch (\Throwable $exception) {
            Log::warning('Metrik-Collector: AdSense nicht abrufbar, Ertragswerte bleiben leer.', [
                'tenant_id' => $this->tenantId,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }

        $normalized = [];

        foreach ($rows as $path => $row) {
            $normalized[rtrim((string) $path, '/')] = $row;
        }

        return $normalized;
    }

    /**
     * Veroeffentlichte Beitraege zu den gefundenen Pfaden. Der Pfad eines
     * Ratgebers ist '/ratgeber/<slug>'; seit #34 zaehlen alle Beitraege unter
     * dem Praefix — Ratgeber-Themen wie Altartikel —, nicht nur die aus der
     * alten Pipeline.
     *
     * @param  array<int, string>  $paths
     * @return array<string, Post>
     */
    private function postsByPath(array $paths): array
    {
        $prefix = rtrim((string) config('content.metrics.article_path_prefix', '/ratgeber/'), '/').'/';
        $slugs = [];

        foreach ($paths as $path) {
            if (! str_starts_with($path.'/', $prefix)) {
                continue;
            }

            $slug = trim(substr($path, strlen($prefix)), '/');

            if ($slug !== '' && ! str_contains($slug, '/')) {
                $slugs[$slug] = $path;
            }
        }

        if ($slugs === []) {
            return [];
        }

        $posts = Post::query()
            ->published()
            ->whereIn('slug', array_keys($slugs))
            ->get(['id', 'slug', 'published_at']);

        $byPath = [];

        foreach ($posts as $post) {
            $path = $slugs[(string) $post->slug] ?? null;

            if ($path !== null) {
                $byPath[$path] = $post;
            }
        }

        return $byPath;
    }

    /**
     * Eine Zeile schreiben und daraus die Snapshot-Flags ableiten.
     *
     * @param  array{impressions: int, clicks: int, ctr: float, position: ?float, window_days: int}  $metrics
     * @param  array{pageviews: int, earnings: float, page: string}|null  $revenue
     */
    private function store(Post $post, CarbonImmutable $windowEnd, array $metrics, ?array $revenue): void
    {
        $metric = ArticleMetric::query()->updateOrCreate(
            [
                'article_id' => (int) $post->getKey(),
                'date' => $windowEnd->toDateString(),
            ],
            [
                'window_days' => $metrics['window_days'],
                'impressions' => $metrics['impressions'],
                'clicks' => $metrics['clicks'],
                'ctr' => $metrics['ctr'],
                'position' => $metrics['position'],
                'pageviews' => $revenue === null ? null : $revenue['pageviews'],
                'adsense_revenue_usd' => $revenue === null ? null : $revenue['earnings'],
            ],
        );

        $this->markSnapshots($post, $metric, $windowEnd);
    }

    /**
     * Erste Zeile ab 7, 30 und 90 Tagen nach der Veroeffentlichung markieren.
     * Ist der Snapshot fuer den Artikel schon gesetzt, bleibt es dabei.
     */
    private function markSnapshots(Post $post, ArticleMetric $metric, CarbonImmutable $windowEnd): void
    {
        $publishedAt = $post->published_at;

        if ($publishedAt === null) {
            return;
        }

        $ageDays = CarbonImmutable::parse($publishedAt)->startOfDay()->diffInDays($windowEnd->startOfDay());
        $flags = [];

        foreach ((array) config('content.metrics.snapshots', [7, 30, 90]) as $day) {
            $column = 'is_d'.(int) $day;

            if ($ageDays < (int) $day || ! in_array($column, ['is_d7', 'is_d30', 'is_d90'], true)) {
                continue;
            }

            $exists = ArticleMetric::query()
                ->forArticle((int) $post->getKey())
                ->where($column, true)
                ->where('id', '!=', $metric->getKey())
                ->exists();

            if (! $exists) {
                $flags[$column] = true;
            }
        }

        if ($flags !== []) {
            $metric->forceFill($flags)->save();
        }
    }

    private function prune(): void
    {
        $days = (int) config('content.metrics.retention_days', 1095);

        if ($days <= 0) {
            return;
        }

        ArticleMetric::query()
            ->where('date', '<', CarbonImmutable::today()->subDays($days)->toDateString())
            ->delete();
    }
}
