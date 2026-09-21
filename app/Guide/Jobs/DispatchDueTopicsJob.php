<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Orchestration\DailyOrchestrator;
use App\Guide\Scheduling\DueTopicSelector;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tageslauf eines Tenants (#13, docs/guide-system.md §2): waehlt die
 * faelligen Themen (DueTopicSelector, #9), legt je Thema den Lauf des Tages
 * an und reiht dessen Kette gestaffelt ueber das Laufzeitfenster ein.
 *
 * Staffelung: delay = Fensterlaenge / Anzahl Laeufe * Index, plus ein
 * Zufallsversatz je Tenant (hoechstens ein Abstand bzw.
 * guide.orchestrator.tenant_offset_max_seconds). Das Fenster ist das des
 * Tenants (tenant_guide_settings.run_window_start/end, sonst config); wird
 * vor Fensterbeginn angestossen, beginnt die Staffel am Fensterbeginn, nach
 * Fensterende (manueller Lauf) werden alle Laeufe sofort eingereiht — die
 * Parallelitaetsgrenze (LimitGuideConcurrency) haelt die Rate-Limits dann
 * trotzdem ein.
 *
 * Wiederaufsetzbar: Laeufe des Tages, die noch nicht terminal sind, werden
 * fortgesetzt statt neu angelegt (hoechstens ein Lauf je Thema und Tag,
 * Unique-Index guide_topic_id + run_date). Laeufe in review warten auf das
 * Dashboard und bleiben unberuehrt.
 */
class DispatchDueTopicsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $tenantId,
        public string $date,
        public bool $ignoreWindow = false,
    ) {
        $this->onQueue((string) config('guide.queues.dispatch', 'guide-dispatch'));
    }

    public function uniqueId(): string
    {
        return static::class.":{$this->tenantId}:{$this->date}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(DueTopicSelector $selector, TopicChainFactory $chains): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        // Global pausiert (#33): auch ein bereits eingereihter Tageslauf legt
        // keine Laeufe mehr an.
        if ($tenant === null || GuideRunState::isPaused()) {
            return;
        }

        $tenant->run(function () use ($selector, $chains): void {
            $timezone = (string) config('guide.timezone', 'Europe/Berlin');
            $day = Carbon::parse($this->date, $timezone)->startOfDay();

            // Erst fortsetzen, dann neu waehlen: die Auswahl laesst Themen mit
            // heutigem Lauf ohnehin aus.
            $runs = TopicRun::query()
                ->forDate($day)
                ->whereNotIn('status', [RunStatus::REVIEW->value, RunStatus::PUBLISHED->value, RunStatus::UNCHANGED->value, RunStatus::FAILED->value])
                ->orderBy('id')
                ->get()
                ->all();

            $selection = $selector->select($day);
            $selector->defer($selection);

            foreach ($selection->selected as $topic) {
                $run = $this->runFor($topic, $day);

                if ($run !== null && ! $run->status->isTerminal()) {
                    $runs[] = $run;
                }
            }

            if ($runs === []) {
                return;
            }

            [$startIn, $windowSeconds] = $this->window($day);
            $count = count($runs);
            $spacing = $windowSeconds > 0 ? intdiv($windowSeconds, $count) : 0;
            $offset = $spacing > 0 ? random_int(0, min($spacing, (int) config('guide.orchestrator.tenant_offset_max_seconds', 600))) : 0;
            $dispatched = 0;

            foreach (array_values($runs) as $index => $run) {
                if ($chains->dispatch($this->tenantId, $run, $startIn + $spacing * $index + $offset)) {
                    $dispatched++;
                }
            }

            Log::info('Guide-Tageslauf eingereiht.', [
                'tenant_id' => $this->tenantId,
                'date' => $day->toDateString(),
                'runs' => $count,
                'dispatched' => $dispatched,
                'deferred' => count($selection->deferred),
                'spacing_seconds' => $spacing,
            ]);
        });
    }

    /**
     * Lauf des Tages; ein paralleler Anstoss, der ihn zwischen Abfrage und
     * Anlage angelegt hat, liefert ueber den Unique-Index denselben.
     */
    private function runFor(Topic $topic, Carbon $day): ?TopicRun
    {
        $existing = $topic->runs()->whereDate('run_date', $day->toDateString())->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            /** @var TopicRun */
            return $topic->runs()->create([
                'run_date' => $day->toDateString(),
                'status' => RunStatus::QUEUED,
                'mode' => $topic->article_id !== null ? RunMode::UPDATE : RunMode::CREATE,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $topic->runs()->whereDate('run_date', $day->toDateString())->first();
        }
    }

    /**
     * Sekunden bis zum Beginn der Staffel und deren Laenge.
     *
     * @return array{0: int, 1: int}
     */
    private function window(Carbon $day): array
    {
        if ($this->ignoreWindow) {
            return [0, 0];
        }

        $settings = TenantGuideSetting::current();
        [$start, $end] = DailyOrchestrator::windowFor($day, $settings->run_window_start, $settings->run_window_end);
        $now = Carbon::now($day->getTimezone());

        if ($now->greaterThanOrEqualTo($end)) {
            return [0, 0];
        }

        $from = $now->greaterThan($start) ? $now : $start;

        return [(int) $now->diffInSeconds($from), (int) $from->diffInSeconds($end)];
    }
}
