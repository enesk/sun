<?php

declare(strict_types=1);

namespace App\Content\Orchestration;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\ContentAlert;
use App\Content\Models\Central\ContentDailyReport;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\Central\ProviderState;
use App\Content\Models\TenantContentSetting;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Der Tagesbericht der Content-Pipeline (#22).
 *
 * Beantwortet in einer Mail um 20:00, was der Tag gebracht hat: je Portal
 * veroeffentlicht gegen Ziel, die Artikel mit Score und Kosten, die
 * fehlgeschlagenen Slots mit Grund, Kosten fuer Tag und Monat, Provider-Lage
 * und offene Alarme.
 *
 * Der fertige Bericht wird central abgelegt (`content_daily_reports`), damit
 * die Uebersicht des Panels ihn ohne einen zweiten Durchgang durch alle
 * Tenant-Datenbanken zeigen kann. Nicht im Cache: der Bericht entsteht in
 * einem Lauf ueber alle Mandanten, und der Dateicache haengt dabei am
 * mandantenspezifischen storage_path.
 *
 * Der Bericht deckt ausschliesslich die fuer die Content-Pipeline
 * freigeschalteten Portale ab (#102) — ein nicht freigeschaltetes Portal
 * hat kein Tagesziel und wuerde die Zielerfuellung nur verwaessern.
 */
final class DailyReportBuilder
{
    public function __construct(private readonly TenantRollout $rollout) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?CarbonImmutable $date = null): array
    {
        $date = ($date ?? CarbonImmutable::today())->startOfDay();

        $portals = [];
        $published = 0;
        $refreshed = 0;
        $target = 0;
        $failed = 0;

        $costPerTenant = $this->costPerTenant($date);

        foreach ($this->rollout->activeTenantsOn($date) as $tenant) {
            $portal = $this->portal($tenant, $date, $costPerTenant[(int) $tenant->getKey()] ?? 0.0);

            if ($portal === null) {
                continue;
            }

            $portals[] = $portal;
            $published += $portal['published'];
            $refreshed += $portal['refreshed'];
            $target += $portal['target'];
            $failed += count($portal['failed_slots']);
        }

        usort($portals, static fn (array $a, array $b): int => [$a['published'] - $a['target'], $a['name']] <=> [$b['published'] - $b['target'], $b['name']]);

        $report = [
            'date' => $date->toDateString(),
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'totals' => [
                'published' => $published,
                'refreshed' => $refreshed,
                'target' => $target,
                'failed_slots' => $failed,
                'portals' => count($portals),
            ],
            'portals' => $portals,
            'cost' => $this->cost($date),
            'providers' => $this->providers(),
            'alerts' => $this->alerts(),
        ];

        ContentDailyReport::query()->updateOrCreate(
            ['report_date' => $date->toDateString()],
            [
                'report_json' => $report,
                'published' => $published,
                'target' => $target,
                'cost_usd' => $report['cost']['today'],
            ],
        );

        return $report;
    }

    /**
     * Der zuletzt gebaute Bericht fuer die Anzeige im Panel. Ohne Lauf gibt
     * es keinen Bericht — dann bleibt die Flaeche leer statt falsch.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        try {
            $stored = ContentDailyReport::latest();
        } catch (Throwable $exception) {
            // Umgebung ohne die Berichtstabelle: die Uebersicht bleibt
            // benutzbar, die Berichtsflaeche bleibt leer.
            Log::warning('Tagesbericht nicht lesbar.', ['message' => $exception->getMessage()]);

            return null;
        }

        return $stored?->report_json;
    }

    /**
     * Ein Portal. Rueckgabe null, wenn die Pipeline-Tabellen dort fehlen —
     * das ist auf Staging der Normalfall und darf den Bericht nicht
     * abbrechen.
     *
     * @return array<string, mixed>|null
     */
    private function portal(Tenant $tenant, CarbonImmutable $date, float $cost): ?array
    {
        $tenantId = (int) $tenant->getKey();

        try {
            /** @var array{target: int, articles: array<int, array<string, mixed>>, refreshes: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>} $data */
            $data = $tenant->run(function () use ($date): array {
                $settings = TenantContentSetting::query()->first();
                $target = max(1, (int) ($settings?->articles_per_day
                    ?? config('content.targets.articles_per_tenant_per_day', 2)));

                $articles = [];
                $refreshes = [];
                $failed = [];

                foreach (ContentDailyOrchestrator::draftsOfDay($date) as $draft) {
                    if ($draft->status === DraftStatus::PUBLISHED) {
                        // Eine Aktualisierung (#24) traegt dieselbe Adresse und
                        // als `published_at` das Datum der Erstveroeffentlichung.
                        // Sie ist kein Artikel des Tages (#103) und bekommt
                        // deshalb eine eigene Spur statt einer zweiten Zeile
                        // in `articles`.
                        if ($draft->isRefresh()) {
                            $refreshes[] = self::refreshRow($draft);

                            continue;
                        }

                        $articles[] = [
                            'title' => (string) $draft->title,
                            'url' => (string) (($draft->publication_json ?? [])['url'] ?? ''),
                            'score' => $draft->quality_score !== null ? round((float) $draft->quality_score, 1) : null,
                            'cost' => round((float) $draft->generation_cost_usd, 4),
                            'at' => $draft->published_at?->format('H:i'),
                        ];

                        continue;
                    }

                    if ($draft->status === DraftStatus::FAILED) {
                        $failed[] = [
                            'title' => (string) $draft->title,
                            'reason' => self::failureReason($draft),
                        ];
                    }
                }

                return ['target' => $target, 'articles' => $articles, 'refreshes' => $refreshes, 'failed' => $failed];
            });
        } catch (Throwable $exception) {
            Log::warning('Tagesbericht: Portal uebersprungen.', [
                'tenant_id' => $tenantId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $failedSlots = $data['failed'];

        foreach ($this->openAlertsFor($tenantId, $date) as $alert) {
            $failedSlots[] = ['title' => __('Slot :slot', ['slot' => $alert->slot ?? '-']), 'reason' => (string) $alert->message];
        }

        return [
            'id' => $tenantId,
            'name' => (string) $tenant->name,
            'target' => $data['target'],
            'published' => count($data['articles']),
            'refreshed' => count($data['refreshes']),
            'articles' => $data['articles'],
            'refreshes' => $data['refreshes'],
            'failed_slots' => $failedSlots,
            'cost' => round($cost, 4),
        ];
    }

    /**
     * Die Kosten je Portal am Berichtstag (#102). Quelle ist
     * `llm_usage_logs` und nicht die nachgezogene Spalte
     * `article_drafts.generation_cost_usd`: die Spalte kennt nur Entwuerfe,
     * die an diesem Tag entstanden sind, und laesst damit Aktualisierungen
     * aelterer Artikel, Themenfindung und Quellenlaeufe unter den Tisch
     * fallen. Ueber das Nutzungsprotokoll deckt sich die Portalspalte mit
     * der Tagessumme, und Entwuerfe zaehlen unabhaengig davon, ob sie es
     * bis zur Veroeffentlichung geschafft haben.
     *
     * @return array<int, float>
     */
    private function costPerTenant(CarbonImmutable $date): array
    {
        return LlmUsageLog::query()
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->selectRaw('tenant_id, SUM(cost_usd) AS total_usd')
            ->groupBy('tenant_id')
            ->pluck('total_usd', 'tenant_id')
            ->map(static fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContentAlert>
     */
    private function openAlertsFor(int $tenantId, CarbonImmutable $date)
    {
        return ContentAlert::query()
            ->open()
            ->where('tenant_id', $tenantId)
            ->whereDate('for_date', $date->toDateString())
            ->where('key', ContentAlert::KEY_SLOT_EXHAUSTED)
            ->orderBy('slot')
            ->get();
    }

    /**
     * @return array<string, float>
     */
    private function cost(CarbonImmutable $date): array
    {
        $daily = (float) config('content.budget.daily_usd', 35.0);
        $monthly = (float) config('content.budget.monthly_usd', 1050.0);

        $today = (float) LlmUsageLog::query()
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->sum('cost_usd');

        $month = (float) LlmUsageLog::query()
            ->whereBetween('created_at', [$date->startOfMonth(), $date->endOfMonth()])
            ->sum('cost_usd');

        return [
            'today' => round($today, 2),
            'daily_budget' => $daily,
            'today_share' => $daily > 0 ? round($today / $daily, 3) : 0.0,
            'month' => round($month, 2),
            'monthly_budget' => $monthly,
            'month_share' => $monthly > 0 ? round($month / $monthly, 3) : 0.0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providers(): array
    {
        return ProviderState::query()
            ->orderBy('provider')
            ->get()
            ->map(static fn (ProviderState $state): array => [
                'provider' => (string) $state->provider,
                'status' => (string) $state->status,
                'requests_today' => (int) $state->requests_today,
                'last_error' => $state->status === ProviderState::STATUS_OK ? null : $state->last_error,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alerts(): array
    {
        return ContentAlert::query()
            ->open()
            ->orderByRaw("CASE level WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get()
            ->map(static fn (ContentAlert $alert): array => [
                'level' => (string) $alert->level,
                'message' => (string) $alert->message,
                'occurrences' => (int) $alert->occurrences,
                'since' => $alert->created_at?->format('d.m. H:i'),
            ])
            ->all();
    }

    /**
     * Eine Zeile der Spur „Aktualisiert" (#103). Die Uhrzeit kommt aus dem
     * Lauf selbst (`updated_at`, sonst `created_at`) — `published_at` traegt
     * bei einer Kindfassung bewusst das Datum der Erstveroeffentlichung und
     * stuende in der Zeitspalte falsch. Es erscheint stattdessen als
     * `first_published`. Die Adresse aendert sich beim Aktualisieren nicht;
     * fehlt sie an der Kindfassung, gilt die der Elternfassung.
     *
     * @return array<string, mixed>
     */
    private static function refreshRow(ArticleDraft $draft): array
    {
        $url = (string) (($draft->publication_json ?? [])['url'] ?? '');

        $parent = $draft->parentDraft;

        if ($url === '' && $parent instanceof ArticleDraft) {
            $url = (string) (($parent->publication_json ?? [])['url'] ?? '');
        }

        return [
            'title' => (string) $draft->title,
            'url' => $url,
            'cost' => round((float) $draft->generation_cost_usd, 4),
            'at' => ($draft->updated_at ?? $draft->created_at)?->format('H:i'),
            'first_published' => $draft->published_at?->format('d.m.Y'),
        ];
    }

    /**
     * Der Grund, an dem ein Slot gescheitert ist. Der Generator legt ihn
     * unter `generation.failed_reason` ab, das Qualitaetsgate unter
     * `quality.decision`.
     */
    private static function failureReason(ArticleDraft $draft): string
    {
        $report = (array) ($draft->quality_report_json ?? []);
        $generation = (array) ($report['generation'] ?? []);
        $quality = (array) ($report['quality'] ?? []);

        return (string) ($generation['failed_reason']
            ?? $quality['decision']
            ?? __('unbekannt'));
    }
}
