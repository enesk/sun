<?php

declare(strict_types=1);

namespace App\Listeners\Premium;

use App\Enums\PremiumFeature;
use App\Events\CompanyPlanChanged;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\Premium\CompanyPlanService;
use App\Services\Premium\FeaturedPlacementService;

/**
 * Faellt die Top-Platzierung aus der Stufe (z.B. Premium => Pro oder Free),
 * werden die Platzierungen beendet und die Slots frei (#6).
 * Synchron, damit die Listen sofort ohne den Betrieb sortieren.
 */
class ReleaseFeaturedPlacementsOnDowngrade
{
    public function __construct(
        private readonly CompanyPlanService $plans,
        private readonly FeaturedPlacementService $placements,
    ) {}

    public function handle(CompanyPlanChanged $event): void
    {
        if ($event->newTier->hasFeature(PremiumFeature::FeaturedPlacement) || $event->tenantId === null) {
            return;
        }

        $tenant = Tenant::query()->where((new Tenant)->getTenantKeyName(), $event->tenantId)->first();

        if ($tenant === null) {
            return;
        }

        $this->plans->runInTenant($tenant, function () use ($event): void {
            $company = Company::query()->find($event->companyId);

            if ($company !== null) {
                $this->placements->releaseForCompany($company);
            }
        });
    }
}
