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
 * Verwaltung freigeschaltet (Firmenliste, Status). Angemeldete sehen nur
 * Schritt 1 und tragen damit direkt ein.
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

    /**
     * Feldnamen fuer :attribute, falls eine Regel ohne eigene Meldung greift
     * (max, string, ...). Einzige Quelle ist portal.signup.attributes.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return (array) __('portal.signup.attributes');
    }

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
            'firma.required' => __('portal.errors.signup.name_required'),
            'firma.min' => __('portal.errors.signup.name_required'),
            'strasse.required' => __('portal.errors.signup.street_required'),
            'plz.required' => __('portal.errors.signup.zip_required'),
            'plz.regex' => __('portal.errors.signup.zip_format'),
            'ort.required' => __('portal.errors.signup.city_required'),
            'tel.required' => __('portal.errors.signup.phone_required'),
            'tel.regex' => __('portal.errors.signup.phone_format'),
            'web.url' => __('portal.errors.signup.website_format'),
        ]);

        if (! $this->resolveCity()) {
            $this->addError('ort', __('portal.errors.signup.city_mismatch'));

            return;
        }

        // Angemeldete haben schon ein Konto: kein zweiter Schritt, direkt eintragen.
        if (Auth::check()) {
            $this->submitAccount();

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
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                $this->addError('email', trans_choice('portal.errors.signup.throttled', $minutes, ['minuten' => $minutes]));

                return;
            }

            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users,email'],
                'password' => ['required', 'string', 'min:8'],
                'agb' => ['accepted'],
            ], [
                'name.required' => __('portal.errors.signup.person_required'),
                'email.required' => __('portal.errors.signup.email_required'),
                'email.email' => __('portal.errors.signup.email_format'),
                'email.unique' => __('portal.errors.signup.email_taken'),
                'password.required' => __('portal.errors.signup.password_min'),
                'password.min' => __('portal.errors.signup.password_min'),
                'agb.accepted' => __('portal.errors.signup.terms_required'),
            ]);

            RateLimiter::hit($key, 3600);
        }

        $city = $this->resolveCity();
        if (! $city) {
            $this->back();
            $this->addError('ort', __('portal.errors.signup.city_mismatch'));

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
