<?php

declare(strict_types=1);

namespace App\Listeners\Claims;

use App\Claims\ClaimNotificationStatus;
use App\Mail\ClaimApproved;
use Illuminate\Mail\Events\MessageSent;

/**
 * Haelt fest, dass die Claim-Benachrichtigung wirklich rausgegangen ist
 * (Ticket #25).
 *
 * Gehoert an `MessageSent` und **nicht** an die Stelle des Aufrufs:
 * `ClaimApproved` und `ClaimRejected` sind `ShouldQueue`, dort heisst
 * `Mail::to()->send()` nur "in die Warteschlange gelegt". Ein Status, der
 * danach auf "versendet" springt, behauptet etwas, das noch nicht stimmt —
 * und bliebe stehen, wenn der Job scheitert (in `queue:failed` der Produktion
 * lagen zwei solche Mail-Jobs vom September, unsichtbar).
 *
 * `MessageSent` feuert erst, wenn der Mailer die Nachricht abgegeben hat. Der
 * Antrag wird ueber die Kopfzeilen der Nachricht gefunden, nicht ueber den
 * Empfaenger oder den Zeitstempel: dieselbe Adresse kann mehrere Antraege
 * haben.
 *
 * Zustellung beim Empfaenger ist damit nicht belegt — das kann kein SMTP-Aufruf
 * wissen. Belegt ist: wir haben sie abgegeben.
 */
class RecordClaimNotificationSent
{
    public function handle(MessageSent $event): void
    {
        $kopf = $event->message->getHeaders();

        $antrag = $kopf->get(ClaimApproved::HEADER_CLAIM)?->getBodyAsString();
        $portal = $kopf->get(ClaimApproved::HEADER_TENANT)?->getBodyAsString();

        if (! is_numeric($antrag) || ! is_string($portal) || trim($portal) === '') {
            return;
        }

        ClaimNotificationStatus::markSent(trim($portal), (int) $antrag);
    }
}
