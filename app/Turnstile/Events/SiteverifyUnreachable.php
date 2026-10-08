<?php

declare(strict_types=1);

namespace App\Turnstile\Events;

use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Dto\VerificationResult;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Siteverify war nicht erreichbar (#4). Alarmcode SUN-TS-011.
 *
 * Wird von TurnstileRule bei VerificationOutcome::Error ausgeloest — in beiden
 * Fail-Modes, denn der Betrieb muss den Ausfall sehen, egal ob die Anfrage
 * durchgelaufen ist oder nicht. Bei fail_mode=open ist dieses Ereignis die
 * einzige Spur davon, dass gerade ungeprueft durchgelassen wurde.
 *
 * Der Listener dazu ist App\Listeners\Turnstile\AlertOnSiteverifyUnreachable
 * (#19): er zaehlt die Ausfaelle je Portal in einem kurzen Fenster und meldet
 * erst ab der Schwelle, statt je Ereignis eine Mail zu schicken.
 */
class SiteverifyUnreachable
{
    use Dispatchable;

    public const ALERT_CODE = 'SUN-TS-011';

    public function __construct(
        public readonly ResolvedTurnstileConfig $config,
        public readonly VerificationResult $result,
        public readonly bool $requestWasAllowed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return $this->config->toLogContext() + $this->result->toLogContext() + [
            'alert' => self::ALERT_CODE,
            'request_allowed' => $this->requestWasAllowed,
        ];
    }
}
