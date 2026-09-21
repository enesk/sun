<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\RunDisplay;
use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Enums\TopicStatus;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Support\CheckedQuote;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tageslauf-Monitor und Pruef-Queue (#16, design/guide-dashboard.md §3 und §8).
 *
 * Liest je Portal ueber $tenant->run() nur Aggregate (Zaehler je Status,
 * Laeufe von heute, Themen in review) und central aus llm_usage_logs und
 * guide_alerts. Das Ergebnis ist ein flaches Array, 60 s gecacht; der
 * Schluessel traegt die Portal-ids, damit sich verschiedene Auswahlen nicht
 * mischen. Nach einer Entscheidung in der Pruef-Queue verwirft forget() den
 * Stand.
 *
 * Tag = Kalendertag in guide.timezone, wie beim Dispatcher (run_date) und
 * beim BudgetGuard.
 */
class RunOverviewService
{
    public const CACHE_SECONDS = 60;

    private const CACHE_PREFIX = 'guide:overview:';

    /** Laenge der Liste "Heute geaendert" je Portal. */
    private const CHANGED_LIMIT = 20;

    /**
     * Reihenfolge der Segmente im Tagesbalken (§3.2), links nach rechts.
     */
    public const SEGMENTS = ['neu', 'aktualisiert', 'unveraendert', 'pruefung', 'fehlgeschlagen', 'in-arbeit', 'offen'];

    public function __construct(private readonly BudgetGuard $budget) {}

    /**
     * Tagesstand aller uebergebenen Portale.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return array{date: string, generated_at: string, portals: list<array<string, mixed>>, totals: array<string, mixed>, alerts: list<array<string, mixed>>, costs: array<string, mixed>, window: array<string, mixed>}
     */
    public function overview(Collection $tenants): array
    {
        // "day2": Aufbau seit #33 um Pause, Prognose und Ueberfaellige erweitert.
        $key = self::CACHE_PREFIX.'day2:v'.$this->version().':'.md5($tenants->keys()->implode(','));

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->build($tenants));
    }

    /**
     * Laeufe im Status review aller uebergebenen Portale, aelteste zuerst.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    public function reviewQueue(Collection $tenants): array
    {
        $key = self::CACHE_PREFIX.'review:v'.$this->version().':'.md5($tenants->keys()->implode(','));

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $tenants
            ->flatMap(fn (Tenant $tenant): array => $this->read($tenant, fn (): array => $this->reviewRows($tenant)) ?? [])
            ->sortBy('waiting_since')
            ->values()
            ->all());
    }

    /**
     * Verlauf › Laeufe (#33, design/guide-dashboard.md §7.1): Laeufe aller
     * uebergebenen Portale mit run_date im Zeitraum, juengste zuerst.
     * Seitenweise ueber alle Portale: je Portal hoechstens die ersten
     * $page * $perPage Zeilen, dann gemischt und geschnitten.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @param  list<string>  $displays  Filter auf RunDisplay-Werte, leer = alle
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function runs(Collection $tenants, Carbon $from, Carbon $until, array $displays = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $key = self::CACHE_PREFIX.'runs:v'.$this->version().':'.md5(implode('|', [
            $tenants->keys()->implode(','), $from->toDateString(), $until->toDateString(), implode(',', $displays), $page, $perPage,
        ]));

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($tenants, $from, $until, $displays, $page, $perPage): array {
            $limit = $page * $perPage;
            $total = 0;
            $rows = collect();

            foreach ($tenants as $tenant) {
                $result = $this->read($tenant, fn (): array => $this->runRows($tenant, $from, $until, $displays, $limit));

                if ($result !== null) {
                    $total += $result['total'];
                    $rows = $rows->concat($result['rows']);
                }
            }

            return [
                'rows' => $rows->sortByDesc('started_at')->values()->forPage($page, $perPage)->values()->all(),
                'total' => $total,
            ];
        });
    }

    /**
     * Zaehler je Tag und Segment (§3.2) fuer die Kleinbalken im Verlauf,
     * aelteste zuerst. Offen = eingeplant, aber nie gestartet.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{date: string, counts: array<string, int>, total: int, checked: int}>
     */
    public function dailyCounts(Collection $tenants, int $days = 14): array
    {
        $until = $this->today();
        $from = $until->copy()->subDays($days - 1);
        $key = self::CACHE_PREFIX.'daily:v'.$this->version().':'.md5($tenants->keys()->implode(',').'|'.$from->toDateString().'|'.$days);

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($tenants, $from, $days): array {
            $result = [];

            for ($i = 0; $i < $days; $i++) {
                $result[$from->copy()->addDays($i)->toDateString()] = array_fill_keys(self::SEGMENTS, 0);
            }

            foreach ($tenants as $tenant) {
                $rows = $this->read($tenant, fn (): array => TopicRun::query()
                    ->whereDate('run_date', '>=', $from->toDateString())
                    ->selectRaw('run_date, status, mode, count(*) as aggregate')
                    ->groupBy('run_date', 'status', 'mode')
                    ->toBase()
                    ->get()
                    ->all()) ?? [];

                foreach ($rows as $row) {
                    $date = Carbon::parse((string) $row->run_date)->toDateString();
                    $status = RunStatus::tryFrom((string) $row->status);

                    if (! isset($result[$date]) || $status === null) {
                        continue;
                    }

                    $display = RunDisplay::fromRun($status, RunMode::tryFrom((string) $row->mode))->value;
                    $result[$date][$display === RunDisplay::QUEUED->value ? 'offen' : $display] += (int) $row->aggregate;
                }
            }

            return collect($result)
                ->map(fn (array $counts, string $date): array => [
                    'date' => $date,
                    'counts' => $counts,
                    'total' => array_sum($counts),
                    'checked' => $counts['neu'] + $counts['aktualisiert'] + $counts['unveraendert'] + $counts['pruefung'],
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Verwirft alle Staende; die naechste Anzeige liest frisch.
     */
    public function forget(): void
    {
        Cache::forever(self::CACHE_PREFIX.'version', $this->version() + 1);
    }

    public function today(): Carbon
    {
        return Carbon::now($this->timezone())->startOfDay();
    }

    /**
     * Laufzeitfenster aus der Konfiguration (§9.1, schreibgeschuetzt) und die
     * Lage von jetzt darin.
     *
     * @return array{start: string, end: string, phase: string}
     */
    public function window(?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->copy()->setTimezone($this->timezone());
        $start = (string) config('guide.run_window_start', '02:00');
        $end = (string) config('guide.run_window_end', '07:00');

        $phase = match (true) {
            $now->format('H:i') < $start => 'before',
            $now->format('H:i') < $end => 'running',
            default => 'after',
        };

        return ['start' => $start, 'end' => $end, 'phase' => $phase];
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function build(Collection $tenants): array
    {
        $today = $this->today();
        $costs = $this->costsToday($tenants->keys()->all());

        $portals = $tenants
            ->map(function (Tenant $tenant) use ($today, $costs): array {
                $row = $this->read($tenant, fn (): array => $this->portalRow($today)) ?? $this->emptyPortal();
                $tenantCosts = $costs['by_tenant'][(int) $tenant->getKey()] ?? ['cost' => 0.0, 'searches' => 0];
                $runCosts = $costs['by_run'][(int) $tenant->getKey()] ?? [];

                $costByDisplay = [];

                foreach ($row['run_displays'] as $runId => $display) {
                    if (isset($runCosts[$runId])) {
                        $costByDisplay[$display] = ($costByDisplay[$display] ?? 0.0) + $runCosts[$runId];
                    }
                }

                unset($row['run_displays']);

                return [
                    ...$row,
                    'tenant_id' => (int) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'domain' => $tenant->domain,
                    'cost' => $tenantCosts['cost'],
                    'searches' => $tenantCosts['searches'],
                    'cost_by_display' => $costByDisplay,
                    'changed' => array_map(
                        fn (array $item): array => [...$item, 'url' => $this->portalUrl($tenant, $item['path'])],
                        $row['changed'],
                    ),
                ];
            })
            ->values();

        $totals = ['total' => 0, 'checked' => 0, 'due' => 0, 'deferred' => 0, 'review_overdue' => 0, 'outlines_pending' => 0, 'stuck' => 0, 'overdue' => 0, 'done' => 0, 'counts' => array_fill_keys(self::SEGMENTS, 0)];
        $costByDisplay = [];

        foreach ($portals as $portal) {
            foreach (self::SEGMENTS as $segment) {
                $totals['counts'][$segment] += $portal['counts'][$segment];
            }

            foreach (['total', 'checked', 'due', 'deferred', 'review_overdue', 'outlines_pending', 'stuck', 'overdue', 'done'] as $field) {
                $totals[$field] += $portal[$field];
            }

            foreach ($portal['cost_by_display'] as $display => $amount) {
                $costByDisplay[$display] = ($costByDisplay[$display] ?? 0.0) + $amount;
            }
        }

        // Vor dem ersten Lauf (§3.5): Themen importiert, aber kein Portal
        // freigeschaltet (tenant_guide_settings.is_active, DailyOrchestrator).
        $totals['imported_inactive'] = $portals->sum('topics_total') > 0 && $portals->where('is_active', true)->isEmpty();
        $totals['topics_total'] = (int) $portals->sum('topics_total');
        $totals['portals_with_topics'] = $portals->where('topics_total', '>', 0)->count();
        $totals['finished_at'] = $portals->pluck('finished_at')->filter()->max();
        $totals['first_started_at'] = $portals->pluck('first_started_at')->filter()->min();

        return [
            'date' => $today->toDateString(),
            'generated_at' => Carbon::now()->toIso8601String(),
            'window' => $this->window(),
            'portals' => $portals->all(),
            'totals' => $totals,
            'costs' => [
                'today' => $costs['total'],
                'searches' => $costs['searches'],
                'budget' => (float) config('guide.budget.daily_usd_total', 0.0),
                'by_display' => $costByDisplay,
            ],
            'alerts' => $this->alerts($tenants),
            'paused' => $this->pauseState(),
        ];
    }

    /**
     * Globale Pause fuer die Zustandszeile von Heute (§3.1 Punkt 1).
     *
     * @return array{at: string, by: string|null}|null
     */
    private function pauseState(): ?array
    {
        if (! GuideRunState::isPaused()) {
            return null;
        }

        $state = GuideRunState::current();

        return ['at' => (string) $state->paused_at?->toIso8601String(), 'by' => $state->paused_by_name];
    }

    /**
     * Zaehler eines Portals fuer heute. Laeuft im Tenant-Kontext.
     *
     * @return array<string, mixed>
     */
    private function portalRow(Carbon $today): array
    {
        $setting = TenantGuideSetting::query()->first();
        $counts = array_fill_keys([...self::SEGMENTS, RunDisplay::QUEUED->value], 0);
        $displays = [];

        $runs = TopicRun::query()
            ->forDate($today)
            ->get(['id', 'guide_topic_id', 'status', 'mode', 'started_at', 'finished_at', 'updated_at', 'created_at']);

        $stuckBefore = Carbon::now()->subMinutes((int) config('guide.schedule.stuck_after_minutes', 45));
        $stuck = 0;

        foreach ($runs as $run) {
            $display = RunDisplay::fromRun($run->status, $run->mode);
            $counts[$display->value]++;
            $displays[(int) $run->getKey()] = $display->value;

            if ($run->status->isInFlight() && ($run->updated_at ?? $run->created_at)?->lt($stuckBefore)) {
                $stuck++;
            }
        }

        // Der Balken hat kein Segment "Wartet": ein eingeplanter Lauf zaehlt
        // wie ein noch offener (§3.2).
        $counts['offen'] += $counts[RunDisplay::QUEUED->value];
        unset($counts[RunDisplay::QUEUED->value]);

        $open = Topic::query()
            ->due(Carbon::now())
            ->whereDoesntHave('runs', fn ($query) => $query->whereDate('run_date', $today->toDateString()))
            ->count();

        $counts['offen'] += $open;

        $deferred = (int) count((array) (GuideAlert::query()
            ->where('key', GuideAlert::KEY_TOPICS_DEFERRED)
            ->where('tenant_id', tenant()?->getKey())
            ->whereDate('for_date', $today->toDateString())
            ->value('context_json')['topics'] ?? []));

        $counts['offen'] += $deferred;

        $checked = $counts['neu'] + $counts['aktualisiert'] + $counts['unveraendert'] + $counts['pruefung'];

        $topicCounts = Topic::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status');

        return [
            'is_active' => (bool) ($setting?->is_active ?? false),
            'budget' => $setting?->dailyBudgetUsd() ?? (float) config('guide.budget.daily_usd_per_tenant', 0.0),
            // Prognose "voraussichtlich fertig gegen" (§3.1): abgeschlossene
            // Laeufe und fruehester Start von heute.
            'done' => $runs->filter(fn (TopicRun $run): bool => $run->status->isTerminal() || $run->status === RunStatus::REVIEW)->count(),
            'first_started_at' => $runs->min('started_at')?->toIso8601String(),
            'overdue' => Topic::query()->overdue()->count(),
            'counts' => $counts,
            'total' => array_sum($counts),
            'checked' => $checked,
            // Nenner der Quote (#38 G7): faellig laut Tagesauswahl inkl. verschobener.
            'due' => CheckedQuote::due($today->toDateString()),
            'deferred' => $deferred,
            'stuck' => $stuck,
            'review_overdue' => TopicRun::query()
                ->where('status', RunStatus::REVIEW->value)
                ->where('updated_at', '<', Carbon::now()->subDay())
                ->count(),
            'outlines_pending' => (int) ($topicCounts[TopicStatus::OUTLINE_PENDING->value] ?? 0),
            'topics_total' => (int) $topicCounts->sum(),
            'finished_at' => $runs->isNotEmpty() && $runs->every(fn (TopicRun $run): bool => $run->status->isTerminal() || $run->status === RunStatus::REVIEW)
                ? $runs->max('finished_at')?->toIso8601String()
                : null,
            'run_displays' => $displays,
            'changed' => $this->changedToday($today),
        ];
    }

    /**
     * "Heute geaendert": neu erschienene und aktualisierte Artikel mit
     * Changelog-Satz. Laeuft im Tenant-Kontext.
     *
     * @return list<array{topic_id: int, question: string, mode: string, summary: string|null, published_at: string|null, path: string|null}>
     */
    private function changedToday(Carbon $today): array
    {
        return TopicRun::query()
            ->forDate($today)
            ->where('status', RunStatus::PUBLISHED->value)
            ->with(['topic:id,question,article_id', 'topic.article:id,slug'])
            ->orderByDesc('finished_at')
            ->limit(self::CHANGED_LIMIT)
            ->get()
            // Eine inhaltsgleiche Fassung ist keine Aenderung (Publisher: unchanged).
            ->reject(fn (TopicRun $run): bool => ($run->publish_json['action'] ?? null) === 'unchanged')
            ->map(function (TopicRun $run): array {
                /** @var Topic|null $topic */
                $topic = $run->topic;
                $slug = $topic?->article?->getAttribute('slug');

                return [
                    'topic_id' => (int) $run->guide_topic_id,
                    'question' => (string) $topic?->question,
                    'mode' => RunDisplay::fromRun($run->status, $run->mode)->value,
                    'summary' => $run->change_summary,
                    'published_at' => Carbon::make($run->finished_at)?->toIso8601String(),
                    'path' => filled($slug) ? route('guide.show', (string) $slug, false) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Laeufe eines Portals im Zeitraum, juengste zuerst. Laeuft im
     * Tenant-Kontext.
     *
     * @param  list<string>  $displays
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function runRows(Tenant $tenant, Carbon $from, Carbon $until, array $displays, int $limit): array
    {
        $query = TopicRun::query()
            ->whereDate('run_date', '>=', $from->toDateString())
            ->whereDate('run_date', '<=', $until->toDateString());

        if ($displays !== []) {
            $query->where(function ($query) use ($displays): void {
                foreach ($displays as $display) {
                    match ($display) {
                        RunDisplay::QUEUED->value => $query->orWhere('status', RunStatus::QUEUED->value),
                        RunDisplay::IN_PROGRESS->value => $query->orWhereIn('status', [RunStatus::PROBING->value, RunStatus::RESEARCHING->value, RunStatus::WRITING->value, RunStatus::CHECKING->value]),
                        RunDisplay::REVIEW->value => $query->orWhere('status', RunStatus::REVIEW->value),
                        RunDisplay::CREATED->value => $query->orWhere(fn ($q) => $q->where('status', RunStatus::PUBLISHED->value)->where('mode', RunMode::CREATE->value)),
                        RunDisplay::UPDATED->value => $query->orWhere(fn ($q) => $q->where('status', RunStatus::PUBLISHED->value)->where(fn ($m) => $m->where('mode', '!=', RunMode::CREATE->value)->orWhereNull('mode'))),
                        RunDisplay::UNCHANGED->value => $query->orWhere('status', RunStatus::UNCHANGED->value),
                        RunDisplay::FAILED->value => $query->orWhere('status', RunStatus::FAILED->value),
                        default => null,
                    };
                }
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->with('topic:id,question')
            ->orderByRaw('coalesce(started_at, created_at) desc')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (TopicRun $run): array => [
                ...TopicDirectory::runFields($run),
                '__key' => TopicDirectory::key($tenant->getKey(), $run->getKey()),
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_name' => (string) $tenant->name,
                'topic_key' => TopicDirectory::key($tenant->getKey(), $run->guide_topic_id),
                'question' => (string) $run->topic?->question,
                'mode' => $run->mode?->value,
                'started_at' => Carbon::make($run->started_at ?? $run->created_at)?->toIso8601String(),
                'duration_seconds' => $run->started_at !== null && $run->finished_at !== null
                    ? max(0, (int) $run->started_at->diffInSeconds($run->finished_at))
                    : null,
                'cost' => (float) $run->cost_usd,
            ])
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Eintraege der Pruef-Queue eines Portals. Laeuft im Tenant-Kontext.
     *
     * @return list<array<string, mixed>>
     */
    private function reviewRows(Tenant $tenant): array
    {
        return TopicRun::query()
            ->where('status', RunStatus::REVIEW->value)
            ->with('topic:id,question,status,outline_locked_at,outline_json')
            ->get()
            // Freigegeben, aber noch nicht veroeffentlicht: nicht mehr in der Queue.
            ->filter(fn (TopicRun $run): bool => $run->topic !== null && ($run->research_json['review']['decision'] ?? null) !== 'approved')
            ->map(fn (TopicRun $run): array => [
                '__key' => TopicDirectory::key($tenant->getKey(), $run->getKey()),
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_name' => (string) $tenant->name,
                'run_id' => (int) $run->getKey(),
                'topic_id' => (int) $run->guide_topic_id,
                'topic_key' => TopicDirectory::key($tenant->getKey(), $run->guide_topic_id),
                'question' => (string) $run->topic->question,
                'mode' => $run->mode?->value,
                'changed_sections' => count((array) ($run->research_json['writing']['changed_section_ids'] ?? $run->changed_section_ids_json ?? [])),
                // Ohne gesperrte Gliederung wartet der Lauf auf den Editor (§8.1).
                'awaits_outline' => ! $run->topic->isOutlineLocked(),
                'score' => $run->quality_score,
                'cost' => (float) $run->cost_usd,
                'waiting_since' => ($run->updated_at ?? $run->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Kosten von heute aus llm_usage_logs: gesamt, je Portal und je Lauf.
     *
     * @param  list<int>  $tenantIds
     * @return array{total: float, searches: int, by_tenant: array<int, array{cost: float, searches: int}>, by_run: array<int, array<int, float>>}
     */
    private function costsToday(array $tenantIds): array
    {
        [$from, $until] = $this->budget->dayBounds();

        $base = LlmUsageLog::query()
            ->where('operation', 'like', BudgetGuard::OPERATION_PREFIX.'%')
            ->whereBetween('created_at', [$from, $until])
            ->whereIn('tenant_id', $tenantIds);

        $byTenant = (clone $base)
            ->selectRaw('tenant_id, sum(cost_usd) as cost, sum(search_count) as searches')
            ->groupBy('tenant_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->tenant_id => ['cost' => (float) $row->cost, 'searches' => (int) $row->searches]])
            ->all();

        $byRun = [];

        (clone $base)
            ->where('reference_type', LlmCallContext::REFERENCE_RUN)
            ->selectRaw('tenant_id, reference_id, sum(cost_usd) as cost')
            ->groupBy('tenant_id', 'reference_id')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$byRun): void {
                $byRun[(int) $row->tenant_id][(int) $row->reference_id] = (float) $row->cost;
            });

        return [
            'total' => (float) array_sum(array_column($byTenant, 'cost')),
            'searches' => (int) array_sum(array_column($byTenant, 'searches')),
            'by_tenant' => $byTenant,
            'by_run' => $byRun,
        ];
    }

    /**
     * Offene Alarme der Portale und netzwerkweite, schwerste zuerst.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    private function alerts(Collection $tenants): array
    {
        $rank = [GuideAlert::LEVEL_CRITICAL => 0, GuideAlert::LEVEL_WARNING => 1, GuideAlert::LEVEL_INFO => 2];

        return GuideAlert::query()
            ->open()
            ->where(fn ($query) => $query->whereIn('tenant_id', $tenants->keys()->all())->orWhereNull('tenant_id'))
            // Verschobene Themen stehen als Zaehler im Balken, nicht als Alarm.
            ->where('key', '!=', GuideAlert::KEY_TOPICS_DEFERRED)
            ->latest('last_seen_at')
            ->limit(50)
            ->get()
            ->map(fn (GuideAlert $alert): array => [
                'id' => (int) $alert->getKey(),
                'key' => (string) $alert->key,
                'level' => (string) $alert->level,
                'message' => (string) $alert->message,
                'tenant_id' => $alert->tenant_id !== null ? (int) $alert->tenant_id : null,
                'tenant_name' => $alert->tenant_id !== null ? $tenants->get((int) $alert->tenant_id)?->name : null,
                'occurrences' => (int) $alert->occurrences,
                'last_seen_at' => ($alert->last_seen_at ?? $alert->created_at)?->toIso8601String(),
                'for_date' => $alert->for_date?->toDateString(),
            ])
            ->sortBy(fn (array $alert): int => $rank[$alert['level']] ?? 3)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPortal(): array
    {
        return [
            'is_active' => false,
            'budget' => (float) config('guide.budget.daily_usd_per_tenant', 0.0),
            'counts' => array_fill_keys(self::SEGMENTS, 0),
            'total' => 0,
            'checked' => 0,
            'due' => 0,
            'deferred' => 0,
            'stuck' => 0,
            'review_overdue' => 0,
            'outlines_pending' => 0,
            'topics_total' => 0,
            'finished_at' => null,
            'done' => 0,
            'first_started_at' => null,
            'overdue' => 0,
            'run_displays' => [],
            'changed' => [],
            'unavailable' => true,
        ];
    }

    private function portalUrl(Tenant $tenant, ?string $path): ?string
    {
        if ($path === null || blank($tenant->domain)) {
            return null;
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$tenant->domain}{$path}";
    }

    /**
     * Ein Portal ohne Guide-Tabellen wird uebersprungen statt den Monitor zu
     * sprengen (wie TopicDirectory).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function read(Tenant $tenant, callable $callback): mixed
    {
        try {
            return $tenant->run($callback);
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Monitor: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function version(): int
    {
        return (int) Cache::get(self::CACHE_PREFIX.'version', 1);
    }

    private function timezone(): string
    {
        return (string) config('guide.timezone', 'Europe/Berlin');
    }
}
