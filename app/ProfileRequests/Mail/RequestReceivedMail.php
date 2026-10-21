<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Eingangsbestaetigung an den Anfragenden (SUN-RQ #10).
 *
 * Geht — anders als die Bestaetigungsmail (#6) — an die Adresse des
 * **Anfragenden**: sie bestaetigt nichts und gibt nichts frei, sie sagt nur
 * „angekommen, Vorgangsnummer, hier ist der Stand". Genau diese Mail nimmt
 * die Rueckfrage „haben Sie meine Mail bekommen?" weg, die heute einen
 * grossen Teil der Handarbeit ausmacht (docs/profile-requests.md 2.4).
 *
 * Sie erklaert ausdruecklich, dass die Bearbeitung an der Bestaetigung ueber
 * die hinterlegte Firmen-E-Mail haengt — sonst wartet jemand auf eine
 * Erledigung, die auf einen Klick wartet, den er nie gesehen hat.
 */
class RequestReceivedMail extends RequestMail
{
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        /** Adresse, an die der Magic-Link ging; null = Handpruefung. */
        public ?string $verificationSentTo = null,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return match ($this->request->type) {
            RequestType::DELETE => 'Ihr Löschantrag ist eingegangen',
            RequestType::CLAIM => 'Ihr Übernahmeantrag ist eingegangen',
            RequestType::CHANGE => 'Ihre Änderung ist eingegangen',
            default => 'Ihre Anfrage ist eingegangen',
        };
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.received';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'verificationSentTo' => $this->verificationSentTo,
            'changes' => $this->changeLines(),
            'what' => $this->what(),
        ];
    }

    private function what(): string
    {
        return match ($this->request->type) {
            RequestType::CHANGE => 'Sie möchten Angaben in Ihrem Eintrag ändern.',
            RequestType::DELETE => 'Sie möchten Ihren Eintrag löschen lassen.',
            RequestType::CLAIM => 'Sie möchten Ihren Eintrag übernehmen und selbst verwalten.',
            default => 'Sie haben uns zu Ihrem Eintrag geschrieben.',
        };
    }
}
