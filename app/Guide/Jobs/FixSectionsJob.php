<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Jobs\Concerns\WritesArticle;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\TopicRun;
use App\Guide\Writing\ArticleWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Fix-Durchlauf des Qualitaetsgates (#11): writing -> checking.
 *
 * Bessert in der geprueften Fassung genau die Teile aus
 * quality_report_json.fix_plan nach (ArticleWriter::fix()) und legt eine
 * neue Fassung an; alle anderen Abschnitte bleiben byte-identisch. Danach
 * ArticleWritten, die neue Fassung geht wieder durch den QualityCheckJob —
 * der schickt sie nicht noch einmal zurueck, sondern in review.
 *
 * Fehler -> failed wie bei den anderen Schreib-Jobs.
 */
class FixSectionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels, WritesArticle {
        WritesArticle::releaseOnBudget insteadof HandlesGuideRun;
    }

    public function __construct(
        public int $tenantId,
        public int $runId,
        public int $versionId,
        public bool $continue = true,
    ) {
        $this->onQueue((string) config('guide.queues.write', 'guide-write'));
    }

    public function handle(ArticleWriter $writer, BudgetGuard $budget): void
    {
        $this->withRun(function (TopicRun $run) use ($writer, $budget): void {
            if (! $this->transition($run, RunStatus::WRITING)) {
                return;
            }

            /** @var ArticleVersion|null $base */
            $base = ArticleVersion::query()
                ->whereKey($this->versionId)
                ->where('guide_topic_run_id', $run->getKey())
                ->first();

            $plan = (array) ($run->quality_report_json['fix_plan'] ?? []);

            if ($base === null || $plan === []) {
                throw new RuntimeException("Fix-Durchlauf fuer Lauf {$run->getKey()} ohne Fassung {$this->versionId} oder ohne Fix-Plan.");
            }

            $this->finishWriting($run, $writer->fix($run->topic, $run, $base, $plan), $budget);
        });
    }
}
