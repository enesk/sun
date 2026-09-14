<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\NewCompanyNotifier;
use App\Services\TenantPermissionService;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * "Trag deinen Betrieb ein" (/eintragen, Theme sun-v2): zwei Schritte in
 * einer Karte. Schritt 1 Betrieb, Schritt 2 Konto; beide validieren erst
 * beim Weiterklicken. Der Eintrag entsteht inaktiv und wird in der
 * Verwaltung freigeschaltet (Firmenliste, Status). Angemeldete ueberspringen
 * die Kontofelder.
 */
class CompanySignup extends Component
{
    public int $step = 1;

    // Schritt 1: Betrieb
    public string $firma = '';

    public string $strasse = '';

    public string $plz = '';

    public string $ort = '';

    public string $tel = '';

    public string $web = '';

    // Schritt 2: Konto
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $agb = false;

    // Honeypot
    public string $website_url = '';

    public bool $done = false;

    public bool $resent = false;

    public function next(): void
    {
        $this->step === 1 ? $this->submitCompany() : $this->submitAccount();
    }

    public function back(): void
    {
        $this->step = 1;
        $this->resetValidation();
        $this->dispatch('signup-step');
    }

    private function submitCompany(): void
    {
        $this->web = $this->normalizedWebsite();

        $this->validate([
            'firma' => ['required', 'string', 'min:2', 'max:255'],
            'strasse' => ['required', 'string', 'max:255'],
            'plz' => ['required', 'regex:/^\d{5}$/'],
            'ort' => ['required', 'string', 'max:255'],
            'tel' => ['required', 'string', 'max:50', 'regex:/\d{5,}/'],
            'web' => ['nullable', 'url', 'max:255'],
        ], [
            'firma.required' => 'Wie heißt dein Betrieb?',
            'firma.min' => 'Wie heißt dein Betrieb?',
            'strasse.required' => 'Ohne Adresse findet dich niemand auf der Stadtseite.',
            'plz.required' => 'Bitte gib die PLZ ein.',
            'plz.regex' => 'Die PLZ hat fünf Ziffern.',
            'ort.required' => 'Bitte gib den Ort ein.',
            'tel.required' => 'Ohne Telefonnummer kann dich niemand erreichen.',
            'tel.regex' => 'Die Nummer sieht unvollständig aus.',
            'web.url' => 'Die Adresse sieht nicht wie eine Website aus.',
        ]);

        if (! $this->resolveCity()) {
            $this->addError('ort', 'PLZ und Ort passen nicht zusammen – bitte prüfen.');

            return;
        }

        $this->step = 2;
        $this->resetValidation();
        $this->dispatch('signup-step');
    }

    private function submitAccount(): void
    {
        if ($this->website_url !== '') {
            $this->done = true;

            return;
        }

        if (Auth::guest()) {
            $key = 'company-signup:'.request()->ip();

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $this->addError('email', 'Zu viele Versuche. Bitte versuch es in '.(int) ceil(RateLimiter::availableIn($key) / 60).' Minuten noch einmal.');

                return;
            }

            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users,email'],
                'password' => ['required', 'string', 'min:8'],
                'agb' => ['accepted'],
            ], [
                'name.required' => 'Wie heißt du?',
                'email.required' => 'Ohne E-Mail kannst du dich später nicht anmelden.',
                'email.email' => 'Die E-Mail-Adresse sieht unvollständig aus.',
                'email.unique' => 'Mit dieser E-Mail gibt es schon ein Konto – melde dich an.',
                'password.required' => 'Das Passwort braucht mindestens 8 Zeichen.',
                'password.min' => 'Das Passwort braucht mindestens 8 Zeichen.',
                'agb.accepted' => 'Bitte bestätige AGB und Datenschutzhinweise.',
            ]);

            RateLimiter::hit($key, 3600);
        }

        $city = $this->resolveCity();
        if (! $city) {
            $this->back();
            $this->addError('ort', 'PLZ und Ort passen nicht zusammen – bitte prüfen.');

            return;
        }

        if (Auth::guest()) {
            $user = app(UserService::class)->createUser([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
            event(new Registered($user));
            Auth::login($user);
        }

        $company = DB::transaction(fn () => Company::create([
            'user_id' => Auth::id(),
            'name' => $this->firma,
            'slug' => $this->uniqueSlug(),
            'street' => $this->strasse,
            'zipcode' => $this->plz,
            'city_id' => $city->id,
            'tel' => $this->tel,
            'email' => Auth::user()->email,
            'website' => $this->web ?: null,
            // Freischaltung durch das Portal-Team (Verwaltung > Firmen)
            'is_active' => false,
            'is_premium' => false,
            'is_verified' => false,
        ]));

        if ($tenant = tenant()) {
            $permissions = app(TenantPermissionService::class);
            if (! in_array(TenancyPermissionConstants::ROLE_COMPANY_OWNER, $permissions->getTenantUserRoles($tenant, Auth::user()), true)) {
                $permissions->assignTenantUserRole($tenant, Auth::user(), TenancyPermissionConstants::ROLE_COMPANY_OWNER);
            }
        }

        app(NewCompanyNotifier::class)->notify($company, Auth::user());

        $this->password = '';
        $this->done = true;
    }

    public function resendVerification(): void
    {
        $user = Auth::user();

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        $key = 'company-signup-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return;
        }

        RateLimiter::hit($key, 600);
        $user->sendEmailVerificationNotification();
        $this->resent = true;
    }

    /**
     * Initialen fuer die Zusammenfassung: erste Buchstaben der ersten zwei Woerter.
     */
    public function initials(): string
    {
        return Str::upper(collect(preg_split('/\s+/u', trim($this->firma)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_substr($word, 0, 1))
            ->implode(''));
    }

    private function resolveCity(): ?City
    {
        $ort = trim($this->ort);

        return City::query()->where('zipcode', $this->plz)->where('name', $ort)->first()
            ?? City::query()->where('name', $ort)->first()
            ?? City::query()->where('zipcode', $this->plz)->first();
    }

    private function normalizedWebsite(): string
    {
        $web = trim($this->web);

        return $web === '' || preg_match('#^https?://#i', $web) ? $web : "https://{$web}";
    }

    private function uniqueSlug(): string
    {
        $base = Str::slug($this->firma) ?: 'betrieb';
        $slug = $base;

        for ($i = 2; Company::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.portal.company-signup');
    }
}
