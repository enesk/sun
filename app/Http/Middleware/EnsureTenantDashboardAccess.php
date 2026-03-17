<?php

namespace App\Http\Middleware;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user has access to the tenant dashboard.
 *
 * Checks:
 * 1. User is authenticated
 * 2. Tenant is initialized (tenancy context active)
 * 3. User is assigned to this tenant (tenant_user pivot exists)
 * 4. User has a dashboard-relevant role (admin, company_owner) or is global admin
 *
 * Runs AFTER InitializeTenancyByDomain so tenant() is available.
 */
class EnsureTenantDashboardAccess
{
    public function __construct(
        private TenantPermissionService $permissionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $user = Auth::user();

        // Check if user is assigned to this tenant
        if (! $user->tenants()->whereKey($tenant)->exists()) {
            abort(403, 'Sie haben keinen Zugriff auf diesen Bereich.');
        }

        // Only global admins and tenant admins have access to /verwaltung
        // Company owners use /firmenprofil instead
        if (! $user->isAdmin()) {
            $roles = $this->permissionService->getTenantUserRoles($tenant, $user);

            if (! in_array(TenancyPermissionConstants::ROLE_ADMIN, $roles, true)) {
                abort(403, 'Sie haben keinen Zugriff auf diesen Bereich.');
            }
        }

        // Share tenant and user data with all views
        view()->share('dashboardTenant', $tenant);
        view()->share('dashboardUser', $user);

        // Build permission map for navigation visibility
        $permissions = $this->buildPermissionMap($tenant, $user);
        view()->share('dashboardPermissions', $permissions);

        return $next($request);
    }

    private function buildPermissionMap($tenant, $user): array
    {
        return [
            'manage_companies' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_VIEW_OWN_COMPANY),
            'manage_reviews' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_OWN_REVIEWS),
            'manage_categories' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS),
            'manage_cities' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS),
            'view_subscriptions' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_VIEW_SUBSCRIPTIONS),
            'view_orders' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_VIEW_ORDERS),
            'view_transactions' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_VIEW_TRANSACTIONS),
            'manage_team' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_TEAM),
            'invite_members' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS),
            'manage_roles' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_VIEW_ROLES),
            'update_settings' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS),
            'manage_claims' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_CLAIMS),
            'manage_ads' => $user->isAdmin() || $this->permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_ADS),
            'is_admin' => $user->isAdmin(),
        ];
    }
}
