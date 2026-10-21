<?php

declare(strict_types=1);

namespace App\ProfileRequests\Verification;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Actions\ApproveClaim;
use App\ProfileRequests\AutoRules\RuleRunner;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Mail\VerifyRequestMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifikation einer Anfrage (SUN-RQ #6; Regeln in
 * docs/profile-requests.md 4).
 *
 * Eine Stelle fuer alle Quellen, in dieser Reihenfolge:
 *
 *  1. **Domain-Match** ({@see SenderDomainMatcher}) — nur fuer Quelle
 *     `email`. Die Absenderadresse der Mail ist die einzige Adresse im
 *     Verfahren, die nicht jemand in ein Formular tippen kann; beim Formular
 *     ist die Adresse Eingabe und beweist nichts. Trifft die Regel, geht die
 *     Anfrage ohne Mail direkt auf `verified`.
 *  2. **Magic-Link** an die **beim Profil hinterlegte** Firmen-E-Mail — nie
 *     an die Adresse des Anfragenden. Wer den Link klickt, hat Zugriff auf
 *     das Firmenpostfach; darin liegt der ganze Beweis. Hoechstens
 *     `verification.max_links_per_company_per_day` Mails je Profil und Tag.
 *  3. **Handpruefung** — kein Treffer, keine Firmen-E-Mail, Limit erreicht.
 *     Der Vorgang steht auf `awaiting_verification` mit Kennzeichen
 *     `manual`, Enes entscheidet im Panel.
 *
 * Nach erfolgreicher Verifikation laeuft die Folgeautomatik: eine Uebernahme
 * wird freigegeben ({@see ApproveClaim}), Aenderungen und Loeschungen gehen
 * in das Regelwerk der Auto-Erledigung ({@see RuleRunner}, #9). Was dort
 * keine Regel trifft, bleibt auf `verified` und wartet im Panel (#7).
 */
class RequestVerifier
{
    public function __construct(
        private readonly SenderDomainMatcher $matcher,
        private readonly ApproveClaim $approveClaim,
        private readonly TenantCompanyResolver $resolver,
    ) {}

    /**
     * Den Vorgang einen Schritt weiterbringen. Rueckgabe ist der Weg, der
     * gegriffen hat, oder null, wenn gar nichts zu tun war.
     */
    public function handle(ProfileRequest $request): ?VerificationMethod
    {
        if ($request->status !== RequestStatus::CLASSIFIED) {
            // `new` wartet auf die Klassifikation, alles ab
            // `awaiting_verification` ist schon unterwegs oder entschieden.
            return null;
        }

        $company = $request->company();

        if ($this->domainMatches($request, $company)) {
            $this->markVerified($request, VerificationMethod::SENDER_DOMAIN, (string) $request->requester_email);

            return VerificationMethod::SENDER_DOMAIN;
        }

        $companyEmail = $this->companyEmail($company);

        if ($companyEmail === null) {
            $request->requireManualVerification('keine_firmen_email');

            return VerificationMethod::MANUAL;
        }

        if ($this->dailyLinkLimitReached($request)) {
            $request->requireManualVerification('mail_limit_erreicht');

            return VerificationMethod::MANUAL;
        }

        $token = $request->requestVerification($companyEmail);

        if ($token === null) {
            return null;
        }

        $this->sendMagicLink($request, $companyEmail, $token);

        return VerificationMethod::MAGIC_LINK;
    }

    /**
     * Neuen Magic-Link fuer einen wartenden Vorgang — die Seite „Link
     * abgelaufen, neu anfordern". Rueckgabe false, wenn der Vorgang nicht
     * (mehr) wartet, keine Firmen-E-Mail hat oder das Tageslimit erreicht
     * ist.
     */
    public function resendMagicLink(ProfileRequest $request): bool
    {
        if ($request->status !== RequestStatus::AWAITING_VERIFICATION) {
            return false;
        }

        $companyEmail = $this->companyEmail($request->company());

        if ($companyEmail === null || $this->dailyLinkLimitReached($request)) {
            return false;
        }

        $token = $request->refreshVerificationToken($companyEmail);

        if ($token === null) {
            return false;
        }

        $this->sendMagicLink($request, $companyEmail, $token);

        return true;
    }

    /**
     * Verifikation festschreiben und die Folgeautomatik anstossen. Der Weg
     * fuer den Magic-Link-Klick (Controller) und den Domain-Match.
     */
    public function markVerified(
        ProfileRequest $request,
        VerificationMethod $method,
        ?string $verifiedEmail = null,
        string $actorType = RequestEvent::ACTOR_REQUESTER,
    ): bool {
        if (! $request->verify($method, $verifiedEmail, $actorType)) {
            return false;
        }

        $this->afterVerified($request);

        return true;
    }

    /**
     * Was nach der Verifikation von selbst laeuft:
     *
     *  * `claim` — die Uebernahme wird freigegeben (#6). Scheitert das,
     *    bleibt der Vorgang auf `verified` und wartet im Panel — eine halbe
     *    Uebernahme gibt es nicht.
     *  * alles andere — das Regelwerk der Auto-Erledigung (#9) entscheidet:
     *    Aenderung an Whitelist-Feldern uebernehmen, Loeschung in die
     *    Karenzfrist stellen, sonst liegenlassen.
     */
    private function afterVerified(ProfileRequest $request): void
    {
        if ($request->type !== RequestType::CLAIM) {
            app(RuleRunner::class)->run($request);

            return;
        }

        try {
            ($this->approveClaim)($request);
        } catch (Throwable $e) {
            Log::warning('profile-requests: Claim-Automatik fehlgeschlagen', [
                'request_id' => $request->id,
                'exception' => $e->getMessage(),
            ]);

            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Claim-Automatik fehlgeschlagen, Freigabe von Hand noetig',
                'error' => Str::limit($e->getMessage(), 300),
            ]);
        }
    }

    /**
     * Domain-Match, aber nur fuer eine eingegangene Mail.
     */
    private function domainMatches(ProfileRequest $request, ?Company $company): bool
    {
        if ($request->source !== RequestSource::EMAIL) {
            return false;
        }

        $check = $this->matcher->check($request->requester_email, $company);

        $request->recordEvent(RequestEventType::NOTE, [
            'note' => 'Absender-Domain geprüft',
            'treffer' => $check['matched'],
            'grund' => $check['reason'],
            'absender_domain' => $check['domain'],
        ]);

        return $check['matched'];
    }

    /**
     * Die beim Profil hinterlegte Firmen-E-Mail — die einzige Adresse, an die
     * ein Magic-Link gehen darf.
     */
    private function companyEmail(?Company $company): ?string
    {
        $email = Str::lower(trim((string) ($company->email ?? '')));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    /**
     * Wie viele Bestaetigungsmails gingen heute schon an dieses Profil?
     * Gezaehlt wird am Protokoll und ueber alle Vorgaenge des Profils, nicht
     * je Anfrage — sonst reichen drei Formularabsendungen fuer drei Mails.
     */
    private function dailyLinkLimitReached(ProfileRequest $request): bool
    {
        if ($request->tenant_id === null || $request->company_id === null) {
            return false;
        }

        $max = (int) config('profile-requests.verification.max_links_per_company_per_day', 3);

        if ($max <= 0) {
            return false;
        }

        $count = RequestEvent::query()
            ->where('event', RequestEventType::VERIFICATION_REQUESTED->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('request_id', ProfileRequest::query()
                ->select('id')
                ->where('tenant_id', $request->tenant_id)
                ->where('company_id', $request->company_id))
            ->count();

        return $count >= $max;
    }

    /**
     * Bestaetigungsmail mit Magic-Link und Stornolink. Der Klartext des
     * Tokens existiert nur hier und in der Mail.
     */
    private function sendMagicLink(ProfileRequest $request, string $companyEmail, string $token): void
    {
        $tenant = $request->tenant_id === null ? null : $this->resolver->tenant((int) $request->tenant_id);
        $expiresAt = $request->verification_expires_at ?? now()->addHours((int) config('profile-requests.verification_ttl_hours', 72));

        $links = $this->links($request, $token, $tenant);

        try {
            Mail::to($companyEmail)->send(new VerifyRequestMail(
                request: $request,
                tenant: $tenant,
                companyName: (string) ($request->company()->name ?? ''),
                verifyUrl: $links['verify'],
                cancelUrl: $links['cancel'],
                expiresAt: $expiresAt,
            ));
        } catch (Throwable $e) {
            // Die Anfrage bleibt bestehen und wartet; ohne Mail laeuft sie in
            // den Ablauf und taucht im Panel unter „Abgelaufen" auf.
            Log::warning('profile-requests: Bestaetigungsmail nicht versendet', [
                'request_id' => $request->id,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        $request->recordEvent(RequestEventType::MAIL_SENT, [
            'mail' => 'verify_request',
            // Nur die Zieladresse, nie der Token oder der Link.
            'to' => $companyEmail,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Die beiden Links der Mail, erzeugt auf der Portal-Domain.
     *
     * Der Host muss stimmen, bevor signiert wird: Laravel prueft die
     * Signatur gegen die tatsaechlich aufgerufene URL. Eine zentral erzeugte
     * und danach umgeschriebene Adresse waere ungueltig — und eine fremde
     * Domain in der Bestaetigungsmail eines Portals sieht ohnehin aus wie
     * Phishing.
     *
     * @return array{verify: string, cancel: string}
     */
    private function links(ProfileRequest $request, string $token, ?Tenant $tenant): array
    {
        $root = rtrim(TenantMailBranding::for($tenant)->url('/'), '/');
        $previous = rtrim(url('/'), '/');
        $ttlHours = (int) config('profile-requests.verification_ttl_hours', 72);

        URL::forceRootUrl($root);

        try {
            return [
                'verify' => URL::temporarySignedRoute(
                    'profile-requests.verify',
                    now()->addHours($ttlHours),
                    ['request' => $request->getKey(), 'token' => $token],
                ),
                'cancel' => URL::temporarySignedRoute(
                    'profile-requests.cancel',
                    now()->addHours($ttlHours),
                    ['request' => $request->getKey(), 'token' => $token],
                ),
            ];
        } finally {
            URL::forceRootUrl($previous);
        }
    }
}
