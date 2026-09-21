<?php

declare(strict_types=1);

namespace App\Guide\Orchestration;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Central\GuideDailyReport;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\TopicRun;
use App\Guide\Support\CheckedQuote;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Tagesbericht des Ratgebersystems (#13): je Tenant geprueft / unveraendert /
 * aktualisiert / neu / review / fehlgeschlagen / verschoben / offen, Kosten
 * und die Alarme des Tages. Gespeichert central in guide_daily_reports (fuer
 * das Dashboard), versandt als App\Guide\Mail\DailyGuideReport.
 *
 * Zaehlung je Lauf des Tages (run_date): geprueft = alle Laeufe;
 * aktualisiert/neu = published mit Modus update bzw. create; offen = noch in
 * der Kette. Verschoben = Themen aus dem Alarm topics_deferred des Tages.
 * Kosten aus llm_usage_logs (operation guide.*) im Berliner Kalendertag.
 *
 * Quote (#38 G7, CheckedQuote): due = faellige Themen des Tages inklusive
 * verschobener, checked_due = neu + aktualisiert + unveraendert + Pruefung,
 * checked_ratio = checked_due / due (null bei due = 0), je Portal und gesamt.
 *
 * Vor dem Bericht prueft der Builder je Tenant die Fehlerquote
 * (RunWatchdog::checkFailureRate), damit der Alarm im Bericht steht.
 */
final class DailyReportBuilder
{
    public const COUNTERS = ['checked', 'unchanged', 'updated', 'created', 'review', 'failed', 'deferred', 'open', 'due', 'checked_due'];

    public function __construct(
        private readonly RunWatchdog $watchdog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $day): array
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $day = $day->copy()->setTimezone($timezone)->startOfDay();
        $date = $day->toDateString();
        $appTimezone = (string) config('app.timezone', 'UTC');
        $bounds = [$day->copy()->setTimezone($appTimezone), $day->copy()->endOfDay()->setTimezone($appTimezone)];

        $costs = LlmUsageLog::query()
            ->where('operation', 'like', BudgetGuard::OPERATION_PREFIX.'%')
            ->whereBetween('created_at', $bounds)
            ->selectRaw('tenant_id, sum(cost_usd) as cost')
            ->groupBy('tenant_id')
            ->pluck('cost', 'tenant_id')
            ->map(static fn ($cost): float => (float) $cost)
            ->all();

        $deferred = GuideAlert::query()
            ->where('key', GuideAlert::KEY_TOPICS_DEFERRED)
            ->whereDate('for_date', $date)
            ->get()
            ->mapWithKeys(static fn (GuideAlert $alert): array => [(int) $alert->tenant_id => count((array) ($alert->context_json['topics'] ?? []))])
            ->all();

        $portals = [];
        $totals = array_fill_keys(self::COUNTERS, 0);

        /** @var Tenant $tenant */
        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $tenantId = (int) $tenant->getKey();

            try {
                $portal = $tenant->run(function () use ($tenantId, $date): ?array {
                    $counts = [...$this->counts($date), 'due' => CheckedQuote::due($date)];

                    if (! TenantGuideSetting::current()->is_active && $counts['checked'] === 0 && $counts['due'] === 0) {
                        return null;
                    }

                    $this->watchdog->checkFailureRate($tenantId, $date);

                    return $counts;
                });
            } catch (Throwable $exception) {
                report($exception);
                $portal = ['error' => mb_substr($exception->getMessage(), 0, 300)];
            }

            if ($portal === null) {
                continue;
            }

            $portal = [
                ...array_fill_keys(self::COUNTERS, 0),
                ...$portal,
                'tenant_id' => $tenantId,
                'name' => (string) ($tenant->name ?? $tenant->domain ?? $tenantId),
                'deferred' => $deferred[$tenantId] ?? 0,
                'cost_usd' => round($costs[$tenantId] ?? 0.0, 4),
            ];
            $portal['checked_ratio'] = CheckedQuote::ratio((int) $portal['checked_due'], (int) $portal['due']);

            foreach (self::COUNTERS as $counter) {
                $totals[$counter] += (int) $portal[$counter];
            }

            $portals[] = $portal;
        }

        $totalCost = round(array_sum($costs), 4);
        $limit = (float) config('guide.budget.daily_usd_total', 0.0);

        $report = [
            'date' => $date,
            'generated_at' => Carbon::now($timezone)->toIso8601String(),
            'totals' => [
                ...$totals,
                'checked_ratio' => CheckedQuote::ratio($totals['checked_due'], $totals['due']),
                'portals' => count($portals),
                'cost_usd' => $totalCost,
            ],
            'budget' => [
                'daily_usd_total' => $limit,
                'share' => $limit > 0 ? round($totalCost / $limit, 4) : null,
            ],
            'portals' => $portals,
            'alerts' => $this->alerts($bounds),
        ];

        GuideDailyReport::query()->updateOrCreate(
            ['report_date' => $date],
            ['report_json' => $report, 'checked' => $totals['checked'], 'failed' => $totals['failed'], 'cost_usd' => $totalCost],
        );

        return $report;
    }

    /**
     * Zaehler eines Tenants (Tenant-Kontext).
     *
     * @return array<string, int>
     */
    private function counts(string $date): array
    {
        $counts = array_fill_keys(self::COUNTERS, 0);

        $rows = TopicRun::query()
            ->whereDate('run_date', $date)
            ->selectRaw('status, mode, count(*) as aggregate')
            ->groupBy('status', 'mode')
            ->get();

        foreach ($rows as $row) {
            $number = (int) $row->getAttribute('aggregate');
            $status = $row->status;
            $counts['checked'] += $number;

            $key = match (true) {
                $status === RunStatus::UNCHANGED => 'unchanged',
                $status === RunStatus::PUBLISHED && $row->mode === RunMode::CREATE => 'created',
                $status === RunStatus::PUBLISHED => 'updated',
                $status === RunStatus::REVIEW => 'review',
                $status === RunStatus::FAILED => 'failed',
                default => 'open',
            };

            $counts[$key] += $number;
        }

        // Geprueft im Sinne der Quote (§3.2/§11.2): ohne fehlgeschlagene und offene.
        $counts['checked_due'] = $counts['created'] + $counts['updated'] + $counts['unchanged'] + $counts['review'];

        return $counts;
    }

    /**
     * Alarme, die am Tag ausgeloest oder erneut gemeldet wurden; kritische zuerst.
     *
     * @param  array{0: Carbon, 1: Carbon}  $bounds
     * @return array<int, array<string, mixed>>
     */
    private function alerts(array $bounds): array
    {
        $order = [GuideAlert::LEVEL_CRITICAL => 0, GuideAlert::LEVEL_WARNING => 1, GuideAlert::LEVEL_INFO => 2];
        $names = Tenant::query()->pluck('name', 'id')->all();

        return GuideAlert::query()
            ->whereBetween('last_seen_at', $bounds)
            ->where('key', '!=', GuideAlert::KEY_LEGACY_OVERLAP)
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get()
            ->sortBy(static fn (GuideAlert $alert): int => $order[$alert->level] ?? 3)
            ->map(static fn (GuideAlert $alert): array => [
                'key' => (string) $alert->key,
                'level' => (string) $alert->level,
                'message' => (string) $alert->message,
                'tenant_id' => $alert->tenant_id !== null ? (int) $alert->tenant_id : null,
                'tenant' => $alert->tenant_id !== null ? (string) ($names[$alert->tenant_id] ?? $alert->tenant_id) : null,
                'occurrences' => (int) $alert->occurrences,
                'status' => (string) $alert->status,
            ])
            ->values()
            ->all();
    }
}
