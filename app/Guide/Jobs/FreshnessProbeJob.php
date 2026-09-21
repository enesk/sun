<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
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

/**
 * Freshness-Probe eines update-Laufs (#8): queued -> probing, dann
 *  - keine Aenderung mit ausreichender Sicherheit: Modus und Status
 *    unchanged, Thema bekommt last_checked_at und next_due_at, keine
 *    weiteren LLM-Aufrufe;
 *  - sonst: researching und DeepResearchJob.
 * Hat das Thema noch kein Fakten-Set oder ist guide.force_rewrite gesetzt
 * (#9: alle Abschnitte unabhaengig vom Hash), entfaellt die Probe. Ebenso,
 * wenn ein aktueller Fakt an einer nicht erreichbaren Quelle haengt (#27):
 * Die Probe koennte "unveraendert" melden, dann wuerde nie ersetzt.
 *
 * `continue = false` (guide:research) fuehrt nur die Probe aus.
 */
class FreshnessProbeJob implements ShouldBeUnique, ShouldQueue
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
            if ($run->mode !== RunMode::UPDATE) {
                return;
            }

            // Ohne Fakten-Set gibt es nichts zu vergleichen, mit force_rewrite
            // nichts zu entscheiden, mit kaputter Quelle nichts zu erwarten:
            // gleich zur Tiefenrecherche.
            if ((bool) config('guide.force_rewrite', false)
                || $run->topic->currentFacts()->doesntExist()
                || $run->topic->hasBrokenCurrentSource()) {
                if ($this->transition($run, RunStatus::RESEARCHING, ['started_at' => $run->started_at ?? Carbon::now()]) && $this->continue) {
                    DeepResearchJob::dispatch($this->tenantId, $this->runId);
                }

                return;
            }

            if (! $this->transition($run, RunStatus::PROBING, ['started_at' => $run->started_at ?? Carbon::now()])) {
                return;
            }

            $result = $research->probe($run->topic, $run);

            if ($result->unchanged) {
                $research->markUnchanged($run->topic);
                // "Zuletzt geprueft" im Artikel, nie lastmod (#12).
                $publisher->markChecked($run->topic);
                $this->transition($run, RunStatus::UNCHANGED, [
                    'mode' => RunMode::UNCHANGED,
                    'finished_at' => Carbon::now(),
                ]);

                return;
            }

            if ($this->transition($run, RunStatus::RESEARCHING) && $this->continue) {
                DeepResearchJob::dispatch($this->tenantId, $this->runId);
            }
        });
    }
}
