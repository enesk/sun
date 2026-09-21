<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Enums\RunMode;
use App\Guide\Events\ResearchCompleted;
use App\Guide\Jobs\UpdateSectionsJob;
use App\Guide\Jobs\WriteArticleJob;

/**
 * Naechstes Kettenglied nach der Tiefenrecherche (#8 -> #10): create schreibt
 * den ganzen Artikel, update nur die betroffenen Abschnitte.
 */
class WriteOnResearchCompleted
{
    public function handle(ResearchCompleted $event): void
    {
        match ($event->mode) {
            RunMode::CREATE => WriteArticleJob::dispatch($event->tenantId, $event->runId),
            RunMode::UPDATE => UpdateSectionsJob::dispatch($event->tenantId, $event->runId),
            RunMode::UNCHANGED => null,
        };
    }
}
