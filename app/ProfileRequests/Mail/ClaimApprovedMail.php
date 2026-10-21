<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Uebernahme freigegeben, mit Zugang (SUN-RQ #10).
 *
 * Die Mail nach {@see \App\ProfileRequests\Actions\ApproveClaim}. Zwei
 * Faelle, und sie duerfen nicht verwechselt werden:
 *
 *  * **Konto neu angelegt** — der Betrieb hat noch kein Passwort. Es liegt
 *    auch nicht in dieser Mail: ApproveClaim verschickt dafuer den
 *    Passwort-Link von Laravel, und wir legen nie ein Passwort fuer jemanden
 *    fest. Diese Mail sagt, dass die zweite Mail kommt.
 *  * **Konto war vorhanden** — die Anmeldung laeuft mit den bekannten Daten,
 *    ein Passwort-Link waere hier nur verwirrend.
 */
class ClaimApprovedMail extends RequestMail
{
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public string $loginUrl,
        /** true = Konto wurde neu angelegt, der Passwort-Link kommt separat. */
        public bool $accountCreated = false,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return 'Ihr Eintrag gehört jetzt Ihnen';
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.claim-approved';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'loginUrl' => $this->loginUrl,
            'accountCreated' => $this->accountCreated,
        ];
    }
}
