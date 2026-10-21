<?php

declare(strict_types=1);

namespace App\ProfileRequests\Exceptions;

use App\ProfileRequests\Enums\RequestStatus;
use RuntimeException;

/**
 * Ein Zustandswechsel, den der Automat nicht hergibt (SUN-RQ, Ticket #3).
 *
 * Zwei Faelle, beide ein Programmierfehler und keine Benutzereingabe —
 * deshalb eine Exception und keine Validierungsmeldung:
 *
 * 1. {@see self::notAllowed()} — der Uebergang steht nicht in
 *    RequestStatus::allowedTransitions().
 * 2. {@see self::outsideStateMachine()} — `status` wurde direkt gesetzt und
 *    gespeichert, am Zustandsautomaten vorbei. Dann fehlte dem Vorgang die
 *    Protokollzeile, und genau das soll nie passieren.
 */
class RequestTransitionException extends RuntimeException
{
    public static function notAllowed(RequestStatus $from, RequestStatus $to): self
    {
        return new self(sprintf(
            'Profil-Anfrage: Uebergang %s -> %s ist nicht erlaubt (erlaubt: %s).',
            $from->value,
            $to->value,
            implode(', ', array_map(
                fn (RequestStatus $status) => $status->value,
                $from->allowedTransitions()
            )) ?: '—'
        ));
    }

    public static function outsideStateMachine(mixed $from, mixed $to): self
    {
        return new self(sprintf(
            'Profil-Anfrage: status darf nicht direkt gesetzt werden (%s -> %s). '
            .'Statt dessen markClassified(), requestVerification(), verify(), apply(), '
            .'reject(), markExpired() oder cancel() verwenden — nur die schreiben ein request_event.',
            is_object($from) ? ($from->value ?? '?') : (string) $from,
            is_object($to) ? ($to->value ?? '?') : (string) $to
        ));
    }
}
