<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Constants\FeaturedPlacementStatus;
use App\Constants\SubscriptionStatus;
use App\Enums\PlanTier;
use App\Models\CompanySubscription;
use App\Models\Interval;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Models\Tenant;
use App\Services\CurrencyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kennzahlen des Premium-Moduls fuer das Admin-Dashboard (#18).
 *
 * MRR rechnet wie MetricsService::calculateMRR(), aber nur ueber
 * Subscriptions mit Betriebs-Zuordnung (company_subscriptions), optional je
 * Tenant. Betriebe und Placements liegen in den Tenant-DBs und werden je
 * Portal gezaehlt; das Ergebnis ist 5 Minuten gecacht (zentraler Kontext).
 */
class PremiumRevenueService
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly CompanyPlanService $plans,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @return array{tiers: array<string, int>, placements: int, mrr_cents: int, currency: string, failed_tenants: list<string>}
     */
    public function summary(?int $tenantId = null): array
    {
        return Cache::remember(
            'central.premium.revenue.'.($tenantId ?? 'all'),
            self::CACHE_TTL,
            fn (): array => $this->calculate($tenantId),
        );
    }

    /**
     * Monatlich wiederkehrender Umsatz in Cent (Metrics-Waehrung).
     */
    public function mrrCents(?int $tenantId = null, ?Carbon $date = null): int
    {
        $date ??= Carbon::now();

        $daysPerInterval = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 360];
        $cases = Interval::all()
            ->filter(fn (Interval $interval): bool => isset($daysPerInterval[$interval->name]))
            ->map(fn (Interval $interval): string => "WHEN s.interval_id = {$interval->id} THEN 1.0 * s.price * s.interval_count / {$daysPerInterval[$interval->name]} * 30")
            ->implode("\n");

        if ($cases === '') {
            return 0;
        }

        $mrr = DB::connection(config('tenancy.database.central_connection'))
            ->table('subscriptions as s')
            ->whereExists(fn ($query) => $query
                ->from((new CompanySubscription)->getTable(), 'cs')
                ->whereColumn('cs.subscription_id', 's.id')
                ->when($tenantId !== null, fn ($query) => $query->where('cs.tenant_id', $tenantId)))
            ->where('s.status', SubscriptionStatus::ACTIVE->value)
            ->where('s.ends_at', '>=', $date)
            ->where('s.created_at', '<=', $date)
            ->where(fn ($query) => $query->whereNull('s.trial_ends_at')->orWhere('s.trial_ends_at', '<', $date))
            ->value(DB::raw("SUM(CASE {$cases} ELSE 0 END)"));

        return (int) round((float) $mrr);
    }

    /**
     * @return array{tiers: array<string, int>, placements: int, mrr_cents: int, currency: string, failed_tenants: list<string>}
     */
    private function calculate(?int $tenantId): array
    {
        $tiers = array_fill_keys(PlanTier::values(), 0);
        $placements = 0;
        $failed = [];

        $tenants = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))
            ->orderBy('id')
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $counts = $this->plans->runInTenant($tenant, fn (): array => [
                    'tiers' => Company::query()
                        ->where('is_active', true)
                        ->selectRaw('plan_tier, COUNT(*) AS aggregate')
                        ->groupBy('plan_tier')
                        ->pluck('aggregate', 'plan_tier')
                        ->all(),
                    'placements' => FeaturedPlacement::query()
                        ->where('status', FeaturedPlacementStatus::ACTIVE->value)
                        ->count(),
                ]);
            } catch (Throwable $e) {
                report($e);
                $failed[] = (string) ($tenant->name ?? $tenant->getKey());

                continue;
            }

            foreach ($counts['tiers'] as $tier => $count) {
                $tiers[$tier] = ($tiers[$tier] ?? 0) + (int) $count;
            }

            $placements += $counts['placements'];
        }

        return [
            'tiers' => $tiers,
            'placements' => $placements,
            'mrr_cents' => $this->mrrCents($tenantId),
            'currency' => $this->currencies->getMetricsCurrency()->code,
            'failed_tenants' => $failed,
        ];
    }
}
