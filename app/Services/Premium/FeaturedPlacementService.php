<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Constants\FeaturedPlacementStatus;
use App\Constants\SubscriptionStatus;
use App\Enums\PremiumFeature;
use App\Exceptions\FeaturedPlacementUnavailableException;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Top-Platzierung je Stadt x Branche (#6).
 *
 * Buchung als SaasyKit-Add-on (config premium.addon_plans); Stadt und Branche
 * stehen in company_subscriptions. Der Webhook vergibt den Slot ueber
 * syncFromSubscription(), die Kuendigung wirkt erst mit Status canceled
 * (Periodenende). Aktiv ist eine Platzierung genau solange status=active;
 * ends_at wird erst beim Beenden gesetzt.
 */
class FeaturedPlacementService
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
        private readonly CompanyPlanService $plans,
    ) {}

    public function maxSlots(): int
    {
        return (int) config('premium.featured_slots_per_city_category', FeaturedPlacement::MAX_SLOTS);
    }

    public function availableSlots(int $cityId, int $categoryId): int
    {
        $taken = FeaturedPlacement::query()
            ->active()
            ->forCityAndCategory($cityId, $categoryId)
            ->count();

        return max(0, $this->maxSlots() - $taken);
    }

    /**
     * Vergibt den niedrigsten freien Slot. Die aktiven Platzierungen der
     * Kombination werden per SELECT ... FOR UPDATE gesperrt, parallele
     * Buchungen laufen dadurch nacheinander; der Unique-Index auf active_slot
     * faengt den Rest ab. Muss im Tenant-Kontext laufen.
     *
     * @throws FeaturedPlacementUnavailableException
     */
    public function book(Company $company, int $cityId, int $categoryId, ?string $subscriptionRef): FeaturedPlacement
    {
        if (! $this->entitlements->can($company, PremiumFeature::FeaturedPlacement)) {
            throw FeaturedPlacementUnavailableException::notEntitled((int) $company->getKey());
        }

        try {
            return DB::connection((new FeaturedPlacement)->getConnectionName())->transaction(
                fn (): FeaturedPlacement => $this->assignSlot($company, $cityId, $categoryId, $subscriptionRef),
            );
        } catch (UniqueConstraintViolationException) {
            throw FeaturedPlacementUnavailableException::noFreeSlot($cityId, $categoryId);
        }
    }

    /**
     * Beendet die Platzierung und gibt den Slot frei.
     */
    public function release(FeaturedPlacement $placement, ?Carbon $endsAt = null): void
    {
        if ($placement->status !== FeaturedPlacementStatus::ACTIVE) {
            return;
        }

        $now = Carbon::now();
        $endsAt = $endsAt !== null && $endsAt->lessThan($now) ? $endsAt : $now;

        $placement->forceFill([
            'status' => FeaturedPlacementStatus::ENDED,
            'ends_at' => $endsAt,
        ])->save();
    }

    /**
     * Downgrade (#6): alle aktiven Platzierungen beenden und die Add-on-Abos
     * zum Periodenende kuendigen. Muss im Tenant-Kontext laufen.
     */
    public function releaseForCompany(Company $company): int
    {
        $placements = FeaturedPlacement::query()
            ->where('company_id', $company->getKey())
            ->active()
            ->get();

        foreach ($placements as $placement) {
            $this->release($placement);
            $this->cancelAddonSubscription($placement->subscription_ref);
        }

        return $placements->count();
    }

    /**
     * Beendet eine einzelne Platzierung und kuendigt ein zugehoeriges
     * Add-on-Abo zum Periodenende, damit der naechste Webhook den Slot nicht
     * neu vergibt. Muss im Tenant-Kontext laufen.
     */
    public function end(FeaturedPlacement $placement): void
    {
        $this->release($placement);
        $this->cancelAddonSubscription($placement->subscription_ref);
    }

    public function isAddonSubscription(Subscription $subscription): bool
    {
        $slug = $subscription->plan()->value('slug');

        return is_string($slug)
            && config("premium.addon_plans.{$slug}") === PremiumFeature::FeaturedPlacement->value;
    }

    /**
     * Webhook-Abgleich einer Add-on-Subscription: aktiv => Slot vergeben
     * (idempotent), canceled/inactive => Slot freigeben. past_due aendert
     * nichts; Stripe kuendigt nach den Mahnungen selbst.
     */
    public function syncFromSubscription(Subscription $subscription): void
    {
        $binding = $this->plans->bindingFor($subscription);

        if ($binding === null) {
            return;
        }

        $tenant = Tenant::query()->find($binding->tenant_id);

        if ($tenant === null) {
            Log::warning('FeaturedPlacementService: Tenant der Zuordnung fehlt', [
                'subscription_id' => $subscription->getKey(),
                'tenant_id' => $binding->tenant_id,
            ]);

            return;
        }

        $ref = (string) $subscription->getKey();

        $this->plans->runInTenant($tenant, function () use ($subscription, $binding, $ref): void {
            if (in_array($subscription->status, [SubscriptionStatus::CANCELED->value, SubscriptionStatus::INACTIVE->value], true)) {
                FeaturedPlacement::query()
                    ->where('subscription_ref', $ref)
                    ->active()
                    ->get()
                    ->each(fn (FeaturedPlacement $placement) => $this->release($placement, $subscription->ends_at));

                return;
            }

            if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
                return;
            }

            $company = Company::query()->find($binding->company_id);

            if ($company === null || $binding->featured_city_id === null || $binding->featured_category_id === null) {
                Log::warning('FeaturedPlacementService: Zuordnung des Add-ons unvollstaendig', [
                    'subscription_id' => $ref,
                    'company_id' => $binding->company_id,
                ]);

                return;
            }

            try {
                $this->book($company, $binding->featured_city_id, $binding->featured_category_id, $ref);
            } catch (FeaturedPlacementUnavailableException $e) {
                // Bezahlt, aber kein Slot (z.B. Downgrade oder Wettlauf nach dem Checkout-Start)
                Log::error('FeaturedPlacementService: Add-on bezahlt, Slot nicht vergeben', [
                    'subscription_id' => $ref,
                    'company_id' => $company->getKey(),
                    'reason' => $e->getMessage(),
                ]);
            }
        });
    }

    private function assignSlot(Company $company, int $cityId, int $categoryId, ?string $subscriptionRef): FeaturedPlacement
    {
        $active = FeaturedPlacement::query()
            ->active()
            ->forCityAndCategory($cityId, $categoryId)
            ->lockForUpdate()
            ->get(['id', 'company_id', 'slot', 'subscription_ref']);

        if ($subscriptionRef !== null) {
            $existing = $active->firstWhere('subscription_ref', $subscriptionRef);

            // Webhook-Wiederholung
            if ($existing !== null) {
                return FeaturedPlacement::query()->findOrFail($existing->getKey());
            }
        }

        if ($active->contains('company_id', $company->getKey())) {
            throw FeaturedPlacementUnavailableException::alreadyBooked((int) $company->getKey(), $cityId, $categoryId);
        }

        $taken = $active->pluck('slot')->all();
        $slot = collect(range(1, $this->maxSlots()))->first(fn (int $slot): bool => ! in_array($slot, $taken, true));

        if ($slot === null) {
            throw FeaturedPlacementUnavailableException::noFreeSlot($cityId, $categoryId);
        }

        return FeaturedPlacement::query()->create([
            'company_id' => $company->getKey(),
            'city_id' => $cityId,
            'category_id' => $categoryId,
            'slot' => $slot,
            'starts_at' => Carbon::now(),
            'status' => FeaturedPlacementStatus::ACTIVE,
            'subscription_ref' => $subscriptionRef,
        ]);
    }

    private function cancelAddonSubscription(?string $subscriptionRef): void
    {
        if ($subscriptionRef === null) {
            return;
        }

        $subscription = Subscription::query()->find((int) $subscriptionRef);

        if ($subscription === null
            || $subscription->is_canceled_at_end_of_cycle
            || $subscription->paymentProvider === null
            || ! $this->isAddonSubscription($subscription)) {
            return;
        }

        $subscriptions = app(SubscriptionService::class);

        if (! $subscriptions->canCancelSubscription($subscription)) {
            return;
        }

        try {
            $subscriptions->cancelSubscription(
                $subscription,
                app(PaymentService::class)->getPaymentProviderBySlug($subscription->paymentProvider->slug),
                'other',
                'Top-Platzierung nach Downgrade beendet',
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
