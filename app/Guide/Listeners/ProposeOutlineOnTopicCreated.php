<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Enums\TopicStatus;
use App\Guide\Events\TopicCreated;
use App\Guide\Jobs\ProposeOutlineJob;

/**
 * Importierte Themen ohne Ueberschriften bekommen einen Gliederungsvorschlag
 * (#10). Der Import (#6) legt solche Themen als outline_pending an; ob das
 * Thema wirklich noch keine Gliederung hat, prueft der Job im Tenant-Kontext.
 */
class ProposeOutlineOnTopicCreated
{
    public function handle(TopicCreated $event): void
    {
        if ($event->status !== TopicStatus::OUTLINE_PENDING) {
            return;
        }

        ProposeOutlineJob::dispatch($event->tenantId, $event->topicId);
    }
}
