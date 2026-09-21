<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Events\ResearchCompleted;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Models\TopicRun;
use App\Guide\Publishing\GuidePublisher;
use App\Guide\Research\ResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Tiefenrecherche (#8): laeuft fuer create-Laeufe direkt aus queued und fuer
 * update-Laeufe nach einer Probe mit Befund (researching). Baut das
 * Fakten-Set, setzt den Lauf auf writing und meldet ResearchCompleted; den
 * Schreib-Job haengt #10 daran.
 *
 * Den Modus bestimmt die Aenderungserkennung (#9): Ohne geaenderten Fakt
 * endet ein update-Lauf als unchanged, sonst traegt er den Modus aus dem
 * ChangeSet (changed_section_ids_json schreibt der ResearchService).
 *
 * Hat das Thema danach keinen einzigen belegten Fakt, endet der Lauf mit
 * failed — ohne Fakten darf nichts geschrieben werden.
 *
 * `continue = false` (guide:research) laesst den Lauf auf researching stehen
 * und meldet nichts weiter.
 */
class DeepResearchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $runId,
        public bool $continue = true,
    ) {
        $this->onQueue((string) config('guide.queues.research', 'guide-research'));
    }

    public function handle(ResearchService $research, GuidePublisher $publisher): void
    {
        $this->withRun(function (TopicRun $run) use ($research, $publisher): void {
            if (! $this->transition($run, RunStatus::RESEARCHING, ['started_at' => $run->started_at ?? Carbon::now()])) {
                return;
            }

            $facts = $research->deepResearch($run->topic, $run);

            if ($facts->currentFactCount === 0) {
                $this->failRun($run, new RuntimeException('Tiefenrecherche ohne belegten Fakt (siehe research_json.rejected).'));

                return;
            }

            if (! $this->continue) {
                return;
            }

            $mode = $facts->changes !== null && $run->mode->canTransitionTo($facts->changes->mode)
                ? $facts->changes->mode
                : $run->mode;

            if ($mode === RunMode::UNCHANGED) {
                $research->markUnchanged($run->topic);
                // "Zuletzt geprueft" im Artikel, nie lastmod (#12).
                $publisher->markChecked($run->topic);
                $this->transition($run, RunStatus::UNCHANGED, ['mode' => $mode, 'finished_at' => Carbon::now()]);

                return;
            }

            if ($this->transition($run, RunStatus::WRITING, ['mode' => $mode])) {
                ResearchCompleted::dispatch($this->tenantId, (int) $run->getKey(), $mode, $facts->factsChanged);
            }
        });
    }
}
