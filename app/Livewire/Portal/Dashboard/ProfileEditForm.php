<?php

namespace App\Livewire\Portal\Dashboard;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProfileEditForm extends Component
{
    use WithFileUploads;

    // Company data
    public string $name = '';
    public string $description = '';
    public string $street = '';
    public string $house_no = '';
    public string $zipcode = '';
    public ?int $city_id = null;
    public string $citySearch = '';
    public string $tel = '';
    public string $email = '';
    public string $website = '';
    public array $selectedCategories = [];

    // File uploads
    public $logo;

    // UI State
    public array $citySuggestions = [];
    public bool $saved = false;
    public string $activeSection = 'stammdaten';

    // Cached data (avoid re-querying on every render)
    public ?string $currentLogoUrl = null;
    public int $companyId = 0;

    public function mount(): void
    {
        $company = $this->getCompany();
        $this->companyId = $company->id;

        $this->name = $company->name ?? '';
        $this->description = $company->description ?? '';
        $this->street = $company->street ?? '';
        $this->house_no = $company->house_no ?? '';
        $this->zipcode = $company->zipcode ?? '';
        $this->city_id = $company->city_id;
        $this->tel = $company->tel ?? '';
        $this->email = $company->email ?? '';
        $this->website = $company->website ?? '';
        $this->selectedCategories = $company->categories->pluck('id')->toArray();
        $this->currentLogoUrl = $company->getFirstMediaUrl('logo', 'medium') ?: null;

        if ($company->city) {
            $this->citySearch = "{$company->city->zipcode} {$company->city->name}";
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'street' => ['required', 'string', 'max:255'],
            'house_no' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'tel' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'selectedCategories' => ['required', 'array', 'min:1', 'max:5'],
            'selectedCategories.*' => ['integer', 'exists:categories,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
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
            'logo.max' => 'Das Logo darf maximal 2 MB groß sein.',
        ];
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

    public function save(): void
    {
        $this->validate();

        $company = $this->getCompany();

        $company->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'street' => $this->street,
            'house_no' => $this->house_no ?: null,
            'zipcode' => $this->zipcode,
            'city_id' => $this->city_id,
            'tel' => $this->tel ?: null,
            'email' => $this->email,
            'website' => $this->website ?: null,
        ]);

        $company->categories()->sync($this->selectedCategories);

        // Logo upload
        if ($this->logo) {
            $company->addMedia($this->logo->getRealPath())
                ->usingFileName('logo.' . $this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');
            $this->logo = null;
            // Refresh cached logo URL
            $this->currentLogoUrl = $company->fresh()->getFirstMediaUrl('logo', 'medium') ?: null;
        }

        $this->saved = true;
        $this->dispatch('profile-saved');
    }

    public function getLogoPreviewUrlProperty(): ?string
    {
        if (! $this->logo instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $this->logo->temporaryUrl();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function removeLogo(): void
    {
        $company = $this->getCompany();
        $company->clearMediaCollection('logo');
        $this->currentLogoUrl = null;
    }

    private function getCompany(): Company
    {
        return Company::ownedBy(Auth::id())
            ->with(['categories', 'city', 'media'])
            ->firstOrFail();
    }

    #[Computed(cache: true)]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::roots()->ordered()->get();
    }

    public function render()
    {
        return view('livewire.portal.dashboard.profile-edit-form', [
            'categories' => $this->categories(),
            'currentLogo' => $this->currentLogoUrl,
        ]);
    }
}
