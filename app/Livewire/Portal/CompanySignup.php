<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Rules\NotDisposableEmailRule;
use App\AntiSpam\Support\RateLimitGuard;
use App\AntiSpam\Support\RequestOrigin;
use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\NewCompanyNotifier;
use App\Services\TenantPermissionService;
use App\Services\UserService;
use App\Support\PersonalNameDetector;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
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
    // Honeypot und Ausfuellzeit (#8); die Properties kommen aus dem Trait,
    // gebunden von <x-antispam-fields wire /> in der Ansicht.
    use InteractsWithAntiSpam;

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

    /**
     * Rueckfrage, wenn in `firma` ein Personenname steht (#15). Sie haelt das
     * Absenden nur einmal auf: wer bestaetigt, kommt durch.
     */
    public bool $nameQuestion = false;

    public bool $nameConfirmed = false;

    public bool $done = false;

    /**
     * Der Eintrag gab es schon (#16): gleicher Nutzer, gleicher Name, gleiche
     * PLZ. Dann entsteht kein zweiter Datensatz, der Abschluss nennt den
     * bestehenden und fuehrt in die Verwaltung.
     */
    public bool $duplicate = false;

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

    /**
     * Ein geaenderter Firmenname wird neu beurteilt.
     */
    public function updatedFirma(): void
    {
        $this->nameQuestion = false;
        $this->nameConfirmed = false;
    }

    public function confirmName(): void
    {
        $this->nameConfirmed = true;
        $this->nameQuestion = false;

        $this->next();
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

        if (! $this->nameConfirmed && PersonalNameDetector::looksPersonal($this->firma)) {
            $this->nameQuestion = true;

            return;
        }

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
        // Honeypot und Mindest-Ausfuellzeit (#8): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsseite, es entsteht aber weder Konto
        // noch Eintrag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::CompanyListing, Auth::user()?->email ?? $this->email)) {
            $this->done = true;

            return;
        }

        // Rate-Limit je IP-Hash und Portal (#8), Grenze in
        // config/antispam.php. Gilt jetzt auch fuer Angemeldete — bisher
        // zaehlte nur der Gastweg, weshalb in den Produktionsdaten 10
        // Eintraege desselben Nutzers in 23 Sekunden stehen
        // (docs/turnstile.md §1.3 B). Die Dublettenerkennung selbst bleibt #16.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::COMPANY_LISTING);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::COMPANY_LISTING, TurnstileAction::CompanyListing, Auth::user()?->email ?? $this->email);
            $this->addError('email', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        if (Auth::guest()) {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                // Wegwerf-Adressen sichtbar abweisen (#8). Dieser Weg legt ein
                // Konto an, ohne durch App\Validator\RegisterValidator zu
                // laufen, deshalb haengt die Regel hier eigens.
                'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users,email', new NotDisposableEmailRule(TurnstileAction::CompanyListing)],
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
        }

        // Firmenname gleich dem Personennamen: dasselbe Muster wie in #15,
        // nur in Schritt 2 sichtbar. Rueckfrage am Namensfeld in Schritt 1.
        $person = Auth::check() ? (string) Auth::user()->name : $this->name;

        if (! $this->nameConfirmed && PersonalNameDetector::isSamePerson($this->firma, $person)) {
            $this->nameQuestion = true;
            $this->step = 1;
            $this->resetValidation();
            $this->dispatch('signup-step');

            return;
        }

        $city = $this->resolveCity();
        if (! $city) {
            $this->back();
            $this->addError('ort', __('portal.errors.signup.city_mismatch'));

            return;
        }

        // Dublette desselben Nutzers (#16): hat er denselben Betrieb schon
        // eingetragen, fuehrt der Abschluss auf den bestehenden Eintrag statt
        // einen zweiten anzulegen. Steht vor der Turnstile-Pruefung, damit ein
        // Doppelklick kein Token verbraucht; fuer Gaeste greift es nie, weil
        // das Konto erst unten entsteht.
        if ($this->existingCompany() !== null) {
            $this->duplicate = true;
            $this->done = true;

            return;
        }

        // Turnstile zuletzt: erst wenn alle Felder stimmen, wird ein Token
        // eingeloest. Cloudflare nimmt jedes Token nur einmal an, ein Fehler in
        // einem anderen Feld soll es also nicht verbrauchen.
        $this->validate([
            'turnstileToken' => [new TurnstileRule(TurnstileAction::CompanyListing)],
        ]);

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
            // Herkunft der Selbsteintragung (#8), IP nur als HMAC-Hash.
        ] + RequestOrigin::forCompany()));

        if ($tenant = tenant()) {
            $permissions = app(TenantPermissionService::class);
            if (! in_array(TenancyPermissionConstants::ROLE_COMPANY_OWNER, $permissions->getTenantUserRoles($tenant, Auth::user()), true)) {
                $permissions->assignTenantUserRole($tenant, Auth::user(), TenancyPermissionConstants::ROLE_COMPANY_OWNER);
            }
        }

        app(NewCompanyNotifier::class)->notify($company, Auth::user());

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::COMPANY_LISTING);

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

    /**
     * Bestehender Eintrag desselben Nutzers mit gleichem Namen und gleicher
     * PLZ (#16). Verglichen wird der slug-normalisierte Name, damit
     * "Gutachten Franken", "gutachten-franken" und "Gutachten  Franken" als
     * derselbe Betrieb gelten. Die Kandidaten sind wenige Zeilen (ein Nutzer,
     * eine PLZ), deshalb wird in PHP verglichen statt in SQL.
     */
    private function existingCompany(): ?Company
    {
        $userId = Auth::id();
        $name = $this->normalizedName($this->firma);

        if ($userId === null || $name === '') {
            return null;
        }

        return Company::query()
            ->where('user_id', $userId)
            ->where('zipcode', $this->plz)
            ->orderBy('id')
            ->get()
            ->first(fn (Company $company): bool => $this->normalizedName((string) $company->name) === $name);
    }

    private function normalizedName(string $value): string
    {
        return Str::slug($value, '-', 'de');
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

        // withTrashed(): `slug` ist unique, und ein soft-geloeschter Eintrag
        // (#10) belegt den Wert weiter — ohne ihn liefe das Anlegen in einen
        // Unique-Fehler statt in den naechsten freien Slug.
        for ($i = 2; Company::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.portal.company-signup');
    }
}
