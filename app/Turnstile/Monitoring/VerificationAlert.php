<?php

declare(strict_types=1);

namespace App\Turnstile\Monitoring;

/**
 * Ein Alarm des Bot-Schutzes (#12, #19), fertig formuliert.
 *
 * Der Monitor rechnet, die Mail stellt dar — hier steht das Ergebnis dazwischen.
 * `type` ist der Alarmtyp und gleichzeitig Teil des Entdopplungsschluessels
 * (tenant:type:stunde), `headline` der Betreff, `consequence` die Antwort auf
 * "was passiert ohne Eingriff".
 *
 * @phpstan-type Zahlen array<string, int|float|string>
 */
final class VerificationAlert
{
    public const TYPE_ERROR_SHARE = 'error_share';

    public const TYPE_ERROR_COUNT = 'error_count';

    public const TYPE_BLOCK_SHARE_LOW = 'block_share_low';

    public const TYPE_BLOCK_SHARE_HIGH = 'block_share_high';

    /**
     * Siteverify-Ausfall in Minuten statt Stunden (#19, SUN-TS-011). Kommt
     * nicht aus dem stuendlichen Monitor, sondern aus dem Listener auf
     * {@see \App\Turnstile\Events\SiteverifyUnreachable}; entdoppelt wird
     * dort ueber eine Ruhezeit, nicht ueber die Kalenderstunde.
     */
    public const TYPE_SITEVERIFY_UNREACHABLE = 'siteverify_unreachable';

    /**
     * @param  array<string, int|float|string>  $numbers  Kennzahlen fuer die Mail, Beschriftung => Wert
     */
    public function __construct(
        public readonly string $type,
        public readonly int $tenantId,
        public readonly string $tenantName,
        public readonly string $headline,
        public readonly string $message,
        public readonly string $consequence,
        public readonly array $numbers = [],
    ) {}

    /** Entdopplung: ein Alarm je Portal, Typ und Stunde. */
    public function cacheKey(string $stunde): string
    {
        return "turnstile:alert:{$this->tenantId}:{$this->type}:{$stunde}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'tenant_id' => $this->tenantId,
            'tenant' => $this->tenantName,
            'headline' => $this->headline,
            'message' => $this->message,
            'consequence' => $this->consequence,
            'numbers' => $this->numbers,
        ];
    }
}
