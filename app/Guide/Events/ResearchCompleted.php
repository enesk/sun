<?php

declare(strict_types=1);

namespace App\Guide\Events;

use App\Guide\Enums\RunMode;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Die Tiefenrecherche eines Laufs ist abgeschlossen, der Lauf steht auf
 * `writing` (#8). Nachfolger ist je Modus WriteArticleJob (create) oder
 * UpdateSectionsJob (update); der Listener, der sie anstoesst, kommt mit #10.
 *
 * Traegt nur Schluessel, weil der Lauf in der Tenant-DB liegt.
 */
class ResearchCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $runId,
        public readonly RunMode $mode,
        public readonly bool $factsChanged,
    ) {}
}
