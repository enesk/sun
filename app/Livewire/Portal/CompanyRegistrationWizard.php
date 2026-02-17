<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class CompanyRegistrationWizard extends Component
{
    // Step-Management
    public int $currentStep = 1;
    public int $totalSteps = 4;

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

    // UI State
    public string $categoryFilter = '';
    public bool $submitted = false;
    public ?Company $createdCompany = null;

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

        $selectedCityName = null;
        if ($this->city_id) {
            $selectedCityName = City::find($this->city_id)?->name;
        }

        return view('livewire.portal.company-registration-wizard', [
            'categories' => $categories,
            'selectedCityName' => $selectedCityName,
        ]);
    }
}
