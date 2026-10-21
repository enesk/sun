<?php

declare(strict_types=1);

namespace App\ProfileRequests\Observers;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Exceptions\RequestTransitionException;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;

/**
 * Waechter des Zustandsautomaten (SUN-RQ, Ticket #3).
 *
 * Am Model per #[ObservedBy] registriert, also auch bei Massenanlage, in
 * Jobs und in Filament wirksam.
 *
 * Zwei Aufgaben:
 *
 * 1. `updating` — eine Aenderung an `status` wird nur durchgelassen, wenn sie
 *    aus {@see ProfileRequest::transitionTo()} kommt. Alles andere fliegt mit
 *    einer Exception auf: ein Statuswechsel ohne Protokollzeile waere ein
 *    Vorgang, dessen Geschichte sich nicht mehr belegen laesst — und das
 *    Protokoll ist bei Loesch- und Uebernahmeantraegen die Rechtsgrundlage.
 *
 * 2. `created` — schreibt die erste Protokollzeile, damit jeder Vorgang eine
 *    hat, auch wenn er direkt in `classified` angelegt wird (Formular, #4).
 *
 * Nicht Aufgabe des Observers: pruefen, ob der Uebergang im Automaten steht.
 * Das macht `transitionTo()` vorher, mit der genauen Fehlermeldung.
 *
 * `Model::withoutEvents()` umgeht diesen Waechter — das ist Absicht und der
 * Notausgang fuer eine Datenwanderung (#12), nicht fuer Anwendungscode.
 */
class ProfileRequestObserver
{
    public function updating(ProfileRequest $request): void
    {
        if (! $request->isDirty('status')) {
            return;
        }

        if ($request->isInTransition()) {
            return;
        }

        throw RequestTransitionException::outsideStateMachine(
            $request->getOriginal('status'),
            $request->getAttribute('status'),
        );
    }

    public function created(ProfileRequest $request): void
    {
        $source = $request->source;

        $request->recordEvent(
            RequestEventType::CREATED,
            [
                'source' => $source?->value,
                'type' => $request->type?->value,
            ],
            // Formular und Uebernahme-Dialog hat der Anfragende selbst
            // abgeschickt; eine Mail holt der Poller (#5), das ist System.
            $source === RequestSource::EMAIL ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_REQUESTER,
            null,
            null,
            $request->status instanceof RequestStatus
                ? $request->status
                : RequestStatus::from((string) $request->status),
        );
    }
}
