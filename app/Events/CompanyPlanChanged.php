<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PlanTier;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Die gebuchte Stufe oder Laufzeit eines Betriebs hat sich geaendert (#4).
 * Gefeuert von Checkout/Subscription-Lifecycle (#5) und der Plan-Verwaltung
 * (#17); leert den Entitlement-Cache des Betriebs.
 *
 * tenantId ist optional und faellt auf den aktiven Tenant zurueck.
 */
class CompanyPlanChanged
{
    use Dispatchable;

    public readonly ?string $tenantId;

    public function __construct(
        public readonly int $companyId,
        public readonly ?PlanTier $oldTier,
        public readonly PlanTier $newTier,
        ?string $tenantId = null,
    ) {
        $this->tenantId = $tenantId ?? (tenant() ? (string) tenant()->getTenantKey() : null);
    }
}
