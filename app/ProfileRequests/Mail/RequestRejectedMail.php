<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Anfrage abgelehnt, mit Grund (SUN-RQ #10).
 *
 * Der Grund ist Pflicht und wird im Klartext uebernommen, wie Enes ihn im
 * Panel (#7) eingetragen hat. Eine Ablehnung ohne Grund erzeugt eine
 * Rueckfrage und bei Loesch- und Uebernahmeantraegen auch eine Beschwerde:
 * beide beruehren Rechte des Betriebs, und eine Ablehnung muss begruendet
 * sein.
 *
 * Der naechste Schritt steht ebenfalls drin — ohne ihn ist die Mail eine
 * Sackgasse.
 */
class RequestRejectedMail extends RequestMail
{
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public string $reason,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return 'Wir können Ihre Anfrage nicht umsetzen';
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.rejected';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'reason' => trim($this->reason) !== ''
                ? trim($this->reason)
                : (string) ($this->request->rejected_reason ?? ''),
        ];
    }
}
