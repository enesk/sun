<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Carbon;

/**
 * Loeschung angekuendigt: die Karenzfrist laeuft (SUN-RQ #10;
 * docs/profile-requests.md 5).
 *
 * Geht heraus, sobald eine Loeschung bestaetigt ist und die Frist aus
 * `profile-requests.deletion_grace_hours` beginnt. Sie ist die letzte
 * Gelegenheit zu widersprechen und muss deshalb zwei Dinge unmissverstaendlich
 * nennen: **wann** geloescht wird und **wie** man es stoppt.
 *
 * Der Stornolink zeigt auf die Statusseite, nicht auf den Stornolink der
 * Bestaetigungsmail: deren Token ist mit der Verifikation entwertet
 * (ProfileRequest::verify()). Die Statusadresse ist signiert und laeuft
 * nicht ab, der Knopf dort setzt den Vorgang auf `cancelled`.
 */
class DeletionScheduledMail extends RequestMail
{
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public Carbon $graceUntil,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return 'Ihr Eintrag wird gelöscht — Sie können noch widersprechen';
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.deletion-scheduled';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'graceUntil' => $this->graceUntil,
        ];
    }
}
