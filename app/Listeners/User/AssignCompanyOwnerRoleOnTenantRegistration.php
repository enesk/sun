<?php

namespace App\Listeners\User;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Events\Registered;

class AssignCompanyOwnerRoleOnTenantRegistration
{
    public function __construct(
        private TenantPermissionService $tenantPermissionService,
    ) {}

    public function handle(Registered $event): void
    {
        $tenant = tenant();

        if ($tenant === null) {
            return;
        }

        $user = $event->user;

        // User already attached to this tenant? Skip.
        if ($user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        // Attach user to the current tenant
        $tenant->users()->attach($user);

        // Assign company_owner role
        $this->tenantPermissionService->assignTenantUserRole(
            $tenant,
            $user,
            TenancyPermissionConstants::ROLE_COMPANY_OWNER,
        );
    }
}
