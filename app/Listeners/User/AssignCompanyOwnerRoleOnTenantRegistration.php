<?php

namespace App\Listeners\User;

use Illuminate\Auth\Events\Registered;

/**
 * Attaches a newly registered user to the current tenant.
 *
 * IMPORTANT: Does NOT assign any role. Roles are assigned when:
 * - User creates a company (CompanyRegistrationWizard)
 * - Admin approves a claim (ClaimService::approveClaimRequest)
 * - Admin manually assigns a role (UserTable, Filament)
 */
class AssignCompanyOwnerRoleOnTenantRegistration
{
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

        // Attach user to the current tenant (without role)
        $tenant->users()->attach($user);
    }
}
