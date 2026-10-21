<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use Illuminate\Support\Str;

/**
 * Anfrage ablehnen (SUN-RQ #7).
 *
 * Schreibt nichts im Portal — eine Ablehnung ist eine Entscheidung ueber den
 * Vorgang, nicht ueber das Profil. Der Grund ist Pflicht: er steht im
 * Protokoll, erscheint auf der Status-Seite (#10) und ist die Vorlage fuer
 * die Antwort an den Betrieb.
 *
 * Eine Mail geht hier **nicht** raus. Die Absage formuliert Enes mit
 * „Antwort senden" ({@see SendReply}) — eine automatische Absage auf eine
 * Anfrage, die wir fuer unberechtigt halten, ist der Anfang einer Beschwerde.
 */
class RejectRequest
{
    /** Hoechstlaenge des Grundes; `rejected_reason` ist ein Textfeld. */
    private const MAX_REASON = 2000;

    /**
     * @return array{ok: bool, message: string}
     */
    public function __invoke(ProfileRequest $request, string $reason, ?User $actor = null): array
    {
        $reason = Str::limit(trim($reason), self::MAX_REASON, '');

        if ($reason === '') {
            return ['ok' => false, 'message' => 'Ohne Grund wird nicht abgelehnt.'];
        }

        if (! $request->isOpen()) {
            return ['ok' => false, 'message' => 'Dieser Vorgang ist bereits abgeschlossen.'];
        }

        // `new` kennt den Uebergang nach `rejected` ebenso wie jeder andere
        // offene Zustand (RequestStatus::allowedTransitions()), deshalb
        // braucht es hier keinen Zwischenschritt.
        if (! $request->reject($reason, $actor)) {
            return ['ok' => false, 'message' => 'Der Vorgang ließ sich nicht ablehnen.'];
        }

        // Noch offene Haken im Diff sind mit der Ablehnung hinfaellig; sonst
        // stuende in der Historie eine akzeptierte Zeile, die nie lief.
        $request->changes()->whereNull('accepted')->update(['accepted' => false]);

        $request->recordEvent(
            RequestEventType::NOTE,
            ['note' => 'Diff verworfen (Ablehnung)'],
            $actor === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $actor?->getKey() === null ? null : (int) $actor->getKey(),
        );

        return ['ok' => true, 'message' => 'Anfrage abgelehnt.'];
    }
}
