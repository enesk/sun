<?php

declare(strict_types=1);

namespace App\Listeners\Premium;

use App\Events\CompanyPlanChanged;
use App\Services\Premium\CompanyEntitlementService;

/**
 * Leert Memo und Cache der Freischaltungen nach einer Plan-Aenderung (#4).
 * Bewusst synchron, damit der naechste Zugriff im selben Request stimmt.
 */
class ForgetCompanyEntitlements
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function handle(CompanyPlanChanged $event): void
    {
        $this->entitlements->forget($event->companyId, $event->tenantId);
    }
}
