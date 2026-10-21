<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Carbon;

/**
 * Bestaetigungsmail mit Magic-Link (SUN-RQ #6).
 *
 * Geht ausschliesslich an die **beim Profil hinterlegte** Firmen-E-Mail, nie
 * an die Adresse des Anfragenden — sonst bestaetigt sich jeder selbst. Sie
 * enthaelt beides: den Bestaetigungslink und einen Stornolink fuer den Fall,
 * dass der Betrieb die Anfrage gar nicht gestellt hat. Der zweite Link ist
 * der wichtigere: er ist die einzige Moeglichkeit, einer fremden Anfrage im
 * eigenen Namen zu widersprechen.
 *
 * Betreff, Message-ID, Antwortadresse und Branding kommen aus
 * {@see RequestMail} (#10).
 */
class VerifyRequestMail extends RequestMail
{
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public string $verifyUrl,
        public string $cancelUrl,
        public Carbon $expiresAt,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return match ($this->request->type?->value) {
            'delete' => 'Bitte bestätigen: Löschung Ihres Eintrags',
            'claim' => 'Bitte bestätigen: Übernahme Ihres Eintrags',
            default => 'Bitte bestätigen: Änderung an Ihrem Eintrag',
        };
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.verify-request';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'verifyUrl' => $this->verifyUrl,
            'cancelUrl' => $this->cancelUrl,
            'expiresAt' => $this->expiresAt,
            'what' => $this->what(),
            'changes' => $this->changeLines(),
        ];
    }

    /**
     * Worum es geht, in einem Satz — der Betrieb muss ohne Fachbegriffe
     * verstehen, was er bestaetigt.
     */
    private function what(): string
    {
        return match ($this->request->type?->value) {
            'change' => 'Es liegt eine Änderung an Ihrem Eintrag vor.',
            'delete' => 'Es liegt der Wunsch vor, Ihren Eintrag zu löschen.',
            'claim' => 'Es liegt ein Antrag vor, Ihren Eintrag zu übernehmen und zu verwalten.',
            default => 'Es liegt eine Anfrage zu Ihrem Eintrag vor.',
        };
    }
}
