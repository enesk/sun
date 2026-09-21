<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Events\ArticleApproved;
use App\Guide\Jobs\PublishArticleJob;

/**
 * Naechstes Kettenglied nach dem Qualitaetsgate (#11 -> #12).
 */
class PublishOnArticleApproved
{
    public function handle(ArticleApproved $event): void
    {
        PublishArticleJob::dispatch((int) $event->tenantId, $event->runId, $event->versionId);
    }
}
