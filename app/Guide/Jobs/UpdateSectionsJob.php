<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Jobs\Concerns\WritesArticle;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\TopicRun;
use App\Guide\Writing\ArticleWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Abschnittsweise Aktualisierung (#10, RunMode update): writing -> checking.
 *
 * Schreibt nur guide_topic_runs.changed_section_ids_json neu (#9); alle
 * anderen Abschnitte bleiben byte-identisch. Key-Facts, Kurzantwort, FAQ,
 * Titel und Meta nur, wenn ein darin genannter Fakt betroffen ist; dazu ein
 * Changelog-Eintrag. Fachlogik in App\Guide\Writing\ArticleWriter::update().
 *
 * Ist die Gliederung entsperrt (bewusst im Dashboard), wartet der Lauf in
 * review auf die neue Sperre. Fehler -> failed wie im WriteArticleJob.
 *
 * Mit $fixInstructions ("Mit Hinweis neu schreiben" in der Pruef-Queue, #16)
 * wird nicht neu recherchiert oder aktualisiert, sondern die gepruefte
 * Fassung des Laufs nach dem Hinweis nachgebessert (ArticleWriter::revise());
 * danach geht sie wie jede Fassung durch das Qualitaetsgate.
 */
class UpdateSectionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels, WritesArticle {
        WritesArticle::releaseOnBudget insteadof HandlesGuideRun;
    }

    public function __construct(
        public int $tenantId,
        public int $runId,
        public bool $continue = true,
        public ?string $fixInstructions = null,
    ) {
        $this->onQueue((string) config('guide.queues.write', 'guide-write'));
    }

    public function handle(ArticleWriter $writer, BudgetGuard $budget): void
    {
        $this->withRun(function (TopicRun $run) use ($writer, $budget): void {
            if (! $this->transition($run, RunStatus::WRITING)) {
                return;
            }

            if (! $run->topic->isOutlineLocked()) {
                $this->awaitOutline($run, $budget);

                return;
            }

            $version = filled($this->fixInstructions)
                ? $writer->revise($run->topic, $run, (string) $this->fixInstructions)
                : $writer->update($run->topic, $run);

            $this->finishWriting($run, $version, $budget);
        });
    }
}
