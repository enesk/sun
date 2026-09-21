<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Jobs\Concerns\WritesArticle;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\TopicRun;
use App\Guide\Writing\ArticleWriter;
use App\Guide\Writing\OutlineService;
use App\Guide\Writing\WritingContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Erstfassung eines Ratgebers (#10, RunMode create): writing -> checking.
 *
 * Hat das Thema noch keine Gliederung (erster Lauf eines draft-Themas),
 * schlaegt der Job sie jetzt vor — mit dem frischen Fakten-Set. Mit
 * guide.auto_lock_outline wird sie gesperrt und sofort geschrieben, sonst
 * geht das Thema auf outline_pending und der Lauf auf review; nach der
 * Sperre im Dashboard setzt ResumeWritingOnOutlineLocked ihn mit
 * review -> writing fort.
 *
 * Geschrieben wird ueber App\Guide\Writing\ArticleWriter::create(); Ergebnis
 * ist eine neue guide_article_versions-Zeile, danach ArticleWritten fuer das
 * Qualitaetsgate (#11). Fehler (Schema, Budget, Regelverstoss) -> failed mit
 * error und consecutive_failures + 1 (HandlesGuideRun::failRun()).
 *
 * `continue = false` schreibt, meldet aber nichts weiter.
 */
class WriteArticleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels, WritesArticle {
        WritesArticle::releaseOnBudget insteadof HandlesGuideRun;
    }

    public function __construct(
        public int $tenantId,
        public int $runId,
        public bool $continue = true,
    ) {
        $this->onQueue((string) config('guide.queues.write', 'guide-write'));
    }

    public function handle(ArticleWriter $writer, OutlineService $outlines, BudgetGuard $budget): void
    {
        $this->withRun(function (TopicRun $run) use ($writer, $outlines, $budget): void {
            if (! $this->transition($run, RunStatus::WRITING)) {
                return;
            }

            $topic = $run->topic;

            if (! $topic->isOutlineLocked()) {
                if (($topic->outline_json ?? []) === []) {
                    $outlines->store($topic, $outlines->propose(WritingContext::for($topic, $run)), $outlines->autoLock(), $this->tenantId);
                }

                if (! $topic->isOutlineLocked()) {
                    $this->awaitOutline($run, $budget);

                    return;
                }
            }

            $this->finishWriting($run, $writer->create($topic, $run), $budget);
        });
    }
}
