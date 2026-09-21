<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Events\ArticleWritten;
use App\Guide\Jobs\QualityCheckJob;

/**
 * Naechstes Kettenglied nach dem Schreiben (#10 -> #11): jede neue Fassung,
 * auch die aus dem Fix-Durchlauf, geht durch das Qualitaetsgate.
 */
class CheckQualityOnArticleWritten
{
    public function handle(ArticleWritten $event): void
    {
        QualityCheckJob::dispatch($event->tenantId, $event->runId, $event->versionId);
    }
}
