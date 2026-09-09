<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Models\ArticleDraft;
use App\Content\Models\ArticleMetric;
use App\Content\Models\ArticleMetricRaw;
use App\Content\Providers\AdSenseClient;
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
 * Grundlage sind die Search-Console-Rohzeilen, die der Gap-Connector (#9)
 * ohnehin taeglich in article_metrics_raw ablegt — ein zweiter Abruf derselben
 * Zahlen waere Verschwendung und braeuchte ein zweites Kontingent. Der Job
 * verdichtet das juengste Fenster je Ratgeberseite zu genau einer Zeile in
 * article_metrics; `date` ist das Fensterende, `window_days` die Fensterlaenge.
 *
 * Ertragsdaten kommen, wenn freigeschaltet, aus der AdSense Management API.
 * Fehlt die Zuordnung, bleiben `pageviews` und `adsense_revenue_usd` null.
 * Null heisst "nicht gemessen" und nicht "null Euro" — deshalb faellt der Job
 * bei fehlender AdSense-Anbindung auch nicht aus, sondern schreibt weiter.
 *
 * Drei weitere Dinge passieren hier:
 *
 *  - Snapshot-Flags: die erste Zeile eines Artikels, die 7, 30 bzw. 90 Tage
 *    nach der Veroeffentlichung liegt, wird als d7/d30/d90 markiert. Damit hat
 *    die Performance-Ansicht (#25) feste Vergleichspunkte, ohne jedes Mal ein
 *    Datum ausrechnen zu muessen.
 *  - Refresh-Erkennung: verliert ein Artikel ueber 14 Tage mindestens fuenf
 *    Plaetze oder bricht seine CTR um 40 Prozent ein, wird der Entwurf mit
 *    needs_refresh markiert. Das ist die Eingangsgroesse des Refresh-Loops
 *    (#24); dieser Job aktualisiert selbst nichts.
 *  - Aufraeumen: Zeilen jenseits der Aufbewahrungsfrist werden geloescht.
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

    public function handle(AdSenseClient $adsense): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $adsense): void {
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

            foreach ($this->draftsByPath(array_keys($pages)) as $path => $draft) {
                $this->store($draft, $windowEnd, $pages[$path], $revenue[$path] ?? null);
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
     * Die Position wird ueber die Impressionen gewichtet — genau wie im
     * Gap-Connector, sonst kippt eine Suchanfrage mit drei Impressionen auf
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
     * Entwuerfe zu den gefundenen Pfaden. Der Pfad eines Ratgebers ist
     * '/ratgeber/<slug>'; Seiten ohne veroeffentlichten Entwurf (alte
     * Blogartikel unter demselben Praefix) fallen heraus.
     *
     * @param  array<int, string>  $paths
     * @return array<string, ArticleDraft>
     */
    private function draftsByPath(array $paths): array
    {
        $prefix = rtrim((string) config('content.sources.gsc_gap.article_path_prefix', '/ratgeber/'), '/').'/';
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

        // Nach einer Aktualisierung (#24) tragen mehrere Fassungen denselben
        // Slug. Aufsteigend sortiert gewinnt beim Zuweisen die juengste — die
        // Fassung, die tatsaechlich live steht.
        $drafts = ArticleDraft::query()
            ->whereNotNull('article_id')
            ->whereNotNull('published_at')
            ->whereIn('slug', array_keys($slugs))
            ->orderBy('id')
            ->get();

        $byPath = [];

        foreach ($drafts as $draft) {
            $path = $slugs[(string) $draft->slug] ?? null;

            if ($path !== null) {
                $byPath[$path] = $draft;
            }
        }

        return $byPath;
    }

    /**
     * Eine Zeile schreiben und daraus Snapshot-Flags und Refresh-Bedarf
     * ableiten.
     *
     * @param  array{impressions: int, clicks: int, ctr: float, position: ?float, window_days: int}  $metrics
     * @param  array{pageviews: int, earnings: float, page: string}|null  $revenue
     */
    private function store(ArticleDraft $draft, CarbonImmutable $windowEnd, array $metrics, ?array $revenue): void
    {
        $metric = ArticleMetric::query()->updateOrCreate(
            [
                'article_id' => (int) $draft->article_id,
                'date' => $windowEnd->toDateString(),
            ],
            [
                'article_draft_id' => (int) $draft->getKey(),
                'window_days' => $metrics['window_days'],
                'impressions' => $metrics['impressions'],
                'clicks' => $metrics['clicks'],
                'ctr' => $metrics['ctr'],
                'position' => $metrics['position'],
                'pageviews' => $revenue === null ? null : $revenue['pageviews'],
                'adsense_revenue_usd' => $revenue === null ? null : $revenue['earnings'],
            ],
        );

        $this->markSnapshots($draft, $metric, $windowEnd);
        $this->checkRefresh($draft, $metric, $windowEnd);
    }

    /**
     * Erste Zeile ab 7, 30 und 90 Tagen nach der Veroeffentlichung markieren.
     * Ist der Snapshot fuer den Artikel schon gesetzt, bleibt es dabei.
     */
    private function markSnapshots(ArticleDraft $draft, ArticleMetric $metric, CarbonImmutable $windowEnd): void
    {
        $publishedAt = $draft->published_at;

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
                ->forArticle((int) $draft->article_id)
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

    /**
     * Abrutschende Artikel markieren (#24).
     *
     * Verglichen wird mit der Zeile, die dem Stichtag vor 14 Tagen am
     * naechsten liegt. Zwei Ausloeser, jeder fuer sich ausreichend: ein
     * Positionsverlust von mindestens fuenf Plaetzen oder ein CTR-Einbruch um
     * mindestens 40 Prozent. Unterhalb einer Mindestzahl an Impressionen wird
     * nicht geprueft — dort ist jede Bewegung Zufall.
     */
    private function checkRefresh(ArticleDraft $draft, ArticleMetric $metric, CarbonImmutable $windowEnd): void
    {
        $config = (array) config('content.metrics.refresh', []);
        $compareDays = max(1, (int) ($config['compare_days'] ?? 14));
        $minImpressions = max(0, (int) ($config['min_impressions'] ?? 100));

        if ((int) $metric->impressions < $minImpressions) {
            return;
        }

        $before = ArticleMetric::query()
            ->forArticle((int) $draft->article_id)
            ->where('date', '<=', $windowEnd->subDays($compareDays)->toDateString())
            ->orderByDesc('date')
            ->first();

        if ($before === null || (int) $before->impressions < $minImpressions) {
            return;
        }

        $reasons = [];

        $positionDrop = $config['position_drop'] ?? 5.0;

        if ($before->position !== null && $metric->position !== null
            && ((float) $metric->position - (float) $before->position) >= (float) $positionDrop) {
            $reasons[] = [
                'type' => 'position_drop',
                'from' => round((float) $before->position, 2),
                'to' => round((float) $metric->position, 2),
            ];
        }

        $ctrDrop = (float) ($config['ctr_drop_ratio'] ?? 0.4);

        if ((float) $before->ctr > 0.0
            && (1.0 - ((float) $metric->ctr / (float) $before->ctr)) >= $ctrDrop) {
            $reasons[] = [
                'type' => 'ctr_drop',
                'from' => round((float) $before->ctr, 4),
                'to' => round((float) $metric->ctr, 4),
            ];
        }

        if ($reasons === []) {
            return;
        }

        // Die Marken des letzten Refresh-Laufs bleiben stehen (#93): auf
        // `last_attempt_at` haengt die Sperrfrist des RefreshSelector. Wuerde
        // der Eintrag hier ueberschrieben, liefe ein Artikel, an dem ein Lauf
        // nichts zu aendern fand, am naechsten Tag erneut an.
        $previous = array_intersect_key(
            (array) ($draft->refresh_reason_json ?? []),
            array_flip(['last_attempt_at', 'last_attempt_result', 'refreshed_by_draft_id']),
        );

        $draft->forceFill([
            'needs_refresh' => true,
            'needs_refresh_at' => now(),
            'refresh_reason_json' => $previous + [
                'compared_with' => CarbonImmutable::parse($before->date)->toDateString(),
                'window_end' => $windowEnd->toDateString(),
                'reasons' => $reasons,
            ],
        ])->save();

        Log::info('Metrik-Collector: Artikel zur Auffrischung vorgemerkt.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => (int) $draft->getKey(),
            'reasons' => array_column($reasons, 'type'),
        ]);
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
