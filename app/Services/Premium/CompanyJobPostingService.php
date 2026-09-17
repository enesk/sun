<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Models\Portal\Job;

/**
 * Stellenanzeigen-Limits (#13): Zahl gleichzeitig aktiver Anzeigen je Plan
 * ueber limit(job_postings) und Hervorhebung als Top-Job (job_highlight).
 */
class CompanyJobPostingService
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function canUse(?Company $company): bool
    {
        return $this->entitlements->can($company, PremiumFeature::JobPostings);
    }

    /**
     * Hoechstzahl aktiver Anzeigen; null bedeutet unbegrenzt.
     */
    public function activeLimit(Company $company): ?int
    {
        return $this->entitlements->limit($company, PremiumFeature::JobPostings);
    }

    public function activeCount(Company $company): int
    {
        return Job::forCompany((int) $company->getKey())->active()->count();
    }

    /**
     * Darf eine weitere Anzeige aktiv geschaltet (neu angelegt oder
     * reaktiviert) werden?
     */
    public function canActivate(Company $company): bool
    {
        if (! $this->canUse($company)) {
            return false;
        }

        $limit = $this->activeLimit($company);

        return $limit === null || $this->activeCount($company) < $limit;
    }

    public function isHighlighted(Company $company): bool
    {
        return $this->entitlements->can($company, PremiumFeature::JobHighlight);
    }

    /**
     * Upsell-Hinweis, wenn das Limit erreicht ist.
     */
    public function limitMessage(Company $company): string
    {
        return trans_choice('portal.owner.jobs.limit_reached', (int) $this->activeLimit($company), [
            'max' => (int) $this->activeLimit($company),
        ]);
    }
}
