<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Enums\PlanTier;
use App\Events\CompanyPlanChanged;
use App\Exceptions\FeaturedPlacementUnavailableException;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Manuelle Eingriffe ins Premium-Modul aus dem Filament-Dashboard (#18):
 * Plaene ohne Stripe vergeben (Testkunden, Partner, Kulanz) und
 * Top-Platzierungen von Hand vergeben oder beenden.
 *
 * Manuell = subscription_ref leer. Solche Plaene laufen wie gebuchte ueber
 * premium:process-expirations aus. Jede Aktion landet im Log-Channel
 * premium-admin. Muss im Tenant-Kontext laufen.
 */
class PremiumAdminService
{
    public const LOG_CHANNEL = 'premium-admin';

    public function __construct(
        private readonly FeaturedPlacementService $placements,
    ) {}

    /**
     * Setzt Stufe und Laufzeit ohne Subscription. Bei Free wird die Laufzeit
     * geleert; Top-Platzierungen beendet dann der Listener auf CompanyPlanChanged.
     */
    public function setPlanManually(Company $company, PlanTier $tier, ?Carbon $endsAt, string $note, User $actor): void
    {
        $oldTier = $company->plan_tier;
        $before = $this->planSnapshot($company);
        $now = Carbon::now();

        $isNewPlan = $oldTier !== $tier || $company->subscription_ref !== null;

        $company->forceFill([
            'plan_tier' => $tier,
            'plan_started_at' => $tier === PlanTier::Free ? null : ($isNewPlan || $company->plan_started_at === null ? $now : $company->plan_started_at),
            'plan_ends_at' => $tier === PlanTier::Free ? null : $endsAt?->copy()->endOfDay(),
            'plan_grace_until' => null,
            'subscription_ref' => null,
        ])->save();

        CompanyPlanChanged::dispatch((int) $company->getKey(), $oldTier, $tier);

        $this->log('Plan manuell gesetzt', $actor, $note, [
            'company_id' => $company->getKey(),
            'company' => $company->name,
            'before' => $before,
            'after' => $this->planSnapshot($company),
        ]);
    }

    /**
     * Vergibt den niedrigsten freien Slot ohne Add-on-Abo. Die Stufe des
     * Betriebs muss die Top-Platzierung enthalten.
     *
     * @throws FeaturedPlacementUnavailableException
     */
    public function assignSlotManually(Company $company, int $cityId, int $categoryId, string $note, User $actor): FeaturedPlacement
    {
        $placement = $this->placements->book($company, $cityId, $categoryId, null);

        $this->log('Top-Platzierung manuell vergeben', $actor, $note, [
            'placement_id' => $placement->getKey(),
            'company_id' => $company->getKey(),
            'company' => $company->name,
            'city_id' => $cityId,
            'category_id' => $categoryId,
            'slot' => $placement->slot,
        ]);

        return $placement;
    }

    /**
     * Beendet die Platzierung; ein zugehoeriges Add-on-Abo wird zum
     * Periodenende gekuendigt.
     */
    public function endPlacement(FeaturedPlacement $placement, string $note, User $actor): void
    {
        $this->placements->end($placement);

        $this->log('Top-Platzierung beendet', $actor, $note, [
            'placement_id' => $placement->getKey(),
            'company_id' => $placement->company_id,
            'city_id' => $placement->city_id,
            'category_id' => $placement->category_id,
            'slot' => $placement->slot,
            'subscription_ref' => $placement->subscription_ref,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function planSnapshot(Company $company): array
    {
        return [
            'tier' => $company->plan_tier?->value,
            'started_at' => $company->plan_started_at?->toIso8601String(),
            'ends_at' => $company->plan_ends_at?->toIso8601String(),
            'grace_until' => $company->plan_grace_until?->toIso8601String(),
            'subscription_ref' => $company->subscription_ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, User $actor, string $note, array $context): void
    {
        Log::channel(self::LOG_CHANNEL)->info($message, [
            'tenant' => tenant()?->getTenantKey(),
            'user_id' => $actor->getKey(),
            'user' => $actor->email,
            'note' => $note,
            ...$context,
        ]);
    }
}
