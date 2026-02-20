<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class CompanyForm extends Component
{
    use WithFileUploads;

    public ?Company $company = null;
    public bool $isEdit = false;

    // Firmendaten
    public string $name = '';
    public string $description = '';
    public array $selectedCategories = [];

    // Adresse
    public string $street = '';
    public string $house_no = '';
    public string $zipcode = '';
    public ?int $city_id = null;
    public string $citySearch = '';

    // Kontakt
    public string $tel = '';
    public string $email = '';
    public string $website = '';

    // Status
    public bool $is_active = true;
    public bool $is_premium = false;
    public bool $is_verified = false;

    // Owner (admin only)
    public ?int $user_id = null;

    // Media
    public $logo;
    public $cover;
    public array $gallery = [];
    public array $existingGallery = [];

    // Öffnungszeiten
    public array $openingHours = [];

    // UI state
    public bool $showDeleteModal = false;
    public array $removedGalleryIds = [];

    public function mount(?Company $company = null): void
    {
        if ($company && $company->exists) {
            $this->company = $company;
            $this->isEdit = true;
            $this->fillFromCompany($company);
        } else {
            $this->initDefaultOpeningHours();
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'selectedCategories' => ['required', 'array', 'min:1', 'max:5'],
            'selectedCategories.*' => ['integer', 'exists:categories,id'],
            'street' => ['required', 'string', 'max:255'],
            'house_no' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'tel' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:500'],
            'is_active' => ['boolean'],
            'is_premium' => ['boolean'],
            'is_verified' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'gallery.*' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'openingHours.*.opens_at' => ['nullable', 'string'],
            'openingHours.*.closes_at' => ['nullable', 'string'],
            'openingHours.*.is_closed' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Der Firmenname ist erforderlich.',
            'name.min' => 'Der Firmenname muss mindestens 2 Zeichen lang sein.',
            'selectedCategories.required' => 'Bitte wählen Sie mindestens eine Kategorie.',
            'selectedCategories.max' => 'Maximal 5 Kategorien erlaubt.',
            'street.required' => 'Die Straße ist erforderlich.',
            'zipcode.required' => 'Die Postleitzahl ist erforderlich.',
            'zipcode.regex' => 'Bitte geben Sie eine gültige 5-stellige PLZ ein.',
            'city_id.required' => 'Bitte wählen Sie eine Stadt.',
            'city_id.exists' => 'Die ausgewählte Stadt ist ungültig.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'website.url' => 'Bitte geben Sie eine gültige URL ein (mit http:// oder https://).',
            'logo.max' => 'Das Logo darf maximal 2 MB groß sein.',
            'cover.max' => 'Das Coverbild darf maximal 4 MB groß sein.',
            'gallery.*.max' => 'Jedes Galerie-Bild darf maximal 4 MB groß sein.',
        ];
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'street' => $this->street,
            'house_no' => $this->house_no ?: null,
            'zipcode' => $this->zipcode,
            'city_id' => $this->city_id,
            'tel' => $this->tel ?: null,
            'email' => $this->email ?: null,
            'website' => $this->website ?: null,
            'is_active' => $this->is_active,
        ];

        // Admin-only fields
        if (auth()->user()->isAdmin()) {
            $data['is_premium'] = $this->is_premium;
            $data['is_verified'] = $this->is_verified;
            if ($this->user_id) {
                $data['user_id'] = $this->user_id;
            }
        }

        if ($this->isEdit) {
            $this->company->update($data);
            $company = $this->company;
        } else {
            $data['slug'] = Str::slug($this->name);
            if (! isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }
            $company = Company::create($data);
        }

        // Categories (sync)
        $company->categories()->sync($this->selectedCategories);

        // Öffnungszeiten
        $this->saveOpeningHours($company);

        // Media uploads
        $this->handleMediaUploads($company);

        // Handle gallery removals
        $this->handleGalleryRemovals($company);

        $message = $this->isEdit
            ? "\"{$company->name}\" wurde aktualisiert."
            : "\"{$company->name}\" wurde erstellt.";

        return redirect()
            ->route('verwaltung.companies.index')
            ->with('success', $message);
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

    public function removeGalleryImage(int $mediaId): void
    {
        $this->removedGalleryIds[] = $mediaId;
        $this->existingGallery = array_filter(
            $this->existingGallery,
            fn($img) => $img['id'] !== $mediaId
        );
    }

    public function removeNewGalleryImage(int $index): void
    {
        unset($this->gallery[$index]);
        $this->gallery = array_values($this->gallery);
    }

    public function searchCities(): array
    {
        if (strlen($this->citySearch) < 2) {
            return [];
        }

        return City::where('name', 'like', '%' . $this->citySearch . '%')
            ->orderBy('name')
            ->limit(10)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function render()
    {
        $categories = Category::orderBy('name')->get();
        $isAdmin = auth()->user()->isAdmin();
        $cityResults = $this->searchCities();

        // Current city name for display
        $currentCity = null;
        if ($this->city_id) {
            $currentCity = City::find($this->city_id);
        }

        return view('livewire.verwaltung.company-form', compact(
            'categories',
            'isAdmin',
            'cityResults',
            'currentCity',
        ));
    }

    private function fillFromCompany(Company $company): void
    {
        $this->name = $company->name;
        $this->description = $company->description ?? '';
        $this->selectedCategories = $company->categories->pluck('id')->toArray();
        $this->street = $company->street ?? '';
        $this->house_no = $company->house_no ?? '';
        $this->zipcode = $company->zipcode ?? '';
        $this->city_id = $company->city_id;
        $this->tel = $company->tel ?? '';
        $this->email = $company->email ?? '';
        $this->website = $company->website ?? '';
        $this->is_active = $company->is_active;
        $this->is_premium = $company->is_premium;
        $this->is_verified = $company->is_verified;
        $this->user_id = $company->user_id;

        // Öffnungszeiten laden
        $hours = $company->openingHours;
        if ($hours->isNotEmpty()) {
            $this->openingHours = [];
            foreach ($hours as $hour) {
                $this->openingHours[$hour->day_of_week] = [
                    'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : '',
                    'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : '',
                    'is_closed' => $hour->is_closed,
                ];
            }
            // Fill missing days
            for ($i = 0; $i < 7; $i++) {
                if (! isset($this->openingHours[$i])) {
                    $this->openingHours[$i] = ['opens_at' => '', 'closes_at' => '', 'is_closed' => false];
                }
            }
        } else {
            $this->initDefaultOpeningHours();
        }

        // Existing gallery images
        $galleryMedia = $company->getMedia('gallery');
        $this->existingGallery = $galleryMedia->map(fn($media) => [
            'id' => $media->id,
            'url' => $media->getUrl('medium'),
            'name' => $media->file_name,
        ])->toArray();
    }

    private function initDefaultOpeningHours(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->openingHours[$i] = [
                'opens_at' => ($i < 5) ? '08:00' : '', // Mo-Fr: 8:00
                'closes_at' => ($i < 5) ? '17:00' : '', // Mo-Fr: 17:00
                'is_closed' => ($i >= 5), // Sa+So: geschlossen
            ];
        }
    }

    private function saveOpeningHours(Company $company): void
    {
        // Delete existing and re-create
        $company->openingHours()->delete();

        foreach ($this->openingHours as $day => $hours) {
            CompanyOpeningHour::create([
                'company_id' => $company->id,
                'day_of_week' => (int) $day,
                'opens_at' => $hours['is_closed'] ? null : ($hours['opens_at'] ?: null),
                'closes_at' => $hours['is_closed'] ? null : ($hours['closes_at'] ?: null),
                'is_closed' => $hours['is_closed'] ?? false,
            ]);
        }
    }

    private function handleMediaUploads(Company $company): void
    {
        if ($this->logo) {
            $company->clearMediaCollection('logo');
            $company->addMedia($this->logo->getRealPath())
                ->usingFileName('logo-' . $company->id . '.' . $this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');
        }

        if ($this->cover) {
            $company->clearMediaCollection('cover');
            $company->addMedia($this->cover->getRealPath())
                ->usingFileName('cover-' . $company->id . '.' . $this->cover->getClientOriginalExtension())
                ->toMediaCollection('cover');
        }

        foreach ($this->gallery as $image) {
            $company->addMedia($image->getRealPath())
                ->usingFileName('gallery-' . $company->id . '-' . Str::random(8) . '.' . $image->getClientOriginalExtension())
                ->toMediaCollection('gallery');
        }
    }

    private function handleGalleryRemovals(Company $company): void
    {
        if (! empty($this->removedGalleryIds)) {
            $company->media()
                ->where('collection_name', 'gallery')
                ->whereIn('id', $this->removedGalleryIds)
                ->each(fn($media) => $media->delete());
        }
    }
}
