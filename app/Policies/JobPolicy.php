<?php

namespace App\Policies;

use App\Models\Portal\Company;
use App\Models\Portal\Job;
use App\Models\User;

class JobPolicy
{
    /**
     * Resolve the company owned by the user.
     * Returns null if user doesn't own a company.
     */
    private function resolveCompany(User $user): ?Company
    {
        return Company::ownedBy($user->id)->first();
    }

    /**
     * Can the user list their jobs?
     * Always true if they have a company — the controller decides soft-lock vs. full view.
     */
    public function viewAny(User $user): bool
    {
        return $this->resolveCompany($user) !== null;
    }

    /**
     * Can the user create a new job?
     * Requires: Premium + under the active job limit.
     */
    public function create(User $user): bool
    {
        $company = $this->resolveCompany($user);

        if (! $company || ! $company->is_premium) {
            return false;
        }

        return Job::canCompanyCreateJob($company->id);
    }

    /**
     * Can the user view a specific job?
     * Requires: Ownership (job belongs to user's company).
     */
    public function view(User $user, Job $job): bool
    {
        $company = $this->resolveCompany($user);

        return $company && $job->company_id === $company->id;
    }

    /**
     * Can the user update a job?
     * Requires: Premium + Ownership.
     */
    public function update(User $user, Job $job): bool
    {
        $company = $this->resolveCompany($user);

        return $company
            && $company->is_premium
            && $job->company_id === $company->id;
    }

    /**
     * Can the user delete a job?
     * Requires: Ownership only (no premium required — users can clean up after downgrade).
     */
    public function delete(User $user, Job $job): bool
    {
        $company = $this->resolveCompany($user);

        return $company && $job->company_id === $company->id;
    }

    /**
     * Can the user toggle (activate/deactivate) a job?
     * Requires: Premium + Ownership.
     */
    public function toggle(User $user, Job $job): bool
    {
        $company = $this->resolveCompany($user);

        return $company
            && $company->is_premium
            && $job->company_id === $company->id;
    }

    /**
     * Can the user view applications for a job?
     * Requires: Premium + Ownership.
     */
    public function viewApplications(User $user, Job $job): bool
    {
        $company = $this->resolveCompany($user);

        return $company
            && $company->is_premium
            && $job->company_id === $company->id;
    }

    /**
     * Can the user manage (change status of) applications?
     * Requires: Premium + Ownership.
     */
    public function manageApplications(User $user, Job $job): bool
    {
        return $this->viewApplications($user, $job);
    }
}
