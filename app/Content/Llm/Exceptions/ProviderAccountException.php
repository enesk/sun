<?php

declare(strict_types=1);

namespace App\Content\Llm\Exceptions;

use RuntimeException;

/**
 * Das Konto beim Modellanbieter selbst ist nicht benutzbar (#104): das
 * Guthaben ist aufgebraucht oder der Zugangsschluessel wird abgelehnt.
 *
 * Abgrenzung zu den beiden anderen Geldgrenzen:
 *   - BudgetExceededException (#6): unsere eigene Tagesgrenze in USD. Sie
 *     endet um 00:00 von selbst, der Provider steht auf `paused`.
 *   - ProviderQuotaException (#8): Anzahl Anfragen eines Connectors.
 *
 * Hier hilft kein Wiederholungsversuch und kein Warten auf Mitternacht —
 * es muss jemand Guthaben aufladen oder einen Schluessel erneuern. Der
 * Provider steht deshalb auf ProviderState::STATUS_FAILED und die Nachricht
 * ist bereits der Klartext, der im Alarm und im Tagesbericht steht.
 */
class ProviderAccountException extends RuntimeException
{
    /** Das Guthaben des Kontos ist aufgebraucht. */
    public const REASON_CREDIT = 'credit_exhausted';

    /** Der Zugangsschluessel wird abgelehnt. */
    public const REASON_KEY = 'invalid_key';

    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $reason,
        public readonly ?string $providerError = null,
    ) {
        parent::__construct($message);
    }
}
