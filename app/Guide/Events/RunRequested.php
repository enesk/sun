<?php

declare(strict_types=1);

namespace App\Guide\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Lauf wurde ausserhalb des Tageslaufs angelegt (`guide:run`, Dashboard
 * "Jetzt ausfuehren", #15). Der Lauf steht in guide_topic_runs mit Status
 * `queued`; den ersten Job der Kette stoesst der Listener des Orchestrators
 * (#13) an.
 *
 * Traegt nur Schluessel, weil guide_topic_runs in der Tenant-DB liegt.
 */
class RunRequested
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $tenantId,
        public readonly int $topicId,
        public readonly int $runId,
    ) {}
}
