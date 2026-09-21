<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\Topic;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Kostenansicht des Ratgebersystems (#16, design/guide-dashboard.md §7.3).
 *
 * Alle Betraege kommen ausschliesslich aus llm_usage_logs (operation mit
 * Praefix "guide."), keine Live-Abfrage bei einem Anbieter. Tage und Monat
 * laufen in guide.timezone; created_at wird dafuer stundenweise gelesen und
 * in PHP dem Berliner Tag zugeordnet. 60 s gecacht je Portalauswahl.
 */
class CostReport
{
    public const DAYS = 30;

    private const CACHE_SECONDS = 60;

    private const TOP_TOPICS = 25;

    /**
     * Arbeitsschritt je Vorlage; Grundlage der Stapel im Tagesdiagramm.
     */
    public const KINDS = [
        'research' => ['guide.freshness_probe', 'guide.deep_research', 'guide.assign_facts'],
        'writing' => ['guide.propose_outline', 'guide.write_section', 'guide.update_section', 'guide.fix_section', 'guide.short_answer', 'guide.faq', 'guide.meta', 'guide.change_summary'],
        'quality' => ['guide.quality_rubric'],
    ];

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    public function report(Collection $tenants): array
    {
        $key = 'guide:costs:'.md5($tenants->keys()->implode(','));

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->build($tenants));
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'research' => __('Recherche'),
            'writing' => __('Schreiben'),
            'quality' => __('Qualitätsprüfung'),
            default => __('Sonstiges'),
        };
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function build(Collection $tenants): array
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $appTimezone = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);
        $today = $now->copy()->startOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $from = $today->copy()->subDays(self::DAYS - 1)->min($monthStart);
        $ids = $tenants->keys()->all();

        // Stundenwerte in App-Zeit, dann dem Berliner Tag zugeordnet.
        $hours = $this->logs($ids)
            ->where('created_at', '>=', $from->copy()->setTimezone($appTimezone))
            ->selectRaw('substr(created_at, 1, 13) as hour, operation, sum(cost_usd) as cost, sum(search_count) as searches, count(*) as calls')
            ->groupBy('hour', 'operation')
            ->toBase()
            ->get();

        $days = [];

        for ($day = $today->copy()->subDays(self::DAYS - 1); $day->lte($today); $day->addDay()) {
            $days[$day->toDateString()] = ['research' => 0.0, 'writing' => 0.0, 'quality' => 0.0, 'other' => 0.0, 'searches' => 0];
        }

        $totals = ['today' => 0.0, 'month' => 0.0, 'searches_today' => 0, 'searches_month' => 0, 'calls_month' => 0];

        foreach ($hours as $row) {
            $date = Carbon::parse($row->hour.':00:00', $appTimezone)->setTimezone($timezone);
            $dayKey = $date->toDateString();
            $cost = (float) $row->cost;
            $kind = $this->kind((string) $row->operation);

            if (isset($days[$dayKey])) {
                $days[$dayKey][$kind] += $cost;
                $days[$dayKey]['searches'] += (int) $row->searches;
            }

            if ($date->gte($monthStart)) {
                $totals['month'] += $cost;
                $totals['searches_month'] += (int) $row->searches;
                $totals['calls_month'] += (int) $row->calls;
            }

            if ($date->gte($today)) {
                $totals['today'] += $cost;
                $totals['searches_today'] += (int) $row->searches;
            }
        }

        $elapsed = max(1, (int) $monthStart->diffInDays($now) + 1);
        $monthStartApp = $monthStart->copy()->setTimezone($appTimezone);
        $byTenant = $this->byTenant($tenants, $monthStartApp, $totals['month']);

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'totals' => [
                ...$totals,
                // Lineare Hochrechnung, als solche beschriftet (§7.3).
                'forecast' => $totals['month'] / $elapsed * $now->daysInMonth,
                'budget_day' => (float) config('guide.budget.daily_usd_total', 0.0),
                'budget_month' => (float) config('guide.budget.daily_usd_total', 0.0) * $now->daysInMonth,
                'runs_month' => (int) array_sum(array_column($byTenant, 'runs')),
            ],
            'days' => $days,
            'tenants' => $byTenant,
            'topics' => $this->byTopic($tenants, $monthStartApp),
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    private function byTenant(Collection $tenants, Carbon $since, float $monthTotal): array
    {
        return $this->logs($tenants->keys()->all())
            ->where('created_at', '>=', $since)
            ->selectRaw('tenant_id, sum(cost_usd) as cost, sum(search_count) as searches, count(*) as calls, count(distinct reference_id) as runs')
            ->groupBy('tenant_id')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'tenant_id' => (int) $row->tenant_id,
                'name' => (string) ($tenants->get((int) $row->tenant_id)?->name ?? $row->tenant_id),
                'cost' => (float) $row->cost,
                'searches' => (int) $row->searches,
                'calls' => (int) $row->calls,
                'runs' => (int) $row->runs,
                'share' => $monthTotal > 0 ? (float) $row->cost / $monthTotal : 0.0,
            ])
            ->sortByDesc('cost')
            ->values()
            ->all();
    }

    /**
     * Teuerste Themen des Monats; die Namen kommen aus der Tenant-DB, die
     * Betraege nur aus llm_usage_logs.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    private function byTopic(Collection $tenants, Carbon $since): array
    {
        $rows = $this->logs($tenants->keys()->all())
            ->where('created_at', '>=', $since)
            ->whereNotNull('guide_topic_id')
            ->selectRaw('tenant_id, guide_topic_id, sum(cost_usd) as cost, sum(search_count) as searches, count(distinct reference_id) as runs')
            ->groupBy('tenant_id', 'guide_topic_id')
            ->orderByDesc('cost')
            ->limit(self::TOP_TOPICS)
            ->toBase()
            ->get();

        $names = [];

        foreach ($rows->groupBy('tenant_id') as $tenantId => $group) {
            $tenant = $tenants->get((int) $tenantId);

            if ($tenant === null) {
                continue;
            }

            try {
                $names[(int) $tenantId] = $tenant->run(fn (): array => Topic::query()
                    ->whereKey($group->pluck('guide_topic_id')->all())
                    ->pluck('question', 'id')
                    ->all());
            } catch (Throwable) {
                $names[(int) $tenantId] = [];
            }
        }

        return $rows->map(fn (object $row): array => [
            'tenant_id' => (int) $row->tenant_id,
            'tenant_name' => (string) ($tenants->get((int) $row->tenant_id)?->name ?? ''),
            'topic_key' => TopicDirectory::key((int) $row->tenant_id, (int) $row->guide_topic_id),
            'question' => (string) ($names[(int) $row->tenant_id][(int) $row->guide_topic_id] ?? __('Thema :id', ['id' => $row->guide_topic_id])),
            'cost' => (float) $row->cost,
            'searches' => (int) $row->searches,
            'runs' => (int) $row->runs,
        ])->values()->all();
    }

    /**
     * @param  list<int>  $tenantIds
     * @return Builder<LlmUsageLog>
     */
    private function logs(array $tenantIds): Builder
    {
        return LlmUsageLog::query()
            ->where('operation', 'like', BudgetGuard::OPERATION_PREFIX.'%')
            ->whereIn('tenant_id', $tenantIds);
    }

    private function kind(string $operation): string
    {
        foreach (self::KINDS as $kind => $operations) {
            if (in_array($operation, $operations, true)) {
                return $kind;
            }
        }

        return 'other';
    }
}
