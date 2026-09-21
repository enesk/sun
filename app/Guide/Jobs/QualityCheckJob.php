<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunStatus;
use App\Guide\Events\ArticleApproved;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\TopicRun;
use App\Guide\Quality\QualityGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Qualitaetsgate (#11): prueft jede neue oder aktualisierte Fassung vor der
 * Veroeffentlichung. Fachlogik in App\Guide\Quality\QualityGate; Ergebnis in
 * guide_topic_runs.quality_score und quality_report_json.
 *
 *  - publish: Lauf bleibt `checking`, Event ArticleApproved fuer den
 *    Publisher (#12).
 *  - fix: checking -> writing, FixSectionsJob bessert die genannten
 *    Abschnitte einmal nach; dessen Fassung kommt wieder hierher.
 *  - review: checking -> review (Pruef-Queue im Dashboard, #16).
 *
 * Posts werden hier nie geschrieben: faellt ein Update durch, bleibt die
 * bisher veroeffentlichte Fassung unveraendert online.
 *
 * Budget: Tages-/Tenant-Budget erschoepft -> release() und spaeter pruefen
 * (die Pruefung ist wiederholbar), Lauf-Budget und alle anderen Fehler ->
 * failed (HandlesGuideRun).
 */
class QualityCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $runId,
        public int $versionId,
        public bool $continue = true,
    ) {
        $this->onQueue((string) config('guide.queues.write', 'guide-write'));
    }

    /**
     * Die Fassung aus dem Fix-Durchlauf ist ein eigener Pruefauftrag.
     */
    public function uniqueId(): string
    {
        return static::class.":{$this->tenantId}:{$this->runId}:{$this->versionId}";
    }

    public function handle(QualityGate $gate, BudgetGuard $budget): void
    {
        $this->withRun(function (TopicRun $run) use ($gate, $budget): void {
            if (! $this->transition($run, RunStatus::CHECKING)) {
                return;
            }

            /** @var ArticleVersion|null $version */
            $version = ArticleVersion::query()
                ->whereKey($this->versionId)
                ->where('guide_topic_run_id', $run->getKey())
                ->first();

            if ($version === null) {
                throw new RuntimeException("Fassung {$this->versionId} gehoert nicht zu Lauf {$run->getKey()}.");
            }

            $previous = (array) ($run->quality_report_json ?? []);
            $fixRuns = (int) ($previous['fix_runs'] ?? 0);
            $report = $gate->check($run->topic, $run, $version, $fixRuns);

            if ($previous !== []) {
                $report['previous'] = array_intersect_key($previous, array_flip(['version_id', 'final_score', 'decision', 'blocking_issues', 'fix_plan', 'checked_at']));
            }

            if ($report['decision'] === QualityGate::DECISION_FIX) {
                $report['fix_runs'] = $fixRuns + 1;
            }

            $attributes = [
                'quality_score' => (int) round((float) $report['final_score']),
                'quality_report_json' => $report,
                'cost_usd' => $budget->spentForRun($this->tenantId, (int) $run->getKey()),
            ];

            match ($report['decision']) {
                QualityGate::DECISION_PUBLISH => $this->approve($run, $attributes),
                QualityGate::DECISION_FIX => $this->fix($run, $attributes),
                default => $this->transition($run, RunStatus::REVIEW, $attributes),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function approve(TopicRun $run, array $attributes): void
    {
        $run->forceFill($attributes)->save();

        if ($this->continue) {
            ArticleApproved::dispatch($this->tenantId, (int) $run->getKey(), $this->versionId);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fix(TopicRun $run, array $attributes): void
    {
        if ($this->transition($run, RunStatus::WRITING, $attributes) && $this->continue) {
            FixSectionsJob::dispatch($this->tenantId, (int) $run->getKey(), $this->versionId);
        }
    }
}
