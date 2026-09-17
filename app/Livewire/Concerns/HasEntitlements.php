<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyEntitlementService;

/**
 * Freischaltungen in Livewire-Komponenten (#4). Die Komponente liefert den
 * Betrieb ueber entitlementCompany(); geprueft wird nur ueber den Service.
 */
trait HasEntitlements
{
    abstract protected function entitlementCompany(): ?Company;

    public function can(PremiumFeature $feature): bool
    {
        return app(CompanyEntitlementService::class)->can($this->entitlementCompany(), $feature);
    }

    public function entitlementLimit(PremiumFeature $feature): ?int
    {
        $company = $this->entitlementCompany();

        return $company === null ? 0 : app(CompanyEntitlementService::class)->limit($company, $feature);
    }
}
