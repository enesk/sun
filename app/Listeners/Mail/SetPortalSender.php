<?php

declare(strict_types=1);

namespace App\Listeners\Mail;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;

/**
 * Absendername jeder ausgehenden Mail: der Name des Portals, aus dem sie
 * stammt — nie MAIL_FROM_NAME (= APP_NAME, "SaaSykit").
 *
 * Viele Mails laufen im Central-Kontext (Abo, Bestellung, Claim-Freigabe),
 * dort ist tenant() leer. Deshalb sucht der Listener das Portal zusaetzlich
 * in den Daten der Mail: eine Variable vom Typ Tenant (z. B. mailTenant) oder
 * ein Modell mit tenant-Beziehung (Bestellung, Abo, Antrag).
 *
 * Die Absenderadresse bleibt unveraendert, sonst brechen SPF und DKIM.
 * Stattdessen setzt der Listener die Antwortadresse auf die Kontaktadresse
 * des Portals, damit Antworten beim richtigen Betreiber landen.
 */
class SetPortalSender
{
    public function handle(MessageSending $event): void
    {
        $tenant = $this->resolveTenant($event);
        $message = $event->message;

        $from = $message->getFrom()[0] ?? null;

        if ($from !== null) {
            $message->from(new \Symfony\Component\Mime\Address($from->getAddress(), TenantMailBranding::for($tenant)->portalName()));
        }

        if ($tenant === null || $message->getReplyTo() !== []) {
            return;
        }

        $contact = $tenant->getAttribute(TenantConfigConstants::CONTACT_EMAIL);

        if (is_string($contact) && filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $message->replyTo($contact);
        }
    }

    private function resolveTenant(MessageSending $event): ?Tenant
    {
        $current = tenant();

        if ($current instanceof Tenant) {
            return $current;
        }

        foreach ($event->data as $value) {
            if ($value instanceof Tenant) {
                return $value;
            }
        }

        // Bestellung, Abo, Claim-Antrag und Co. kennen ihr Portal ueber die Beziehung
        foreach ($event->data as $value) {
            if (! $value instanceof Model) {
                continue;
            }

            $related = rescue(fn () => $value->getAttribute('tenant'), null, false);

            if ($related instanceof Tenant) {
                return $related;
            }
        }

        return null;
    }
}
