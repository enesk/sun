<?php

namespace App\Services;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimService
{
    public function __construct(
        private TenantPermissionService $tenantPermissionService,
    ) {}

    /**
     * Erstellt einen Claim-Request (Status: pending).
     *
     * Die Firma wird NICHT sofort zugewiesen — erst nach Admin-Approval
     * via approveClaimRequest().
     */
    public function createClaimRequest(User $user, Company $company, ?string $comment = null): ClaimRequest|false
    {
        $tenant = tenant();

        if (!$tenant) {
            Log::error('ClaimService: No tenant context for claim request', [
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);
            return false;
        }

        // Bereits geclaimed von jemand anderem
        if ($company->user_id !== null && $company->user_id !== $user->id) {
            return false;
        }

        // User besitzt bereits eine andere Firma — nur 1 Firma pro User
        $ownedCompany = Company::where('user_id', $user->id)->first();
        if ($ownedCompany) {
            Log::warning('ClaimService: User already owns a company, blocking second claim', [
                'user_id' => $user->id,
                'owned_company_id' => $ownedCompany->id,
                'requested_company_id' => $company->id,
            ]);
            return false;
        }

        // User hat bereits einen pending Claim für eine ANDERE Firma — blockieren
        $existingPendingOther = ClaimRequest::where('user_id', $user->id)
            ->where('company_id', '!=', $company->id)
            ->pending()
            ->first();

        if ($existingPendingOther) {
            Log::warning('ClaimService: User already has pending claim for another company', [
                'user_id' => $user->id,
                'existing_company_id' => $existingPendingOther->company_id,
                'requested_company_id' => $company->id,
            ]);
            return false;
        }

        // Prüfe ob bereits ein pending Claim-Request existiert
        $existingPending = ClaimRequest::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->pending()
            ->first();

        if ($existingPending) {
            return $existingPending;
        }

        try {
            $claimRequest = DB::transaction(function () use ($user, $company, $tenant, $comment) {
                // 1. Claim-Request erstellen
                $claimRequest = ClaimRequest::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'status' => ClaimRequest::STATUS_PENDING,
                    'comment' => $comment,
                ]);

                // 2. User an Tenant anhängen (damit er sich einloggen kann)
                if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                    $tenant->users()->attach($user);
                }

                return $claimRequest;
            });

            Log::info('ClaimService: Claim request created', [
                'claim_request_id' => $claimRequest->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

            return $claimRequest;
        } catch (\Exception $e) {
            Log::error('ClaimService: Failed to create claim request', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Admin genehmigt einen Claim-Request.
     *
     * JETZT wird die Firma zugewiesen + Role + first_claim markiert.
     */
    public function approveClaimRequest(ClaimRequest $claimRequest, int $reviewerId): bool
    {
        if (!$claimRequest->isPending()) {
            return false;
        }

        $tenant = tenant();

        if (!$tenant) {
            return false;
        }

        $user = User::find($claimRequest->user_id);
        $company = $claimRequest->company;

        if (!$user || !$company) {
            return false;
        }

        // Firma zwischenzeitlich von jemand anderem geclaimed?
        if ($company->user_id !== null && $company->user_id !== $user->id) {
            $claimRequest->reject($reviewerId, 'Firma wurde zwischenzeitlich von jemand anderem übernommen.');
            return false;
        }

        try {
            DB::transaction(function () use ($user, $company, $tenant, $claimRequest, $reviewerId) {
                // 1. Claim-Request genehmigen
                $claimRequest->approve($reviewerId);

                // 2. Firma zuweisen
                $company->update(['user_id' => $user->id]);

                // 3. Company-Owner Role zuweisen
                $currentRoles = $this->tenantPermissionService->getTenantUserRoles($tenant, $user);
                if (!in_array(TenancyPermissionConstants::ROLE_COMPANY_OWNER, $currentRoles)) {
                    $this->tenantPermissionService->assignTenantUserRole(
                        $tenant,
                        $user,
                        TenancyPermissionConstants::ROLE_COMPANY_OWNER,
                    );
                }
            });

            // 4. First-Claim markieren (nicht-kritisch, außerhalb Transaction)
            $user->markFirstClaim();

            Log::info('ClaimService: Claim approved', [
                'claim_request_id' => $claimRequest->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'reviewer_id' => $reviewerId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('ClaimService: Failed to approve claim', [
                'claim_request_id' => $claimRequest->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Admin lehnt einen Claim-Request ab.
     */
    public function rejectClaimRequest(ClaimRequest $claimRequest, int $reviewerId, string $reason): bool
    {
        if (!$claimRequest->isPending()) {
            return false;
        }

        $claimRequest->reject($reviewerId, $reason);

        Log::info('ClaimService: Claim rejected', [
            'claim_request_id' => $claimRequest->id,
            'user_id' => $claimRequest->user_id,
            'company_id' => $claimRequest->company_id,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Determine the claim scenario for the modal.
     *
     * Returns: 'not_logged_in', 'no_company', 'has_company', 'already_claimed', 'pending_claim'
     */
    public function getClaimScenario(?User $user, Company $company): string
    {
        // Scenario 1: Not logged in (90% of cases)
        if ($user === null) {
            return 'not_logged_in';
        }

        // Scenario 4: Already claimed by someone else
        if ($company->user_id !== null && $company->user_id !== $user->id) {
            return 'already_claimed';
        }

        // Scenario 3: Logged in, already owns a different company
        $ownedCompanyCount = Company::where('user_id', $user->id)->count();
        if ($ownedCompanyCount > 0) {
            return 'has_company';
        }

        // Scenario 5: User hat bereits einen pending Claim-Request für diese Firma
        $pendingClaim = ClaimRequest::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->pending()
            ->exists();

        if ($pendingClaim) {
            return 'pending_claim';
        }

        // Scenario 6: User hat bereits einen pending Claim für eine ANDERE Firma
        $pendingOther = ClaimRequest::where('user_id', $user->id)
            ->where('company_id', '!=', $company->id)
            ->pending()
            ->exists();

        if ($pendingOther) {
            return 'has_company';
        }

        // Scenario 2: Logged in, no company yet
        return 'no_company';
    }

    /**
     * Check if a company can be claimed (kein Owner und kein pending Claim).
     */
    public function isClaimable(Company $company): bool
    {
        if ($company->user_id !== null) {
            return false;
        }

        // Auch nicht claimbar wenn bereits ein pending Request existiert
        $hasPendingClaim = ClaimRequest::where('company_id', $company->id)
            ->pending()
            ->exists();

        return !$hasPendingClaim;
    }

    /**
     * Holt den aktuellen Claim-Request eines Users für eine Firma.
     */
    public function getClaimRequest(User $user, Company $company): ?ClaimRequest
    {
        return ClaimRequest::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();
    }

    /**
     * @deprecated Nutze createClaimRequest() + approveClaimRequest() stattdessen.
     *
     * Direkter Claim — nur noch für Admin-Override oder Tests.
     */
    public function claimCompany(User $user, Company $company, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? tenant();

        if (!$tenant) {
            Log::error('ClaimService: No tenant context for claim', [
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);
            return false;
        }

        if ($company->user_id !== null && $company->user_id !== $user->id) {
            return false;
        }

        if ($company->user_id === $user->id) {
            return true;
        }

        try {
            DB::transaction(function () use ($user, $company, $tenant) {
                $company->update(['user_id' => $user->id]);

                if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                    $tenant->users()->attach($user);
                }

                $currentRoles = $this->tenantPermissionService->getTenantUserRoles($tenant, $user);
                if (!in_array(TenancyPermissionConstants::ROLE_COMPANY_OWNER, $currentRoles)) {
                    $this->tenantPermissionService->assignTenantUserRole(
                        $tenant,
                        $user,
                        TenancyPermissionConstants::ROLE_COMPANY_OWNER,
                    );
                }
            });

            $user->markFirstClaim();

            return true;
        } catch (\Exception $e) {
            Log::error('ClaimService: Failed to claim company', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
