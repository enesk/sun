<?php

declare(strict_types=1);

namespace App\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Provider bzw. Quell-Connector ist mehrfach in Folge gescheitert (#7).
 *
 * Das Content-Dashboard (#20) und der Tagesbericht (#22) haengen sich hier
 * ein. Der Alarm wird je Fehlerserie genau einmal ausgeloest; ein
 * erfolgreicher Lauf setzt den Zaehler und den Alarm zurueck.
 */
class ProviderAlarmRaised
{
    use Dispatchable;

    public function __construct(
        public string $provider,
        public int $consecutiveFailures,
        public ?string $lastError = null,
        public ?int $tenantId = null,
    ) {}
}
