<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Rules\NotDisposableEmailRule;
use App\AntiSpam\Support\RateLimitGuard;
use App\Models\Portal\Company;
use App\Services\ClaimService;
use App\Services\UserService;
use App\Turnstile\Concerns\InteractsWithTurnstile;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class ClaimModal extends Component
{
    // Honigtopf und Mindest-Ausfuellzeit (#26): die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht. Der
    // frueher hier handgeschriebene Honigtopf `website_url` ist damit weg —
    // sein Feldname war fest verdrahtet, eine Mindest-Ausfuellzeit gab es
    // nicht, und seine Treffer landeten in keinem Log.
    use InteractsWithAntiSpam;
    use InteractsWithTurnstile;

    public Company $company;

    // Modal state
    public bool $showModal = false;

    public string $scenario = 'guest'; // guest, logged_in_no_company, logged_in_has_company, already_claimed, pending_claim

    public string $activeTab = 'register'; // register, login

    public bool $claimSuccess = false;

    public ?Company $existingCompany = null;

    // Register fields (match blade wire:model names)
    public string $name = '';

    public string $email = '';

    public string $password = '';

    // Login fields
    public string $loginEmail = '';

    public string $loginPassword = '';

    public bool $remember = false;

    /**
     * Turnstile-Token der Uebernahme-Bestaetigung (#7), Aktion company_listing.
     * Eigene Property, weil ein Token immer nur fuer eine Aktion gilt: im
     * Registrierungs-Tab entsteht ein Konto (registration), beim Bestaetigen
     * entsteht ein Uebernahme-Antrag (company_listing). Beide Formulare sind
     * nie gleichzeitig sichtbar.
     */
    public string $claimToken = '';

    // Claim confirmation (Scenario 2 + 3)
    public bool $confirmOwner = false;

    #[On('openClaimModal')]
    public function openModal(): void
    {
        $this->resetState();

        $claimService = app(ClaimService::class);
        $user = Auth::user();

        $this->scenario = $claimService->getClaimScenario($user, $this->company);

        // Map internal scenario names to blade names
        if ($user && $this->scenario === 'has_company') {
            $this->existingCompany = Company::where('user_id', $user->id)->first();
            $this->scenario = 'logged_in_has_company';
        } elseif ($this->scenario === 'no_company') {
            $this->scenario = 'logged_in_no_company';
        } elseif ($this->scenario === 'not_logged_in') {
            $this->scenario = 'guest';
        }
        // pending_claim bleibt als pending_claim

        $this->showModal = true;
    }

    #[On('openClaimModalLogin')]
    public function openModalLogin(): void
    {
        $this->openModal();
        $this->activeTab = 'login';
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    /**
     * Scenario 1: Register + Claim-Request erstellen → Redirect zur Verifizierung
     */
    public function register(): void
    {
        // Honigtopf und Mindest-Ausfuellzeit (#26): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsmeldung, es entsteht aber kein Konto
        // und kein Antrag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::Registration, $this->email)) {
            $this->claimSuccess = true;

            return;
        }

        // Rate-Limit je IP-Hash, Mailadresse und Portal (#26), Grenzen in
        // config/antispam.php. Ersetzt den handgeschriebenen Zaehler, der auf
        // der Klartext-IP, ohne Portal-Praefix und nur je IP lief.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::CLAIM_REGISTRATION, ['email' => $this->email]);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::CLAIM_REGISTRATION, TurnstileAction::Registration, $this->email);
            $this->addError('email', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            // Wegwerf-Adressen sichtbar abweisen (#26). Dieser Weg legt ein
            // Konto an, ohne durch App\Validator\RegisterValidator zu laufen,
            // deshalb haengt die Regel hier eigens.
            'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users,email', new NotDisposableEmailRule(TurnstileAction::Registration)],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Bitte geben Sie Ihren Namen ein.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email.unique' => 'Diese E-Mail-Adresse ist bereits registriert. Bitte melden Sie sich an.',
            'password.required' => 'Bitte wählen Sie ein Passwort.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        // Turnstile zuletzt: erst wenn alle Felder stimmen, wird ein Token
        // eingeloest — Cloudflare nimmt jedes Token nur einmal an. Kontoanlage
        // wie auf /register (#6).
        $this->validateTurnstile(TurnstileAction::Registration);

        /** @var UserService $userService */
        $userService = app(UserService::class);

        $user = $userService->createUser([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        // Registered Event
        event(new Registered($user));

        // Claim-Request erstellen (NICHT direkt zuweisen!)
        $claimService = app(ClaimService::class);
        $claimRequest = $claimService->createClaimRequest($user, $this->company);

        if ($claimRequest === false) {
            $this->addError('email', 'Die Anfrage konnte nicht erstellt werden. Bitte versuchen Sie es erneut.');

            return;
        }

        // Login
        Auth::login($user, $this->remember);

        // E-Mail-Verifizierung senden
        $user->sendEmailVerificationNotification();

        // Session-Kontext für Verifizierungsseite
        session()->put('claim_request_id', $claimRequest->id);
        session()->put('claimed_company_id', $this->company->id);
        session()->put('claimed_company_name', $this->company->name);

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten, ein angelegtes Konto schon.
        $this->antiSpamCount(RateLimitGuard::CLAIM_REGISTRATION, ['email' => $this->email]);

        $this->claimSuccess = true;
    }

    /**
     * Scenario 1: Login + Claim-Request erstellen → Redirect zur Verifizierung
     */
    public function login(): void
    {
        // Honigtopf und Mindest-Ausfuellzeit (#26): still abweisen. Die
        // Aktion dient nur dem Log — zu TurnstileAction gibt es kein `login`,
        // und der Anmeldereiter gehoert zum Kontoschritt der Uebernahme.
        if ($this->antiSpamRejects(TurnstileAction::Registration, $this->loginEmail)) {
            $this->claimSuccess = true;

            return;
        }

        // Rate-Limit je IP-Hash UND Mailadresse, mit Portal-Praefix (#26).
        // Eigener Zaehler, damit der Reiter die zentrale Anmeldung nicht
        // mitsperrt; Grenze in config/antispam.php.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::CLAIM_LOGIN, ['email' => $this->loginEmail]);

        if ($wartezeit !== null) {
            // Nur ins Laravel-Log, nicht nach turnstile_verifications: die
            // Spalte `action` kennt nur die TurnstileAction-Werte, und ein
            // Anmeldeversuch ist keiner davon (wie im LoginController).
            Log::info('Antispam: Rate-Limit erreicht', [
                'limiter' => RateLimitGuard::CLAIM_LOGIN,
                'sekunden' => $wartezeit,
            ]);
            $this->addError('loginEmail', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        $this->validate([
            'loginEmail' => ['required', 'string', 'email'],
            'loginPassword' => ['required', 'string'],
        ], [
            'loginEmail.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'loginEmail.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'loginPassword.required' => 'Bitte geben Sie Ihr Passwort ein.',
        ]);

        if (! Auth::attempt([
            'email' => strtolower($this->loginEmail),
            'password' => $this->loginPassword,
        ], $this->remember)) {
            // Gezaehlt wird der Fehlversuch, nicht das Absenden: ein
            // Tippfehler im Formular soll keinen Versuch kosten, ein falsches
            // Passwort schon.
            $this->antiSpamCount(RateLimitGuard::CLAIM_LOGIN, ['email' => $this->loginEmail]);
            $this->addError('loginEmail', 'E-Mail-Adresse oder Passwort ist falsch.');

            return;
        }

        $user = Auth::user();

        // Re-evaluate nach Login
        $claimService = app(ClaimService::class);
        $newScenario = $claimService->getClaimScenario($user, $this->company);

        if ($newScenario === 'already_claimed') {
            $this->scenario = 'already_claimed';

            return;
        }

        if ($newScenario === 'pending_claim') {
            $this->scenario = 'pending_claim';

            return;
        }

        // Claim-Request erstellen
        $claimRequest = $claimService->createClaimRequest($user, $this->company);

        if ($claimRequest === false) {
            $this->addError('loginEmail', 'Die Anfrage konnte nicht erstellt werden.');

            return;
        }

        session()->put('claim_request_id', $claimRequest->id);
        session()->put('claimed_company_id', $this->company->id);
        session()->put('claimed_company_name', $this->company->name);

        $this->claimSuccess = true;
    }

    /**
     * Scenario 2: Eingeloggt, keine Firma → Claim-Request erstellen
     */
    public function claim(): void
    {
        if ($this->claimGuardRejects()) {
            return;
        }

        $this->validate([
            'confirmOwner' => ['accepted'],
        ], [
            'confirmOwner.accepted' => 'Bitte bestätigen Sie, dass Sie der Inhaber sind.',
        ]);

        $this->validateClaimToken();

        $user = Auth::user();
        $claimService = app(ClaimService::class);

        $claimRequest = $claimService->createClaimRequest($user, $this->company);

        if ($claimRequest === false) {
            $this->addError('confirmOwner', 'Die Anfrage konnte nicht erstellt werden. Bitte versuchen Sie es erneut.');

            return;
        }

        session()->put('claim_request_id', $claimRequest->id);
        session()->put('claimed_company_id', $this->company->id);
        session()->put('claimed_company_name', $this->company->name);

        $this->antiSpamCount(RateLimitGuard::CLAIM);

        $this->claimSuccess = true;
    }

    /**
     * Scenario 3: Eingeloggt, hat bereits Firma → Zusätzlich claimen
     */
    public function claimAdditional(): void
    {
        if ($this->claimGuardRejects()) {
            return;
        }

        $this->validate([
            'confirmOwner' => ['accepted'],
        ], [
            'confirmOwner.accepted' => 'Bitte bestätigen Sie, dass Sie der Inhaber sind.',
        ]);

        $this->validateClaimToken();

        $user = Auth::user();
        $claimService = app(ClaimService::class);

        $claimRequest = $claimService->createClaimRequest($user, $this->company);

        if ($claimRequest === false) {
            $this->addError('confirmOwner', 'Die Anfrage konnte nicht erstellt werden. Bitte versuchen Sie es erneut.');

            return;
        }

        session()->put('claim_request_id', $claimRequest->id);
        session()->put('claimed_company_id', $this->company->id);
        session()->put('claimed_company_name', $this->company->name);

        $this->antiSpamCount(RateLimitGuard::CLAIM);

        $this->claimSuccess = true;
    }

    /**
     * Honigtopf, Mindest-Ausfuellzeit und Rate-Limit des angemeldeten Wegs
     * (#26). True heisst: der Aufrufer hoert auf. Eigener Limiter, weil hier
     * kein Konto entsteht, sondern ein Uebernahme-Antrag — und weil der
     * Zaehler fuer Angemeldete auf der Nutzerkennung laeuft, nicht nur auf
     * der Herkunft.
     */
    private function claimGuardRejects(): bool
    {
        // Still abweisen: dieselbe Erfolgsmeldung, kein Antrag.
        if ($this->antiSpamRejects(TurnstileAction::CompanyListing, Auth::user()?->email)) {
            $this->claimSuccess = true;

            return true;
        }

        $wartezeit = $this->antiSpamLimit(RateLimitGuard::CLAIM);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::CLAIM, TurnstileAction::CompanyListing, Auth::user()?->email);
            $this->addError('confirmOwner', $this->antiSpamThrottleMessage($wartezeit));

            return true;
        }

        return false;
    }

    /**
     * Turnstile der Uebernahme (#7), eigene Property `claimToken`: ein Token
     * gilt nur fuer eine Aktion. Eigener Aufruf nach den Feldregeln, weil ein
     * Token bei Cloudflare nur einmal einloesbar ist und nicht von einem
     * fehlenden Haken verbraucht werden soll.
     */
    private function validateClaimToken(): void
    {
        $this->validate([
            'claimToken' => [new TurnstileRule(TurnstileAction::CompanyListing)],
        ]);
    }

    /**
     * Scenario 4: Dispute beantragen
     */
    public function requestDispute(): void
    {
        // Rate-Limit je IP-Hash und Portal (#26), Grenze in
        // config/antispam.php. Sichtbar, nicht still: ein Einspruch ist ein
        // Anliegen eines Menschen, da darf die Restzeit stehen.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::CLAIM_DISPUTE);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::CLAIM_DISPUTE, TurnstileAction::CompanyListing, Auth::user()?->email);
            $this->dispatch('toast', type: 'error', message: $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        $this->antiSpamCount(RateLimitGuard::CLAIM_DISPUTE);

        // TODO: E-Mail an Admin senden / Dispute-Record erstellen
        $this->dispatch('toast', type: 'success', message: 'Ihre Anfrage wurde gesendet. Wir melden uns innerhalb von 48 Stunden.');
        $this->closeModal();
    }

    /**
     * Redirect zur Verifizierungsseite nach erfolgreichem Claim-Request
     */
    public function goToVerification(): void
    {
        $this->redirect(route('companies.claim-verification', ['slug' => $this->company->slug]), navigate: false);
    }

    /**
     * Redirect ins Dashboard (für pending_claim Szenario)
     */
    public function goToDashboard(): void
    {
        $this->redirect('/verwaltung');
    }

    private function resetState(): void
    {
        $this->resetValidation();
        $this->activeTab = 'register';
        $this->claimSuccess = false;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->loginEmail = '';
        $this->loginPassword = '';
        $this->remember = false;
        $this->antispamHoneypot = '';
        $this->turnstileToken = '';
        $this->claimToken = '';
        $this->confirmOwner = false;
        $this->existingCompany = null;
    }

    public function render()
    {
        return view('livewire.portal.claim-modal');
    }
}
