<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\RequestLinks;
use App\ProfileRequests\Verification\RequestVerifier;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Magic-Link und Stornolink einer Profil-Anfrage (SUN-RQ #6).
 *
 * Zwei Pruefungen, beide muessen stimmen:
 *
 *  1. **Signatur** der URL (`temporarySignedRoute`) — sie belegt, dass der
 *     Link von uns stammt und nicht veraendert wurde.
 *  2. **Token-Hash** gegen `profile_requests.verification_token_hash` — er
 *     belegt, dass es der aktuelle Link dieses Vorgangs ist. Nur so entwertet
 *     ein neu angeforderter Link den alten.
 *
 * Der Klartext des Tokens steht nur in der Mail; in der Datenbank liegt der
 * SHA-256. Verglichen wird mit `hash_equals`.
 *
 * Ein abgelaufener Link wird **nicht** stillschweigend zum Fehler: die Seite
 * sagt, dass er abgelaufen ist, und bietet einen neuen an. Den Vorgang auf
 * `expired` setzt allein der Scheduler (`requests:expire`) — sonst koennte
 * ein verspaeteter Klick den Vorgang schliessen, bevor der Betrieb einen
 * neuen Link anfordern kann.
 *
 * Alle drei Endpunkte sind durchgesetzt (`throttle`), weil sie ohne Anmeldung
 * erreichbar sind und der eine von ihnen eine Mail versendet.
 */
class RequestVerificationController extends Controller
{
    public function __construct(
        private readonly RequestVerifier $verifier,
    ) {}

    /** Klick auf den Magic-Link: Anfrage ist vom Betrieb autorisiert. */
    public function verify(Request $httpRequest, int $request, string $token): View
    {
        $profileRequest = $this->resolve($httpRequest, $request, $token);

        if (! $profileRequest instanceof ProfileRequest) {
            return $this->page('invalid', null);
        }

        if ($profileRequest->status !== RequestStatus::AWAITING_VERIFICATION) {
            return $this->page($profileRequest->isVerified() ? 'already' : 'closed', $profileRequest);
        }

        if ($this->expired($httpRequest, $profileRequest)) {
            return $this->page('expired', $profileRequest);
        }

        $this->verifier->markVerified(
            $profileRequest,
            VerificationMethod::MAGIC_LINK,
            $profileRequest->company()->email ?? null,
        );

        return $this->page('verified', $profileRequest->refresh());
    }

    /** Klick auf den Stornolink: der Betrieb widerspricht. */
    public function cancel(Request $httpRequest, int $request, string $token): View
    {
        $profileRequest = $this->resolve($httpRequest, $request, $token);

        if (! $profileRequest instanceof ProfileRequest) {
            return $this->page('invalid', null);
        }

        if ($profileRequest->status === RequestStatus::CANCELLED) {
            return $this->page('cancelled', $profileRequest);
        }

        if (! $profileRequest->isOpen()) {
            return $this->page('closed', $profileRequest);
        }

        // Ein Widerspruch zaehlt auch nach Ablauf des Links: er nimmt nichts
        // weg, er verhindert nur etwas.
        $profileRequest->cancel('Vom Betrieb über den Stornolink abgelehnt');

        return $this->page('cancelled', $profileRequest->refresh());
    }

    /**
     * „Link abgelaufen, neu anfordern": neuer Magic-Link an dieselbe
     * Firmen-E-Mail. Mehr als
     * `verification.max_links_per_company_per_day` Mails gibt es nicht.
     */
    public function resendLink(Request $httpRequest, int $request): RedirectResponse
    {
        if (! $httpRequest->hasValidSignature()) {
            abort(403);
        }

        $profileRequest = ProfileRequest::find($request);

        if (! $profileRequest instanceof ProfileRequest) {
            abort(404);
        }

        $sent = $this->verifier->resendMagicLink($profileRequest);

        return back()->with($sent ? 'status' : 'error', $sent
            ? 'Wir haben Ihnen einen neuen Bestätigungslink an die im Eintrag hinterlegte E-Mail-Adresse geschickt.'
            : 'Ein neuer Link ist im Moment nicht möglich. Bitte wenden Sie sich an uns.');
    }

    /**
     * Vorgang zum Link oder null. Prueft Signatur (ohne Ablauf, der wird
     * getrennt behandelt) und Token-Hash.
     */
    private function resolve(Request $httpRequest, int $requestId, string $token): ?ProfileRequest
    {
        // Signatur ohne Ablaufpruefung: ein abgelaufener Link ist kein
        // gefaelschter Link und bekommt eine eigene Seite.
        if (! URL::hasCorrectSignature($httpRequest)) {
            return null;
        }

        $profileRequest = ProfileRequest::find($requestId);

        if (! $profileRequest instanceof ProfileRequest) {
            return null;
        }

        $hash = (string) $profileRequest->verification_token_hash;

        if ($hash === '' || ! hash_equals($hash, ProfileRequest::hashToken($token))) {
            // Kein Token mehr (schon benutzt, storniert, abgelaufen) oder ein
            // alter Link, den ein neuer ersetzt hat.
            return $profileRequest->verification_token_hash === null ? $profileRequest : null;
        }

        return $profileRequest;
    }

    /**
     * Abgelaufen ist der Link, wenn die Signatur abgelaufen ist oder der
     * Vorgang eine Frist hat, die vorbei ist.
     */
    private function expired(Request $httpRequest, ProfileRequest $profileRequest): bool
    {
        if (! URL::signatureHasNotExpired($httpRequest)) {
            return true;
        }

        return $profileRequest->hasExpiredVerification();
    }

    /**
     * Statusseite. Minimal und ohne Theme: die Seite wird auf jeder
     * Portal-Domain ausgeliefert, ohne Tenant-Kontext, und darf an keinem
     * Portal-Asset haengen. Die ausgebaute Fassung bringt #10.
     */
    private function page(string $state, ?ProfileRequest $profileRequest): View
    {
        $tenant = $profileRequest?->tenant;
        $branding = TenantMailBranding::for($tenant);

        $resendUrl = null;

        if ($state === 'expired' && $profileRequest !== null) {
            // Ohne Ablauf signiert: die Seite ist genau dann da, wenn der
            // Link schon abgelaufen ist.
            $resendUrl = URL::signedRoute('profile-requests.verify.resend', ['request' => $profileRequest->getKey()]);
        }

        if ($state === 'verified' && $profileRequest !== null) {
            $profileRequest->recordEvent(
                RequestEventType::NOTE,
                ['note' => 'Bestätigungslink geklickt'],
                RequestEvent::ACTOR_REQUESTER,
            );
        }

        return view('profile-requests.verification', [
            'state' => $state,
            'profileRequest' => $profileRequest,
            // Weiter zur ausgebauten Statusseite (#10): Zeitstrahl,
            // eingereichte Werte und der Stornoweg stehen dort.
            'statusUrl' => $profileRequest === null ? null : RequestLinks::status($profileRequest, $tenant),
            'portalName' => $branding->portalName(),
            'portalUrl' => $branding->url('/'),
            'brandColor' => $branding->brandColor(),
            'resendUrl' => $resendUrl,
        ]);
    }
}
