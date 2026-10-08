<?php

declare(strict_types=1);

namespace App\Turnstile\Exceptions;

use App\Turnstile\Enums\TurnstileAction;
use RuntimeException;

/**
 * In Produktion fehlt ein echter Secret (#3). Entweder ist gar keiner
 * hinterlegt, oder es steht noch ein Cloudflare-Testschluessel in der .env —
 * beides heisst: das Formular waere nur scheinbar geschuetzt.
 *
 * Die Meldung enthaelt nie einen Schluesselwert. Gefangen wird die Ausnahme von
 * TurnstileRule (#4), die daraus VerificationOutcome::Error macht und den
 * Fail-Mode anwendet; alarmiert wird in #12.
 */
class TurnstileNotConfiguredException extends RuntimeException
{
    public static function forAction(TurnstileAction $action, ?int $tenantId, string $reason): self
    {
        $portal = $tenantId !== null ? "Portal {$tenantId}" : 'zentrale Domain';

        return new self(
            "Turnstile ist fuer {$action->value} ({$portal}) nicht einsatzbereit: {$reason}. "
            .'Produktionsschluessel in den Turnstile-Einstellungen des Portals oder in der .env hinterlegen (#14).'
        );
    }
}
