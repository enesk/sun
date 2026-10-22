<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Events\ProfileRequestReceived;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Übernahmeantrag in die Inbox eintragen (SUN-RQ #1 der zweiten Reihe).
 *
 * Der Claim-Dialog schreibt seinen Antrag in `claim_requests` der Tenant-DB
 * und kannte die Inbox nicht — gemeldet von Enes am 22.10.2026: neue
 * Übernahmen tauchten dort nicht auf, nur die 115 aus dem Import.
 *
 * Diese Aktion schließt die Lücke und ist der **einzige** Weg, aus einem
 * Antrag einen Inbox-Vorgang zu machen. Sie ist idempotent: `external_ref`
 * trägt die **nackte** `claim_requests.id`, und genau so sucht
 * {@see ApproveClaim} den Antrag später wieder (`ctype_digit`). Ein zweiter
 * Aufruf gibt den vorhandenen Vorgang zurück, statt einen weiteren
 * anzulegen — der Dialog ruft `createClaimRequest()` mehrfach auf, und ein
 * bestehender `pending`-Antrag wird dort wiederverwendet.
 *
 * Status `classified`: Portal, Betrieb und Typ liegen strukturiert vor, es
 * gibt nichts zu klassifizieren. Verifiziert wird über den Nachweis, den
 * der Antragsteller auf der nächsten Seite hochlädt
 * ({@see AttachClaimDocuments}).
 */
class RecordClaimRequest
{
    public function __invoke(ClaimRequest $claimRequest, Company $company, User $user, ?int $tenantId = null): ?ProfileRequest
    {
        $tenantId ??= tenant() !== null ? (int) tenant()->getKey() : null;
        $ref = (string) $claimRequest->getKey();

        $vorhanden = ProfileRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('type', RequestType::CLAIM)
            ->where('external_ref', $ref)
            ->first();

        if ($vorhanden instanceof ProfileRequest) {
            return $vorhanden;
        }

        try {
            $request = DB::connection((new ProfileRequest)->getConnectionName())->transaction(
                fn (): ProfileRequest => ProfileRequest::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $company->getKey(),
                    'type' => RequestType::CLAIM,
                    'source' => RequestSource::CLAIM_FLOW,
                    'status' => RequestStatus::CLASSIFIED,
                    'requester_name' => (string) $user->name,
                    'requester_email' => (string) $user->email,
                    'message' => $claimRequest->comment,
                    'external_ref' => $ref,
                ])
            );
        } catch (Throwable $e) {
            // Der Antrag selbst steht schon und ist im alten Panel
            // bearbeitbar — ein Fehler hier darf die Übernahme nicht
            // aufhalten.
            Log::warning('profile-requests: Übernahmeantrag nicht in die Inbox eingetragen', [
                'claim_request_id' => $claimRequest->getKey(),
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        $request->recordEvent(RequestEventType::NOTE, [
            'note' => "Übernahmeantrag #{$claimRequest->getKey()} aus dem Claim-Dialog. "
                .'Der Nachweis wird auf der Verifizierungsseite hochgeladen und danach hier angehängt.',
        ], RequestEvent::ACTOR_REQUESTER);

        // Sofortmeldung an uns (#23) — Übernahmen gehören zu den beiden
        // Typen, die nicht bis zum Tagesdigest warten.
        ProfileRequestReceived::dispatch($request);

        return $request;
    }
}
