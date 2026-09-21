<?php

declare(strict_types=1);

namespace App\Guide\Listeners;

use App\Guide\Events\RunRequested;
use App\Guide\Jobs\TopicChainFactory;
use App\Guide\Models\TopicRun;
use App\Models\Tenant;

/**
 * Erstes Kettenglied fuer Laeufe ausserhalb des Tageslaufs (#13):
 * `guide:run` und "Jetzt ausfuehren" im Dashboard legen den Lauf ueber den
 * TopicRunStarter an und melden RunRequested; die Kette startet sofort, ohne
 * Staffelung.
 */
class StartChainOnRunRequested
{
    public function __construct(
        private readonly TopicChainFactory $chains,
    ) {}

    public function handle(RunRequested $event): void
    {
        $tenant = is_int($event->tenantId) || ctype_digit($event->tenantId)
            ? Tenant::query()->find((int) $event->tenantId)
            : Tenant::query()->where('uuid', $event->tenantId)->first();

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $event): void {
            $run = TopicRun::query()->find($event->runId);

            if ($run !== null) {
                $this->chains->dispatch((int) $tenant->getKey(), $run);
            }
        });
    }
}
