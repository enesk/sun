<?php

declare(strict_types=1);

namespace App\Console\Commands\Premium;

use App\Constants\SubscriptionStatus;
use App\Enums\PlanTier;
use App\Models\Portal\Company;
use App\Models\Subscription;
use App\Services\Premium\CompanyPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Auto-Downgrade abgelaufener Plaene (#5).
 *
 * Laeuft je Tenant ueber `tenants:run premium:process-expirations`.
 * Abgelaufen ist ein Betrieb, dessen plan_ends_at und plan_grace_until
 * ueberschritten sind. Ist die zugehoerige Subscription noch aktiv (Webhook
 * verspaetet), wird stattdessen neu synchronisiert. Daten bleiben erhalten.
 */
class ProcessPlanExpirations extends Command
{
    protected $signature = 'premium:process-expirations
        {--dry-run : Nur anzeigen, nichts aendern}';

    protected $description = 'Setzt Betriebe mit abgelaufenem Plan bzw. abgelaufener Grace Period auf Free';

    public function handle(CompanyPlanService $plans): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext ausfuehrbar: php artisan tenants:run premium:process-expirations');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $dryRun = (bool) $this->option('dry-run');
        $downgraded = 0;
        $resynced = 0;

        Company::query()
            ->where('plan_tier', '!=', PlanTier::Free->value)
            ->whereNotNull('plan_ends_at')
            ->where('plan_ends_at', '<', $now)
            ->where(fn ($query) => $query->whereNull('plan_grace_until')->orWhere('plan_grace_until', '<', $now))
            ->orderBy('id')
            ->each(function (Company $company) use ($plans, $now, $dryRun, &$downgraded, &$resynced): void {
                $subscription = $company->subscription_ref !== null
                    ? Subscription::query()->find((int) $company->subscription_ref)
                    : null;

                if ($subscription !== null
                    && $subscription->status === SubscriptionStatus::ACTIVE->value
                    && $subscription->ends_at?->greaterThan($now)) {
                    $this->line("  Neu synchronisiert: #{$company->id} {$company->name}");
                    $resynced++;

                    if (! $dryRun) {
                        $plans->syncFromSubscription($subscription);
                    }

                    return;
                }

                $this->line("  Auf Free gesetzt: #{$company->id} {$company->name} ({$company->plan_tier?->value})");
                $downgraded++;

                if (! $dryRun) {
                    $plans->downgradeToFree($company);
                }
            });

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}".tenant()->getTenantKey().": {$downgraded} zurueckgestuft, {$resynced} neu synchronisiert.");

        return self::SUCCESS;
    }
}
