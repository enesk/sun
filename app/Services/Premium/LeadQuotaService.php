<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Enums\PremiumFeature;
use App\Exceptions\LeadQuotaExceededException;
use App\Models\Portal\Company;
use App\Models\Portal\LeadQuotaUsage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Monatliches Kontingent exklusiver Anfragen je Betrieb (#10).
 *
 * Periode ist der Kalendermonat (YYYY-MM). Der Eintrag in lead_quota_usages
 * entsteht beim ersten Zugriff im Monat und friert das Limit aus
 * CompanyEntitlementService in quota_at_period_start ein. Ein hoeheres Limit
 * (Upgrade) wird sofort uebernommen, ein niedrigeres (Downgrade, Ablauf)
 * erst mit dem naechsten Monat. Ein Reset per Cron ist nicht noetig.
 */
class LeadQuotaService
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function hasRemaining(Company $company): bool
    {
        return $this->remaining($company) > 0;
    }

    /**
     * Verbleibende Anfragen im laufenden Monat; PHP_INT_MAX bei unbegrenztem Kontingent.
     */
    public function remaining(Company $company): int
    {
        return $this->summary($company)['remaining'];
    }

    /**
     * Zaehlt eine exklusive Anfrage. Atomar: das Increment greift nur,
     * solange das Kontingent nicht erreicht ist.
     *
     * @throws LeadQuotaExceededException
     */
    public function consume(Company $company): void
    {
        $usage = $this->usage($company);

        $affected = LeadQuotaUsage::query()
            ->whereKey($usage->getKey())
            ->where(function (Builder $query): void {
                $query->whereNull('quota_at_period_start')
                    ->orWhereColumn('used_count', '<', 'quota_at_period_start');
            })
            ->increment('used_count');

        if ($affected === 0) {
            throw LeadQuotaExceededException::forCompany((int) $company->getKey(), $usage->period);
        }
    }

    /**
     * Stand des laufenden Monats fuer die Anzeige im Betriebsbereich.
     *
     * @return array{period: string, used: int, quota: ?int, remaining: int}
     */
    public function summary(Company $company): array
    {
        $usage = $this->usage($company);
        $quota = $usage->quota_at_period_start;

        return [
            'period' => $usage->period,
            'used' => $usage->used_count,
            'quota' => $quota,
            'remaining' => $quota === null ? PHP_INT_MAX : max(0, $quota - $usage->used_count),
        ];
    }

    /**
     * Eintrag des laufenden Monats; legt ihn beim ersten Zugriff an und
     * uebernimmt ein inzwischen hoeheres Limit.
     */
    private function usage(Company $company): LeadQuotaUsage
    {
        $period = LeadQuotaUsage::periodFor();
        $limit = $this->entitlements->limit($company, PremiumFeature::LeadQuota);

        // createOrFirst faengt den parallelen Erstzugriff ueber den Unique-Index ab
        $usage = LeadQuotaUsage::query()->createOrFirst(
            ['company_id' => $company->getKey(), 'period' => $period],
            ['quota_at_period_start' => $limit],
        );

        if ($usage->quota_at_period_start === null || ! $this->isRaise($usage->quota_at_period_start, $limit)) {
            return $usage;
        }

        // Nur anheben, nie senken – auch wenn parallel bereits angehoben wurde
        LeadQuotaUsage::query()
            ->whereKey($usage->getKey())
            ->whereNotNull('quota_at_period_start')
            ->when($limit !== null, fn (Builder $query) => $query->where('quota_at_period_start', '<', $limit))
            ->update(['quota_at_period_start' => $limit]);

        return $usage->refresh();
    }

    private function isRaise(int $frozen, ?int $limit): bool
    {
        return $limit === null || $limit > $frozen;
    }
}
