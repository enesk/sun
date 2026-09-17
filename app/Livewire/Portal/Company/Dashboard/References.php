<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyReference;
use App\Services\Premium\CompanyProfileContentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Projektreferenzen im Betriebsbereich (#13, Feature references).
 * Anzahl ueber limit(References), Fotos je Referenz ueber
 * premium.profile.reference_photos_max. Ohne Freischaltung bleiben
 * vorhandene Referenzen gespeichert und koennen geloescht werden.
 */
class References extends Component
{
    use HasEntitlements;
    use WithFileUploads;

    #[Locked]
    public int $companyId = 0;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public ?int $year = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
    }

    public function create(CompanyProfileContentService $contents): void
    {
        if (! $this->can(PremiumFeature::References) || ! $contents->canAddReference($this->company())) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $referenceId): void
    {
        if (! $this->can(PremiumFeature::References)) {
            return;
        }

        $reference = $this->reference($referenceId);

        $this->resetForm();
        $this->editingId = (int) $reference->getKey();
        $this->title = $reference->title;
        $this->description = (string) $reference->description;
        $this->location = (string) $reference->location;
        $this->year = $reference->year;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CompanyProfileContentService $contents): void
    {
        $company = $this->company();

        if (! $this->can(PremiumFeature::References)) {
            return;
        }

        $reference = $this->editingId !== null ? $this->reference($this->editingId) : null;

        if ($reference === null && ! $contents->canAddReference($company)) {
            $this->addError('title', __('portal.owner.references.limit_reached', ['max' => (int) $contents->referencesLimit($company)]));

            return;
        }

        $photoSlots = $contents->referencePhotosMax() - ($reference?->getMedia(CompanyReference::PHOTO_COLLECTION)->count() ?? 0);

        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'photos' => ['array', 'max:'.max(0, $photoSlots)],
            'photos.*' => ['image', 'max:'.(int) config('premium.profile.reference_photo_max_kb', 5120)],
        ], [
            'title.required' => __('portal.owner.references.errors.title_required'),
            'title.min' => __('portal.owner.references.errors.title_required'),
            'year.*' => __('portal.owner.references.errors.year'),
            'photos.max' => __('portal.owner.references.errors.photos_max', ['max' => $contents->referencePhotosMax()]),
            'photos.*.image' => __('portal.owner.references.errors.photo_image'),
            'photos.*.max' => __('portal.owner.references.errors.photo_max'),
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description ?: null,
            'location' => $this->location ?: null,
            'year' => $this->year,
        ];

        if ($reference === null) {
            $reference = $company->references()->create([
                ...$data,
                'sort_order' => (int) $company->references()->max('sort_order') + 1,
            ]);
        } else {
            $reference->update($data);
        }

        foreach ($this->photos as $photo) {
            $reference->addMedia($photo->getRealPath())
                ->usingFileName('reference_'.uniqid().'.'.$photo->getClientOriginalExtension())
                ->toMediaCollection(CompanyReference::PHOTO_COLLECTION);
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('portal.owner.references.saved'));
    }

    public function delete(int $referenceId): void
    {
        // Loeschen auch ohne Freischaltung (Aufraeumen nach Downgrade)
        $this->reference($referenceId)->delete();

        if ($this->editingId === $referenceId) {
            $this->resetForm();
        }
    }

    public function removePhoto(int $referenceId, int $mediaId): void
    {
        $this->reference($referenceId)
            ->getMedia(CompanyReference::PHOTO_COLLECTION)
            ->firstWhere('id', $mediaId)
            ?->delete();
    }

    /**
     * Verschiebt eine Referenz um eine Position ($direction -1 = nach oben, 1 = nach unten).
     */
    public function move(int $referenceId, int $direction): void
    {
        $references = $this->company()->references()->get()->values();
        $index = $references->search(fn (CompanyReference $reference): bool => $reference->getKey() === $referenceId);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || ! $references->has($target)) {
            return;
        }

        $ordered = $references->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::connection((new CompanyReference)->getConnectionName())->transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $reference) {
                $reference->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function render(CompanyProfileContentService $contents): View
    {
        $company = $this->company();
        $limit = $contents->referencesLimit($company);
        $references = $company->references()->with('media')->get();

        return view('livewire.portal.company.dashboard.references', [
            'allowed' => $this->can(PremiumFeature::References),
            'references' => $references,
            'limit' => $limit,
            'canAdd' => $contents->canAddReference($company),
            'photosMax' => $contents->referencePhotosMax(),
            'editing' => $this->editingId !== null ? $references->firstWhere('id', $this->editingId) : null,
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'title', 'description', 'location', 'year', 'photos');
        $this->resetValidation();
    }

    private function reference(int $referenceId): CompanyReference
    {
        return $this->company()->references()->whereKey($referenceId)->firstOrFail();
    }

    private function company(): Company
    {
        return $this->company ??= Company::ownedBy((int) Auth::id())
            ->when($this->companyId > 0, fn ($query) => $query->whereKey($this->companyId))
            ->firstOrFail();
    }
}
