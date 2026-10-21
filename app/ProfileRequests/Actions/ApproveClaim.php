<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\TenantCompanyResolver;
use App\Services\ClaimService;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Claim-Automatik (SUN-RQ #6; Entscheidung in docs/profile-requests.md 7).
 *
 * Eine **verifizierte** Uebernahme wird ohne Enes freigegeben. Die fachliche
 * Logik dafuer existiert bereits und wird nicht neu gebaut: der
 * {@see ClaimService} legt den Antrag an, haengt den Nutzer an den Tenant,
 * vergibt die Rolle `company_owner` und verschickt die Freigabemail. Diese
 * Aktion ist nur die Klammer darum — sie sorgt dafuer, dass es einen Antrag
 * und ein Konto gibt, und schreibt den Vorgang danach auf `applied`.
 *
 * Drei Faelle:
 *
 *  * Antrag aus dem Claim-Dialog (`external_ref` zeigt auf
 *    `claim_requests.id`): vorhandenen Antrag freigeben.
 *  * Anfrage per Mail oder Formular mit vorhandenem Konto: Antrag anlegen und
 *    sofort freigeben.
 *  * Anfrage ohne Konto: Konto anlegen, Antrag anlegen, freigeben und einen
 *    Link zum Setzen des Passworts schicken — „Zugang anlegen".
 *
 * Abgebrochen wird, wenn der Betrieb schon einem **anderen** Konto gehoert.
 * Dann bleibt der Vorgang auf `verified` und wartet im Panel: zwei Inhaber
 * sind ein Fall fuer einen Menschen.
 *
 * `claim_requests.reviewed_by` traegt in diesem Weg 0 — die Spalte ist eine
 * Nutzer-ID, und es gibt keinen Pruefer. Das Protokoll des Vorgangs
 * (`request_events`) haelt fest, dass es die Automatik war.
 */
class ApproveClaim
{
    /** Wert in `claim_requests.reviewed_by`, wenn die Automatik freigibt. */
    public const SYSTEM_REVIEWER_ID = 0;

    public function __construct(
        private readonly ClaimService $claimService,
        private readonly UserService $userService,
        private readonly TenantCompanyResolver $resolver,
    ) {}

    public function __invoke(ProfileRequest $request): bool
    {
        if ($request->type !== RequestType::CLAIM || ! $request->isVerified()) {
            return false;
        }

        if ($request->tenant_id === null || $request->company_id === null) {
            $this->note($request, 'Übernahme ohne Portal oder Betrieb — Zuordnung fehlt');

            return false;
        }

        $email = Str::lower(trim((string) $request->requester_email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->note($request, 'Übernahme ohne brauchbare Absenderadresse');

            return false;
        }

        $result = $this->resolver->runFor((int) $request->tenant_id, function () use ($request, $email): array {
            return $this->run($request, $email);
        });

        if ($result === null || $result['ok'] !== true) {
            $this->note($request, $result['note'] ?? 'Übernahme konnte nicht freigegeben werden');

            return false;
        }

        // Der Passwortlink **nach** dem Tenant-Kontext: der Token-Speicher
        // des Brokers (`password_reset_tokens`) liegt in der Central-DB, und
        // innerhalb von Tenant::run() zeigt die Standardverbindung auf die
        // Tenant-DB — dort gibt es die Tabelle nicht.
        if ($result['user_created'] === true) {
            $this->sendPasswordLink($request, $email);
        }

        $request->apply(null, [
            'action' => 'claim_approved',
            'claim_request_id' => $result['claim_request_id'],
            'user_id' => $result['user_id'],
            'konto_neu' => $result['user_created'],
        ]);

        return true;
    }

    /**
     * Der Teil im Tenant-Kontext: Betrieb, Konto, Antrag, Freigabe.
     *
     * @return array{ok: bool, note?: string, claim_request_id?: int|null, user_id?: int|null, user_created?: bool}
     */
    private function run(ProfileRequest $request, string $email): array
    {
        $company = Company::find($request->company_id);

        if ($company === null) {
            return ['ok' => false, 'note' => 'Betrieb im Portal nicht gefunden'];
        }

        [$user, $created] = $this->user($email, $request, $company);

        if ($company->user_id !== null && (int) $company->user_id !== (int) $user->getKey()) {
            return ['ok' => false, 'note' => 'Betrieb gehört bereits einem anderen Konto — Freigabe von Hand'];
        }

        $claimRequest = $this->claimRequest($request, $company, $user);

        if ($claimRequest === null) {
            return ['ok' => false, 'note' => 'Übernahmeantrag konnte nicht angelegt werden (Besitzregel greift)'];
        }

        if ($claimRequest->status !== ClaimRequest::STATUS_PENDING) {
            // Schon freigegeben — etwa weil Enes schneller war.
            return [
                'ok' => $claimRequest->status === ClaimRequest::STATUS_APPROVED,
                'note' => 'Übernahmeantrag steht auf '.$claimRequest->status,
                'claim_request_id' => (int) $claimRequest->getKey(),
                'user_id' => (int) $user->getKey(),
                'user_created' => $created,
            ];
        }

        if (! $this->claimService->approveClaimRequest($claimRequest, self::SYSTEM_REVIEWER_ID)) {
            return ['ok' => false, 'note' => 'ClaimService hat die Freigabe abgelehnt'];
        }

        $request->forceFill(['external_ref' => (string) $claimRequest->getKey()])->save();

        return [
            'ok' => true,
            'claim_request_id' => (int) $claimRequest->getKey(),
            'user_id' => (int) $user->getKey(),
            'user_created' => $created,
        ];
    }

    /**
     * Konto zur Anfrage — vorhandenes verknuepfen oder neues anlegen.
     *
     * Das neue Konto gilt als bestaetigt: die Verifikation lief ueber die im
     * Profil hinterlegte Firmen-E-Mail bzw. die Firmendomain und ist damit
     * strenger als eine Bestaetigungsmail an die selbst eingetippte Adresse.
     * Ein Passwort wird nicht gesetzt, sondern per Link gewaehlt — wir legen
     * nie ein Passwort fuer jemanden fest.
     *
     * @return array{0: User, 1: bool}
     */
    private function user(string $email, ProfileRequest $request, Company $company): array
    {
        $existing = $this->userService->findByEmail($email);

        if ($existing instanceof User) {
            return [$existing, false];
        }

        $user = $this->userService->createUser([
            'name' => trim((string) $request->requester_name) !== ''
                ? (string) $request->requester_name
                : (string) $company->name,
            'email' => $email,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, true];
    }

    /**
     * Der Uebernahmeantrag in der Tenant-DB: vorhandener aus dem
     * Claim-Dialog oder ein neu angelegter.
     */
    private function claimRequest(ProfileRequest $request, Company $company, User $user): ?ClaimRequest
    {
        $ref = (string) $request->external_ref;

        if (ctype_digit($ref)) {
            $vorhanden = ClaimRequest::find((int) $ref);

            if ($vorhanden instanceof ClaimRequest) {
                return $vorhanden;
            }
        }

        $angelegt = $this->claimService->createClaimRequest(
            $user,
            $company,
            'Automatisch aus Anfrage REQ-'.$request->id.' ('.($request->verification_method?->value ?? 'verifiziert').')',
        );

        return $angelegt instanceof ClaimRequest ? $angelegt : null;
    }

    /**
     * Link zum Setzen des Passworts. Scheitert der Versand, bleibt die
     * Uebernahme bestehen — der Nutzer kann ihn auf der Anmeldeseite selbst
     * anfordern, und genau das steht dann im Protokoll.
     */
    private function sendPasswordLink(ProfileRequest $request, string $email): void
    {
        try {
            Password::broker()->sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            Log::warning('profile-requests: Passwortlink nach Claim nicht versendet', [
                'request_id' => $request->id,
                'exception' => $e->getMessage(),
            ]);

            $this->note($request, 'Passwortlink konnte nicht versendet werden');
        }
    }

    private function note(ProfileRequest $request, string $note): void
    {
        $request->recordEvent(RequestEventType::NOTE, ['note' => $note]);
    }
}
