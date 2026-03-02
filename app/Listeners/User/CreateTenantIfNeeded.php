<?php

namespace App\Listeners\User;

use App\Services\SessionService;
use App\Services\TenantCreationService;
use Illuminate\Auth\Events\Registered;

class CreateTenantIfNeeded
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SessionService $sessionService,
        private TenantCreationService $tenantCreationService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        // SECURITY: Never auto-create tenants when registering on a tenant domain.
        // Our portals (sanitaerfinden.com, firmenfreund.de etc.) are pre-existing tenants.
        // Users register on these portals to become company owners, NOT to get their own tenant.
        // Auto-tenant-creation is only valid for the central SaaS domain (if ever needed).
        if (tenant() !== null) {
            return;
        }

        if ($this->sessionService->shouldCreateTenantForFreePlanUser() ||
            config('app.create_tenant_on_user_registration', false)
        ) {
            $this->tenantCreationService->createTenantForFreePlanUser($event->user);
            $this->sessionService->resetCreateTenantForFreePlanUser();
        }
    }
}
