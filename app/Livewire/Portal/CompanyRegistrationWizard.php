<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\AntiSpam\Support\RequestOrigin;
use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\NewCompanyNotifier;
use App\Services\TenantPermissionService;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CompanyRegistrationWizard extends Component
{
    // Honeypot und Ausfuellzeit (#8/#25); die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht.
    use InteractsWithAntiSpam;
    use WithFileUploads;

    // Step-Management
    public int $currentStep = 1;

    public int $totalSteps = 5;

    // Step 1: Firmendaten
    public string $name = '';

    public string $description = '';

    public array $selectedCategories = [];

    // Step 2: Adresse
    public string $street = '';

    public string $house_no = '';

    public string $zipcode = '';

    public ?int $city_id = null;

    public string $citySearch = '';

    public array $citySuggestions = [];

    // Step 3: Kontakt
    public string $tel = '';

    public string $email = '';

    public string $website = '';

    // Step 4: Logo
    public $logo;

    // UI State
    public string $categoryFilter = '';

    public bool $submitted = false;

    public ?Company $createdCompany = null;

    /**
     * Der Eintrag gab es schon (#25, wie #16 fuer CompanySignup): gleicher
     * Nutzer, slug-normalisierter Name, gleiche PLZ. Dann entsteht kein
     * zweiter Datensatz, der Abschluss nennt den bestehenden.
     */
    public bool $duplicate = false;

    public ?string $selectedCityName = null;

    /**
     * Turnstile-Token (#7), Aktion company_listing. Das Widget steht nur in
     * Schritt 5 (Zusammenfassung): ein Token gilt 300 s, in Schritt 1 gerendert
     * waere es beim Abschicken abgelaufen. Geprueft wird es als letzte Regel,
     * damit der Logo-Upload aus Schritt 4 bei einem Turnstile-Fehler nicht
     * verloren geht (docs/turnstile.md §12).
     */
    public string $turnstileToken = '';

    public function mount(string $prefillName = '', string $prefillPlace = ''): void
    {
        if (Auth::check() && Auth::user()->email) {
            $this->email = Auth::user()->email;
        }

        // Vorbelegung aus der Betriebssuche auf /eintragen (Theme sun-v2)
        $this->name = mb_substr(trim($prefillName), 0, 255);

        $place = trim($prefillPlace);
        if ($place === '') {
            return;
        }

        if (preg_match('/(?<!\d)(\d{5})(?!\d)/', $place, $match)) {
            $this->zipcode = $match[1];
        }

        $this->citySearch = $place;
        $city = $this->zipcode !== ''
            ? City::where('zipcode', $this->zipcode)->first()
            : City::where('name', $place)->first();
        if ($city) {
            $this->selectCity($city->id);
        }
    }

    /**
     * Portale ohne gepflegte Kategorien (z. B. Elektriker) koennen sonst
     * keinen Betrieb eintragen, weil Schritt 1 mindestens eine verlangt.
     */
    public function hasCategories(): bool
    {
        return Category::query()->exists();
    }

    protected function rules(): array
    {
        return match ($this->currentStep) {
            1 => [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'selectedCategories' => $this->hasCategories() ? ['required', 'array', 'min:1', 'max:5'] : ['array', 'max:5'],
                'selectedCategories.*' => ['integer', 'exists:categories,id'],
            ],
            2 => [
                'street' => ['required', 'string', 'max:255'],
                'house_no' => ['nullable', 'string', 'max:20'],
                'zipcode' => ['required', 'string', 'regex:/^\d{5}$/'],
                'city_id' => ['required', 'integer', 'exists:cities,id'],
            ],
            3 => [
                'tel' => ['nullable', 'string', 'max:50'],
                'email' => ['required', 'email', 'max:255'],
                'website' => ['nullable', 'url', 'max:255'],
            ],
            4 => [
                'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            ],
            5 => [
                'turnstileToken' => [new TurnstileRule(TurnstileAction::CompanyListing)],
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie den Firmennamen ein.',
            'name.min' => 'Der Firmenname muss mindestens 3 Zeichen lang sein.',
            'selectedCategories.required' => 'Bitte wählen Sie mindestens eine Kategorie.',
            'selectedCategories.max' => 'Maximal 5 Kategorien erlaubt.',
            'street.required' => 'Bitte geben Sie die Straße ein.',
            'zipcode.required' => 'Bitte geben Sie die Postleitzahl ein.',
            'zipcode.regex' => 'Die Postleitzahl muss 5 Ziffern haben.',
            'city_id.required' => 'Bitte wählen Sie eine Stadt aus.',
            'email.required' => 'Bitte geben Sie eine E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'website.url' => 'Bitte geben Sie eine gültige URL ein (z.B. https://example.com).',
            'logo.image' => 'Das Logo muss ein Bild sein (JPEG, PNG, WebP).',
            'logo.mimes' => 'Erlaubte Formate: JPEG, PNG oder WebP.',
            'logo.max' => 'Das Logo darf maximal 2 MB groß sein.',
        ];
    }

    public function nextStep(): void
    {
        $this->validate();
        $this->currentStep = min($this->currentStep + 1, $this->totalSteps);
        $this->dispatch('step-changed');
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
        $this->dispatch('step-changed');
    }

    public function goToStep(int $step): void
    {
        // Nur zurücknavigieren oder zum aktuellen Step erlaubt
        if ($step <= $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function updatedCitySearch(string $value): void
    {
        if (strlen($value) < 2) {
            $this->citySuggestions = [];

            return;
        }

        $this->citySuggestions = City::search($value)
            ->select('id', 'name', 'zipcode')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function selectCity(int $cityId): void
    {
        $city = City::find($cityId);
        if ($city) {
            $this->city_id = $city->id;
            $this->citySearch = "{$city->zipcode} {$city->name}";
            $this->selectedCityName = $city->name;
            $this->citySuggestions = [];
        }
    }

    public function toggleCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->selectedCategories)) {
            $this->selectedCategories = array_values(
                array_diff($this->selectedCategories, [$categoryId])
            );
        } elseif (count($this->selectedCategories) < 5) {
            $this->selectedCategories[] = $categoryId;
        }
    }

    public function removeLogo(): void
    {
        $this->logo = null;
    }

    public function getLogoPreviewUrlProperty(): ?string
    {
        if (! $this->logo instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $this->logo->temporaryUrl();
        } catch (\RuntimeException $e) {
            // Extension nicht in preview_mimes (z.B. HEIC von iPhones)
            return null;
        }
    }

    public function submit(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }

        // Honeypot und Mindest-Ausfuellzeit (#8): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsseite, es entsteht aber kein
        // Eintrag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::CompanyListing, (string) Auth::user()->email)) {
            $this->submitted = true;

            return;
        }

        // Rate-Limit je IP-Hash und Nutzer (#8), Grenzen in
        // config/antispam.php. Gezaehlt wird erst nach dem Anlegen, damit ein
        // Validierungsfehler keinen Versuch kostet.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::COMPANY_LISTING);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::COMPANY_LISTING, TurnstileAction::CompanyListing, (string) Auth::user()->email);
            $this->addError('antispamLimit', $this->antiSpamThrottleMessage($wartezeit));

            return;
        }

        // Validiere alle Steps nochmal
        $this->currentStep = 1;
        $this->validate();
        $this->currentStep = 2;
        $this->validate();
        $this->currentStep = 3;
        $this->validate();
        $this->currentStep = 4;
        $this->validate();

        // Dublette desselben Nutzers (#25): hat er denselben Betrieb schon
        // eingetragen, fuehrt der Abschluss auf den bestehenden Eintrag statt
        // einen zweiten anzulegen. Steht vor der Turnstile-Pruefung, damit ein
        // Doppelklick kein Token verbraucht.
        $bestehender = $this->existingCompany();

        if ($bestehender !== null) {
            $this->createdCompany = $bestehender;
            $this->duplicate = true;
            $this->submitted = true;

            return;
        }

        // Turnstile als letzte Pruefung: schlaegt sie an, bleiben die
        // temporaeren Uploads aus Schritt 4 unberuehrt in der Property.
        $this->currentStep = 5;
        $this->validate();

        // Slug generieren mit Uniqueness-Check
        $baseSlug = Str::slug($this->name);
        $slug = $baseSlug;
        $counter = 1;
        // withTrashed(): `slug` ist unique, und ein soft-geloeschter Eintrag
        // (#10) belegt den Wert weiter — ohne ihn liefe das Anlegen in einen
        // Unique-Fehler statt in den naechsten freien Slug.
        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        DB::transaction(function () use ($slug) {
            $this->createdCompany = Company::create([
                'user_id' => Auth::id(),
                'name' => $this->name,
                'slug' => $slug,
                'description' => $this->description ?: null,
                'street' => $this->street,
                'house_no' => $this->house_no ?: null,
                'zipcode' => $this->zipcode,
                'city_id' => $this->city_id,
                'tel' => $this->tel ?: null,
                'email' => $this->email,
                'website' => $this->website ?: null,
                // Freischaltung durch das Portal-Team (Verwaltung > Firmen).
                // Fail-closed nach #7: ein frischer Eintrag ist nicht sofort
                // oeffentlich, auch nicht von einem angemeldeten Konto.
                'is_active' => false,
                'is_premium' => false,
                'is_verified' => false,
                // Herkunft der Selbsteintragung (#8), IP nur als HMAC-Hash.
            ] + RequestOrigin::forCompany());

            $this->createdCompany->categories()->sync($this->selectedCategories);
        });

        // Logo-Upload nach Company-Erstellung (außerhalb Transaction — Media Library hat eigene DB-Operationen)
        if ($this->logo) {
            $this->createdCompany
                ->addMedia($this->logo->getRealPath())
                ->usingFileName('logo.'.$this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');
        }

        // company_owner Rolle zuweisen (damit der User auf /verwaltung zugreifen kann)
        $tenant = tenant();
        if ($tenant) {
            $permissionService = app(TenantPermissionService::class);
            $currentRoles = $permissionService->getTenantUserRoles($tenant, Auth::user());
            if (! in_array(TenancyPermissionConstants::ROLE_COMPANY_OWNER, $currentRoles)) {
                $permissionService->assignTenantUserRole(
                    $tenant,
                    Auth::user(),
                    TenancyPermissionConstants::ROLE_COMPANY_OWNER,
                );
            }
        }

        app(NewCompanyNotifier::class)->notify($this->createdCompany, Auth::user());

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::COMPANY_LISTING);

        $this->submitted = true;

        session()->flash('success', 'Ihre Firma wurde eingetragen und wird jetzt geprüft.');
    }

    /**
     * Bestehender Eintrag desselben Nutzers mit gleichem Namen und gleicher
     * PLZ (#25). Verglichen wird der slug-normalisierte Name, damit
     * "Gutachten Franken", "gutachten-franken" und "Gutachten  Franken" als
     * derselbe Betrieb gelten. Die Kandidaten sind wenige Zeilen (ein Nutzer,
     * eine PLZ), deshalb wird in PHP verglichen statt in SQL.
     */
    private function existingCompany(): ?Company
    {
        $userId = Auth::id();
        $name = Str::slug($this->name, '-', 'de');

        if ($userId === null || $name === '') {
            return null;
        }

        return Company::query()
            ->where('user_id', $userId)
            ->where('zipcode', $this->zipcode)
            ->orderBy('id')
            ->get()
            ->first(fn (Company $company): bool => Str::slug((string) $company->name, '-', 'de') === $name);
    }

    public function render()
    {
        $categories = collect();
        if ($this->currentStep === 1) {
            $query = Category::roots()->ordered()->with(['children' => fn ($q) => $q->ordered()]);
            if ($this->categoryFilter !== '') {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->categoryFilter.'%')
                        ->orWhereHas('children', fn ($c) => $c->where('name', 'like', '%'.$this->categoryFilter.'%'));
                });
            }
            $categories = $query->get();
        }

        return view('livewire.portal.company-registration-wizard', [
            'categories' => $categories,
            'selectedCityName' => $this->selectedCityName,
        ]);
    }
}
