<?php

declare(strict_types=1);

namespace App\Guide\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Das Qualitaetsgate (#11) hat eine Fassung zur Veroeffentlichung
 * freigegeben; der Lauf steht weiter auf `checking`. Nachfolger ist der
 * Publisher (#12), der sich per Listener daran haengt und den Lauf auf
 * `published` setzt.
 *
 * Traegt nur Schluessel, weil Lauf und Fassung in der Tenant-DB liegen.
 */
class ArticleApproved
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $tenantId,
        public readonly int $runId,
        public readonly int $versionId,
    ) {}
}
