<?php

declare(strict_types=1);

namespace App\Guide\Jobs\Concerns;

use App\Guide\Enums\RunStatus;
use App\Guide\Events\ArticleWritten;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\TopicRun;
use Illuminate\Support\Carbon;

/**
 * Gemeinsamer Abschluss von WriteArticleJob und UpdateSectionsJob (#10).
 * Setzt HandlesGuideRun voraus.
 */
trait WritesArticle
{
    /**
     * Schreib-Jobs setzen den Lauf bei erschoepftem Budget auf failed statt
     * ihn spaeter fortzusetzen: Ein Neustart ist ein neuer Lauf.
     */
    protected function releaseOnBudget(): bool
    {
        return false;
    }

    /**
     * Ohne gesperrte Gliederung wartet der Lauf in review, bis sie im
     * Dashboard gesperrt ist (ResumeWritingOnOutlineLocked).
     */
    protected function awaitOutline(TopicRun $run, BudgetGuard $budget): void
    {
        $this->transition($run, RunStatus::REVIEW, [
            'cost_usd' => $budget->spentForRun($this->tenantId, (int) $run->getKey()),
        ]);
    }

    protected function finishWriting(TopicRun $run, ArticleVersion $version, BudgetGuard $budget): void
    {
        $topic = $run->topic;

        // Erfolgreicher Lauf (#9): Fehlerzaehler zurueck, naechster Termin nach Pruefabstand.
        $topic?->forceFill([
            'consecutive_failures' => 0,
            'next_due_at' => Carbon::now()->addDays($topic->refreshIntervalDays()),
        ])->save();

        $moved = $this->transition($run, RunStatus::CHECKING, [
            'cost_usd' => $budget->spentForRun($this->tenantId, (int) $run->getKey()),
        ]);

        if ($moved && $this->continue) {
            ArticleWritten::dispatch($this->tenantId, (int) $run->getKey(), (int) $version->getKey(), $run->mode);
        }
    }
}
