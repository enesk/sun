<?php

namespace App\Livewire\Portal;

use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

class ClaimModal extends Component
{
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

    // Honeypot
    public string $website_url = '';

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
        // Honeypot
        if (!empty($this->website_url)) {
            $this->claimSuccess = true;
            return;
        }

        // Rate Limiting: 5 Registrierungen pro IP pro Stunde
        $ip = request()->ip();
        $rateLimitKey = 'claim-register:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            $this->addError('email', "Zu viele Versuche. Bitte versuchen Sie es in {$minutes} Minuten erneut.");
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Bitte geben Sie Ihren Namen ein.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email.unique' => 'Diese E-Mail-Adresse ist bereits registriert. Bitte melden Sie sich an.',
            'password.required' => 'Bitte wählen Sie ein Passwort.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        RateLimiter::hit($rateLimitKey, 3600);

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

        $this->claimSuccess = true;
    }

    /**
     * Scenario 1: Login + Claim-Request erstellen → Redirect zur Verifizierung
     */
    public function login(): void
    {
        // Honeypot
        if (!empty($this->website_url)) {
            $this->claimSuccess = true;
            return;
        }

        // Rate Limiting: 10 Login-Versuche pro IP pro Stunde
        $ip = request()->ip();
        $rateLimitKey = 'claim-login:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            $this->addError('loginEmail', "Zu viele Versuche. Bitte versuchen Sie es in {$minutes} Minuten erneut.");
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

        RateLimiter::hit($rateLimitKey, 3600);

        if (!Auth::attempt([
            'email' => strtolower($this->loginEmail),
            'password' => $this->loginPassword,
        ], $this->remember)) {
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
        $this->validate([
            'confirmOwner' => ['accepted'],
        ], [
            'confirmOwner.accepted' => 'Bitte bestätigen Sie, dass Sie der Inhaber sind.',
        ]);

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

        $this->claimSuccess = true;
    }

    /**
     * Scenario 3: Eingeloggt, hat bereits Firma → Zusätzlich claimen
     */
    public function claimAdditional(): void
    {
        $this->validate([
            'confirmOwner' => ['accepted'],
        ], [
            'confirmOwner.accepted' => 'Bitte bestätigen Sie, dass Sie der Inhaber sind.',
        ]);

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

        $this->claimSuccess = true;
    }

    /**
     * Scenario 4: Dispute beantragen
     */
    public function requestDispute(): void
    {
        // Rate Limiting
        $ip = request()->ip();
        $rateLimitKey = 'claim-dispute:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

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
        $this->website_url = '';
        $this->confirmOwner = false;
        $this->existingCompany = null;
    }

    public function render()
    {
        return view('livewire.portal.claim-modal');
    }
}
