<?php

declare(strict_types=1);

namespace App\Guide\Events;

use App\Guide\Enums\TopicStatus;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Ratgeber-Thema wurde durch die Zuweisung einer Themenliste angelegt.
 *
 * Traegt nur Schluessel statt des Modells, weil guide_topics in der
 * Tenant-DB liegt und ein Listener den Tenant-Kontext selbst herstellen muss.
 * Der Listener, der bei outline_pending den Gliederungsvorschlag anstoesst,
 * wird mit #10 registriert.
 */
class TopicCreated
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $tenantId,
        public readonly int $topicId,
        public readonly TopicStatus $status,
    ) {}
}
