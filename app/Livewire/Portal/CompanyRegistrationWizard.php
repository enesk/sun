<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CompanyRegistrationWizard extends Component
{
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
    public ?string $selectedCityName = null;

    protected function rules(): array
    {
        return match ($this->currentStep) {
            1 => [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'selectedCategories' => ['required', 'array', 'min:1', 'max:5'],
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
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
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

        // Validiere alle Steps nochmal
        $this->currentStep = 1;
        $this->validate();
        $this->currentStep = 2;
        $this->validate();
        $this->currentStep = 3;
        $this->validate();
        $this->currentStep = 4;
        $this->validate();
        $this->currentStep = 5;

        // Slug generieren mit Uniqueness-Check
        $baseSlug = Str::slug($this->name);
        $slug = $baseSlug;
        $counter = 1;
        while (Company::where('slug', $slug)->exists()) {
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
                'is_active' => true,
                'is_premium' => false,
                'is_verified' => false,
            ]);

            $this->createdCompany->categories()->sync($this->selectedCategories);
        });

        // Logo-Upload nach Company-Erstellung (außerhalb Transaction — Media Library hat eigene DB-Operationen)
        if ($this->logo) {
            $this->createdCompany
                ->addMedia($this->logo->getRealPath())
                ->usingFileName('logo.' . $this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');
        }

        $this->submitted = true;

        session()->flash('success', 'Ihre Firma wurde erfolgreich eingetragen!');
    }

    public function render()
    {
        $categories = collect();
        if ($this->currentStep === 1) {
            $query = Category::roots()->ordered();
            if ($this->categoryFilter !== '') {
                $query->where('name', 'like', '%' . $this->categoryFilter . '%');
            }
            $categories = $query->get();
        }

        return view('livewire.portal.company-registration-wizard', [
            'categories' => $categories,
            'selectedCityName' => $this->selectedCityName,
        ]);
    }
}
