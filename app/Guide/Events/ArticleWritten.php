<?php

declare(strict_types=1);

namespace App\Guide\Events;

use App\Guide\Enums\RunMode;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Der Artikel-Writer (#10) hat eine neue Fassung angelegt, der Lauf steht auf
 * `checking`. Nachfolger ist das Qualitaetsgate (#11), das sich per Listener
 * daran haengt.
 *
 * Traegt nur Schluessel, weil Lauf und Fassung in der Tenant-DB liegen.
 */
class ArticleWritten
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $tenantId,
        public readonly int $runId,
        public readonly int $versionId,
        public readonly RunMode $mode,
    ) {}
}
