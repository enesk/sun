<?php

namespace App\Policies;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Company;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;

class CompanyPolicy
{
    public function __construct(
        private TenantPermissionService $tenantPermissionService
    ) {}

    public function viewAny(User $user): bool
    {
        // Admins can always view all companies
        if ($user->isAdmin()) {
            return true;
        }

        // Tenant admins can view all companies
        return $this->tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            $user,
            TenancyPermissionConstants::PERMISSION_VIEW_OWN_COMPANY,
        );
    }

    public function view(User $user, Company $company): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Company owners can view their own company
        return $company->user_id === $user->id
            && $this->tenantPermissionService->tenantUserHasPermissionTo(
                Filament::getTenant(),
                $user,
                TenancyPermissionConstants::PERMISSION_VIEW_OWN_COMPANY,
            );
    }

    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            $user,
            TenancyPermissionConstants::PERMISSION_CREATE_COMPANY,
        );
    }

    public function update(User $user, Company $company): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $company->user_id === $user->id
            && $this->tenantPermissionService->tenantUserHasPermissionTo(
                Filament::getTenant(),
                $user,
                TenancyPermissionConstants::PERMISSION_UPDATE_OWN_COMPANY,
            );
    }

    public function delete(User $user, Company $company): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $company->user_id === $user->id
            && $this->tenantPermissionService->tenantUserHasPermissionTo(
                Filament::getTenant(),
                $user,
                TenancyPermissionConstants::PERMISSION_DELETE_OWN_COMPANY,
            );
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }
}
