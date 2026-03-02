<?php

namespace App\Console\Commands;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Illuminate\Console\Command;

class FixUserAdminRoles extends Command
{
    protected $signature = 'app:fix-user-admin-roles
                            {--tenant= : Nur für einen bestimmten Tenant (ID)}
                            {--dry-run : Nur anzeigen, nicht ändern}';

    protected $description = 'Findet Portal-User mit fälschlich zugewiesener Admin-Rolle und korrigiert sie auf company_owner';

    public function handle(TenantPermissionService $permissionService): int
    {
        $dryRun = $this->option('dry-run');
        $tenantFilter = $this->option('tenant');

        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine Änderungen werden vorgenommen.');
        }

        $tenants = $tenantFilter
            ? Tenant::where('id', $tenantFilter)->get()
            : Tenant::all();

        $totalFixed = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->info("\n--- Tenant: {$tenant->name} (ID: {$tenant->id}) ---");

            // Load company ownership data from tenant DB
            $companyOwnership = [];
            $tenant->run(function () use (&$companyOwnership) {
                $companies = Company::whereNotNull('user_id')->get(['id', 'user_id', 'name']);
                foreach ($companies as $company) {
                    $companyOwnership[$company->user_id] = $company->name;
                }
            });

            $users = $tenant->users()->get();

            foreach ($users as $user) {
                $roles = $permissionService->getTenantUserRoles($tenant, $user);

                // Skip global admins (is_admin = true) — they SHOULD have admin role
                if ($user->is_admin) {
                    $totalSkipped++;
                    continue;
                }

                // Non-admin user with admin tenant role → PROBLEM
                if (in_array(TenancyPermissionConstants::ROLE_ADMIN, $roles, true)) {
                    $hasCompany = isset($companyOwnership[$user->id]);
                    $companyName = $companyOwnership[$user->id] ?? null;
                    $newRole = $hasCompany
                        ? TenancyPermissionConstants::ROLE_COMPANY_OWNER
                        : TenancyPermissionConstants::ROLE_USER;

                    if ($dryRun) {
                        $this->error("  ✗ {$user->email} — hat admin-Rolle (sollte {$newRole} sein)" .
                            ($hasCompany ? " — Firma: {$companyName}" : ''));
                    } else {
                        $permissionService->assignTenantUserRole($tenant, $user, $newRole);

                        $this->warn("  → {$user->email} — admin → {$newRole} korrigiert" .
                            ($hasCompany ? " (Firma: {$companyName})" : ''));
                    }

                    $totalFixed++;
                    continue;
                }

                $totalSkipped++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("Ergebnis (DRY-RUN): {$totalFixed} User mit falscher Admin-Rolle gefunden, {$totalSkipped} korrekt.");
            if ($totalFixed > 0) {
                $this->info("Erneut ohne --dry-run ausführen um die Rollen zu korrigieren.");
            }
        } else {
            $this->info("Ergebnis: {$totalFixed} User korrigiert, {$totalSkipped} unverändert.");
        }

        return self::SUCCESS;
    }
}
