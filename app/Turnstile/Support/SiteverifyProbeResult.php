<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

/**
 * Ergebnis einer Schluesselprobe ({@see SiteverifyProbe}).
 *
 * `label` ist die kurze Fassung fuer die Tabelle von `turnstile:keys:check`
 * (#14), `message` der ganze Satz fuer das Admin-Panel (#9). Ein Secret steht
 * in keinem der beiden Felder.
 */
final class SiteverifyProbeResult
{
    public function __construct(
        public readonly string $state,
        public readonly string $label,
        public readonly string $message,
    ) {}

    public function isOk(): bool
    {
        return $this->state === SiteverifyProbe::STATE_OK;
    }
}
