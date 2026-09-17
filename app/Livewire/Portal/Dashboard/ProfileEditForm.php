<?php

namespace App\Livewire\Portal\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyProfileContentService;
use App\Support\VideoEmbed;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProfileEditForm extends Component
{
    use HasEntitlements;
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
    public string $video_url = '';

    // File uploads
    public $logo;
    public $cover;
    public $galleryUploads = [];

    // UI State
    public array $citySuggestions = [];
    public bool $saved = false;
    public string $activeSection = 'stammdaten';

    // Cached data (avoid re-querying on every render)
    public ?string $currentLogoUrl = null;
    public ?string $currentCoverUrl = null;
    public array $existingGallery = [];
    // Galerie-Limit aus dem Entitlement-Service (#13), null = unbegrenzt
    public ?int $galleryLimit = null;
    public bool $canVideo = false;

    private ?Company $ownerCompany = null;
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
        $this->video_url = (string) ($company->video_url ?? '');
        $this->canVideo = $this->can(PremiumFeature::VideoEmbed);
        $this->currentLogoUrl = $company->getFirstMediaUrl('logo', 'medium') ?: null;
        $this->currentCoverUrl = $company->getFirstMediaUrl('cover', 'banner') ?: null;
        $this->loadExistingGallery($company);

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
            'cover' => ['nullable', 'image', 'max:5120'],
            'galleryUploads' => ['nullable', 'array', 'max:'.max(1, $this->remainingGallerySlots() ?? PHP_INT_MAX)],
            'galleryUploads.*' => ['image', 'max:'.(int) config('premium.profile.gallery_max_kb', 5120)],
            'video_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! VideoEmbed::isValid((string) $value)) {
                    $fail('Bitte geben Sie einen Link zu einem YouTube- oder Vimeo-Video ein.');
                }
            }],
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
            'cover.image' => 'Das Titelbild muss ein Bild sein (JPEG, PNG, WebP).',
            'cover.max' => 'Das Titelbild darf maximal 5 MB groß sein.',
            'galleryUploads.max' => 'Für Ihr Paket sind nicht mehr so viele Fotos möglich.',
            'galleryUploads.*.image' => 'Nur Bilder erlaubt (JPEG, PNG, WebP).',
            'galleryUploads.*.max' => 'Jedes Bild darf maximal 5 MB groß sein.',
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

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'street' => $this->street,
            'house_no' => $this->house_no ?: null,
            'zipcode' => $this->zipcode,
            'city_id' => $this->city_id,
            'tel' => $this->tel ?: null,
            'email' => $this->email,
            'website' => $this->website ?: null,
        ];

        // Video nur mit Freischaltung (#13); ein gespeicherter Link bleibt nach Downgrade erhalten
        if ($this->can(PremiumFeature::VideoEmbed)) {
            $data['video_url'] = trim($this->video_url) ?: null;
        }

        $company->update($data);

        $company->categories()->sync($this->selectedCategories);

        // Logo upload
        if ($this->logo) {
            $company->addMedia($this->logo->getRealPath())
                ->usingFileName('logo.' . $this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');
            $this->logo = null;
            $this->currentLogoUrl = $company->fresh()->getFirstMediaUrl('logo', 'medium') ?: null;
        }

        // Cover/Banner upload
        if ($this->cover) {
            $company->addMedia($this->cover->getRealPath())
                ->usingFileName('cover.' . $this->cover->getClientOriginalExtension())
                ->toMediaCollection('cover');
            $this->cover = null;
            $this->currentCoverUrl = $company->fresh()->getFirstMediaUrl('cover', 'banner') ?: null;
        }

        // Galerie: Limit je Plan ueber den Entitlement-Service (#13)
        if (! empty($this->galleryUploads)) {
            $contents = app(CompanyProfileContentService::class);
            $added = $contents->addGalleryPhotos($company, $this->galleryUploads);

            if ($added < count($this->galleryUploads)) {
                $this->dispatch('toast', type: 'error', message: __('portal.owner.edit.gallery.limit_reached', ['max' => (int) $contents->galleryLimit($company)]));
            }

            $this->galleryUploads = [];
            $this->loadExistingGallery($company->fresh());
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

    public function getCoverPreviewUrlProperty(): ?string
    {
        if (! $this->cover instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $this->cover->temporaryUrl();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function removeCover(): void
    {
        $company = $this->getCompany();
        $company->clearMediaCollection('cover');
        $this->currentCoverUrl = null;
    }

    public function removeGalleryImage(int $mediaId): void
    {
        $company = $this->getCompany();
        $media = $company->getMedia('gallery')->firstWhere('id', $mediaId);

        if ($media) {
            $media->delete();
            $this->loadExistingGallery($company->fresh());
        }
    }

    /**
     * Neue Reihenfolge der Galerie (#14, Drag-Sort). Die ersten N Fotos
     * sind oeffentlich sichtbar; fremde oder unbekannte IDs werden ignoriert.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderGallery(array $orderedIds): void
    {
        $company = $this->getCompany();
        $gallery = $company->getMedia('gallery')->keyBy(fn ($media) => (int) $media->getKey());
        $ownIds = $gallery->keys()->all();
        $ids = array_values(array_intersect(array_map('intval', $orderedIds), $ownIds));

        // Nicht uebergebene Fotos behalten ihre relative Reihenfolge am Ende
        $ids = [...$ids, ...array_values(array_diff($ownIds, $ids))];

        if ($ids === [] || $ids === $ownIds) {
            return;
        }

        // Ueber die geladenen Modelle speichern, damit die Tenant-Verbindung greift
        foreach ($ids as $position => $id) {
            $media = $gallery->get($id);

            if ((int) $media->order_column !== $position + 1) {
                $media->order_column = $position + 1;
                $media->save();
            }
        }

        $this->loadExistingGallery($company->fresh());
    }

    /**
     * Alle gespeicherten Fotos; ueber dem Limit liegende sind als
     * nicht sichtbar markiert (visible = false).
     */
    private function loadExistingGallery(Company $company): void
    {
        $contents = app(CompanyProfileContentService::class);

        $this->galleryLimit = $contents->galleryLimit($company);
        $this->existingGallery = $contents->galleryOverview($company);
    }

    private function remainingGallerySlots(): ?int
    {
        return $this->galleryLimit === null
            ? null
            : max(0, $this->galleryLimit - count($this->existingGallery));
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->ownerCompany ??= Company::ownedBy(Auth::id())->first();
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
            'currentCover' => $this->currentCoverUrl,
            'gallerySlots' => $this->remainingGallerySlots(),
        ]);
    }
}
