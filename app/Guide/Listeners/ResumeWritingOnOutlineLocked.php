<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Events\OutlineLocked;
use App\Guide\Jobs\UpdateSectionsJob;
use App\Guide\Jobs\WriteArticleJob;
use App\Guide\Models\TopicRun;
use App\Models\Tenant;

/**
 * Laeufe, die in review auf die Sperre der Gliederung warten, setzen nach dem
 * Sperren mit review -> writing fort (docs/guide-system.md §2, #10).
 *
 * Wartend heisst: Status review und noch keine Fassung aus diesem Lauf —
 * Laeufe in der Pruef-Queue des Qualitaetsgates (#11) haben eine.
 * `rewrite` (bewusst entsperrte Gliederung eines Themas mit Artikel) hebt
 * einen update-Lauf auf create: Der Artikel wird entlang der neuen
 * Gliederung komplett neu geschrieben.
 */
class ResumeWritingOnOutlineLocked
{
    public function handle(OutlineLocked $event): void
    {
        $tenant = Tenant::query()->find($event->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($event): void {
            $runs = TopicRun::query()
                ->where('guide_topic_id', $event->topicId)
                ->where('status', RunStatus::REVIEW->value)
                ->whereDoesntHave('versions')
                ->get();

            foreach ($runs as $run) {
                $mode = $event->rewrite && $run->mode->canTransitionTo(RunMode::CREATE) ? RunMode::CREATE : $run->mode;

                if (! $run->status->canTransitionTo(RunStatus::WRITING)) {
                    continue;
                }

                $run->forceFill(['status' => RunStatus::WRITING, 'mode' => $mode])->save();

                $mode === RunMode::UPDATE
                    ? UpdateSectionsJob::dispatch((int) $event->tenantId, (int) $run->getKey())
                    : WriteArticleJob::dispatch((int) $event->tenantId, (int) $run->getKey());
            }
        });
    }
}
