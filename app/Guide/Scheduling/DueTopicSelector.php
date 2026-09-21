<?php

declare(strict_types=1);

namespace App\Guide\Scheduling;

use App\Guide\Dto\DueSelection;
use App\Guide\Enums\RunMode;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Heute faellige Themen eines Tenants (#9). Erwartet einen initialisierten
 * Tenant-Kontext.
 *
 * Grundmenge: Topic::due() bis Tagesende (guide.timezone), ohne Themen, die
 * heute schon einen Lauf haben. Reihenfolge:
 *   1. Themen ohne Artikel zuerst,
 *   2. priority aufsteigend (1 = hoechste; 0 = ohne Angabe, zuletzt),
 *   3. aeltestes last_checked_at zuerst (nie geprueft vor allen anderen).
 *
 * Neuanlagen sind auf guide.schedule.max_creates_per_tenant_per_day begrenzt
 * (heutige create-Laeufe zaehlen mit). Reicht das verbleibende Tagesbudget
 * (Tenant und gesamt) nicht fuer alle, laufen Themen ohne Artikel und Themen
 * mit priority 1 bis guide.schedule.preferred_priority_max zuerst; der Rest
 * wird mit defer() auf morgen verschoben und fuer den Tagesbericht als
 * guide_alert `topics_deferred` festgehalten.
 *
 * Geschaetzt wird mit guide.estimates: create_usd fuer Themen ohne Artikel,
 * check_usd (Probe) fuer alle anderen; Themen mit nicht erreichbarer Quelle
 * eines aktuellen Fakts (#27) gehen ohne Probe in die Tiefenrecherche und
 * zaehlen mit update_usd. Die harte Grenze bleibt der BudgetGuard.
 */
class DueTopicSelector
{
    public function __construct(
        private readonly BudgetGuard $budget,
    ) {}

    public function select(?DateTimeInterface $date = null): DueSelection
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $day = ($date !== null ? Carbon::instance($date) : Carbon::now())->copy()->setTimezone($timezone);
        $endOfDay = $day->copy()->endOfDay()->setTimezone((string) config('app.timezone', 'UTC'));

        $topics = Topic::query()
            ->due($endOfDay)
            ->whereDoesntHave('runs', fn (Builder $query) => $query->whereDate('run_date', $day->toDateString()))
            ->get()
            ->sort(fn (Topic $a, Topic $b): int => $this->sortKey($a) <=> $this->sortKey($b))
            ->values()
            ->all();

        $deferred = [];
        $candidates = [];
        $createsLeft = $this->createsLeft($day);

        foreach ($topics as $topic) {
            if ($topic->article_id === null) {
                if ($createsLeft <= 0) {
                    $deferred[] = ['topic' => $topic, 'reason' => DueSelection::REASON_MAX_CREATES];

                    continue;
                }

                $createsLeft--;
            }

            $candidates[] = $topic;
        }

        $remaining = $this->remainingUsd();
        $estimated = array_sum(array_map(fn (Topic $topic): float => $this->estimate($topic), $candidates));

        if ($remaining === null || $estimated <= $remaining) {
            return new DueSelection($day, $candidates, $deferred, false, $remaining, $estimated);
        }

        $preferred = array_filter($candidates, fn (Topic $topic): bool => $this->isPreferred($topic));
        $rest = array_filter($candidates, fn (Topic $topic): bool => ! $this->isPreferred($topic));
        $budgetLeft = $remaining;
        $chosen = [];

        foreach ([...$preferred, ...$rest] as $topic) {
            $cost = $this->estimate($topic);

            if ($cost > $budgetLeft) {
                $deferred[] = ['topic' => $topic, 'reason' => DueSelection::REASON_BUDGET];

                continue;
            }

            $budgetLeft -= $cost;
            $chosen[$topic->getKey()] = true;
        }

        $selected = array_values(array_filter($candidates, fn (Topic $topic): bool => isset($chosen[$topic->getKey()])));

        return new DueSelection(
            $day,
            $selected,
            $deferred,
            true,
            $remaining,
            array_sum(array_map(fn (Topic $topic): float => $this->estimate($topic), $selected)),
        );
    }

    /**
     * Verschiebt die zurueckgestellten Themen auf morgen und haelt sie fuer den
     * Tagesbericht fest (ein Alarm je Tenant und Tag).
     */
    public function defer(DueSelection $selection): void
    {
        if ($selection->deferred === []) {
            return;
        }

        $tomorrow = $selection->date->copy()->addDay()->startOfDay()->setTimezone((string) config('app.timezone', 'UTC'));
        $rows = [];

        foreach ($selection->deferred as ['topic' => $topic, 'reason' => $reason]) {
            $topic->forceFill(['next_due_at' => $tomorrow])->save();

            $rows[] = [
                'guide_topic_id' => (int) $topic->getKey(),
                'question' => (string) $topic->question,
                'priority' => (int) $topic->priority,
                'has_article' => $topic->article_id !== null,
                'reason' => $reason,
            ];
        }

        $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
        $date = $selection->date->toDateString();
        $count = count($rows);

        GuideAlert::raise(
            "topics_deferred:{$tenantId}:{$date}",
            GuideAlert::KEY_TOPICS_DEFERRED,
            GuideAlert::LEVEL_INFO,
            "{$count} faellige Ratgeber-Themen auf morgen verschoben"
                .($selection->budgetScarce ? ' (Tagesbudget knapp).' : ' (Neuanlage-Grenze erreicht).'),
            [
                'tenant_id' => $tenantId,
                'for_date' => $date,
                'context_json' => [
                    'topics' => $rows,
                    'remaining_usd' => $selection->remainingUsd,
                    'estimated_usd' => round($selection->estimatedUsd, 4),
                ],
            ],
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function sortKey(Topic $topic): array
    {
        $priority = (int) $topic->priority;

        return [
            $topic->article_id === null ? 0 : 1,
            $priority > 0 ? $priority : PHP_INT_MAX,
            $topic->last_checked_at?->getTimestamp() ?? PHP_INT_MIN,
            (int) $topic->getKey(),
        ];
    }

    private function isPreferred(Topic $topic): bool
    {
        $priority = (int) $topic->priority;

        return $topic->article_id === null
            || ($priority >= 1 && $priority <= (int) config('guide.schedule.preferred_priority_max', 2));
    }

    private function estimate(Topic $topic): float
    {
        return match (true) {
            $topic->article_id === null => (float) config('guide.estimates.create_usd', 0.78),
            $topic->hasBrokenCurrentSource() => (float) config('guide.estimates.update_usd', 0.61),
            default => (float) config('guide.estimates.check_usd', 0.14),
        };
    }

    private function createsLeft(Carbon $day): int
    {
        $max = (int) config('guide.schedule.max_creates_per_tenant_per_day', 5);

        if ($max <= 0) {
            return PHP_INT_MAX;
        }

        $started = TopicRun::query()
            ->forDate($day)
            ->where('mode', RunMode::CREATE->value)
            ->count();

        return max(0, $max - $started);
    }

    /**
     * Verbleibendes Tagesbudget: das kleinere aus Tenant- und Gesamtbudget;
     * null, wenn beide abgeschaltet sind (0).
     */
    private function remainingUsd(): ?float
    {
        $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
        $remaining = [];

        $total = (float) config('guide.budget.daily_usd_total', 0.0);

        if ($total > 0.0) {
            $remaining[] = $total - $this->budget->spentToday();
        }

        $perTenant = TenantGuideSetting::current()->dailyBudgetUsd();

        if ($tenantId !== null && $perTenant > 0.0) {
            $remaining[] = $perTenant - $this->budget->spentToday($tenantId);
        }

        return $remaining === [] ? null : max(0.0, min($remaining));
    }
}
