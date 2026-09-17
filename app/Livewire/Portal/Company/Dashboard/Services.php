<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyService;
use App\Services\Premium\CompanyProfileContentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Leistungskatalog im Betriebsbereich (#13, Feature service_catalog, ab Pro).
 * Preis wird als "ab"-Betrag in Euro eingegeben und in Cent gespeichert.
 */
class Services extends Component
{
    use HasEntitlements;

    #[Locked]
    public int $companyId = 0;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $priceFrom = '';

    public string $description = '';

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
    }

    public function create(CompanyProfileContentService $contents): void
    {
        if (! $this->can(PremiumFeature::ServiceCatalog) || $this->company()->services()->count() >= $contents->servicesMax()) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $serviceId): void
    {
        if (! $this->can(PremiumFeature::ServiceCatalog)) {
            return;
        }

        $service = $this->service($serviceId);

        $this->resetForm();
        $this->editingId = (int) $service->getKey();
        $this->name = $service->name;
        $this->priceFrom = $service->price_from === null ? '' : number_format($service->price_from / 100, 2, ',', '');
        $this->description = (string) $service->description;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CompanyProfileContentService $contents): void
    {
        $company = $this->company();

        if (! $this->can(PremiumFeature::ServiceCatalog)) {
            return;
        }

        $service = $this->editingId !== null ? $this->service($this->editingId) : null;

        if ($service === null && $company->services()->count() >= $contents->servicesMax()) {
            $this->addError('name', __('portal.owner.services.limit_reached', ['max' => $contents->servicesMax()]));

            return;
        }

        // Dezimalkomma zulassen, Tausenderpunkte entfernen
        $this->priceFrom = trim($this->priceFrom);
        $normalizedPrice = str_replace(['.', ' ', '€', ','], ['', '', '', '.'], $this->priceFrom);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priceFrom' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) use ($normalizedPrice): void {
                if ($value !== '' && (! is_numeric($normalizedPrice) || (float) $normalizedPrice < 0 || (float) $normalizedPrice > 1000000)) {
                    $fail(__('portal.owner.services.errors.price'));
                }
            }],
        ], [
            'name.required' => __('portal.owner.services.errors.name_required'),
            'name.min' => __('portal.owner.services.errors.name_required'),
        ]);

        $data = [
            'name' => $this->name,
            'price_from' => $this->priceFrom === '' ? null : (int) round((float) $normalizedPrice * 100),
            'description' => $this->description ?: null,
        ];

        if ($service === null) {
            $company->services()->create([
                ...$data,
                'sort_order' => (int) $company->services()->max('sort_order') + 1,
            ]);
        } else {
            $service->update($data);
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('portal.owner.services.saved'));
    }

    public function delete(int $serviceId): void
    {
        // Loeschen auch ohne Freischaltung (Aufraeumen nach Downgrade)
        $this->service($serviceId)->delete();

        if ($this->editingId === $serviceId) {
            $this->resetForm();
        }
    }

    public function render(CompanyProfileContentService $contents): View
    {
        $services = $this->company()->services()->get();

        return view('livewire.portal.company.dashboard.services', [
            'allowed' => $this->can(PremiumFeature::ServiceCatalog),
            'services' => $services,
            'canAdd' => $services->count() < $contents->servicesMax(),
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'name', 'priceFrom', 'description');
        $this->resetValidation();
    }

    private function service(int $serviceId): CompanyService
    {
        return $this->company()->services()->whereKey($serviceId)->firstOrFail();
    }

    private function company(): Company
    {
        return $this->company ??= Company::ownedBy((int) Auth::id())
            ->when($this->companyId > 0, fn ($query) => $query->whereKey($this->companyId))
            ->firstOrFail();
    }
}
