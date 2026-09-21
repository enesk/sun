<?php

declare(strict_types=1);

namespace App\Guide\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Eine Gliederung wurde im Dashboard gesperrt (#15).
 *
 * `rewrite` ist true, wenn das Thema schon einen Artikel hat und die
 * Gliederung vorher bewusst entsperrt wurde: Der naechste Lauf schreibt den
 * Artikel dann komplett neu. Ein Lauf, der in `review` auf die Sperre
 * wartet, setzt mit review -> writing fort; beides verdrahtet der
 * Artikel-Writer (#10) bzw. der Orchestrator (#13).
 *
 * Traegt nur Schluessel, weil guide_topics in der Tenant-DB liegt.
 */
class OutlineLocked
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $tenantId,
        public readonly int $topicId,
        public readonly bool $rewrite,
    ) {}
}
