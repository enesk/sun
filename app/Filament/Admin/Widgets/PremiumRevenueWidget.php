<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\PlanTier;
use App\Services\Premium\PremiumRevenueService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Premium-Umsatz (#18): Betriebe je Stufe, aktive Top-Platzierungen und MRR
 * der Betriebs-Subscriptions; Portal ueber den Dashboard-Filter tenant_id.
 */
class PremiumRevenueWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('Premium-Umsatz');
    }

    protected function getStats(): array
    {
        $tenantId = filled($this->pageFilters['tenant_id'] ?? null) ? (int) $this->pageFilters['tenant_id'] : null;
        $summary = app(PremiumRevenueService::class)->summary($tenantId);

        $stats = [
            Stat::make(__('Premium-MRR'), money($summary['mrr_cents'], $summary['currency']))
                ->description(__('aktive Betriebs-Abos inkl. Add-ons')),
        ];

        foreach ([PlanTier::Pro, PlanTier::Premium, PlanTier::Free] as $tier) {
            $stats[] = Stat::make(__('Betriebe :tier', ['tier' => $tier->label()]), (string) ($summary['tiers'][$tier->value] ?? 0));
        }

        $placements = Stat::make(__('Aktive Top-Platzierungen'), (string) $summary['placements']);

        if ($summary['failed_tenants'] !== []) {
            $placements->description(__('Nicht erreichbar: :tenants', ['tenants' => implode(', ', $summary['failed_tenants'])]))
                ->color('danger');
        }

        $stats[] = $placements;

        return $stats;
    }
}
