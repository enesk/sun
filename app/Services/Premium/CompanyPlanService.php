<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Constants\SubscriptionStatus;
use App\Enums\PlanTier;
use App\Events\CompanyPlanChanged;
use App\Models\CompanySubscription;
use App\Models\Portal\Company;
use App\Models\Subscription;
use App\Models\Tenant;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Subscription-Lifecycle des Premium-Moduls (#5).
 *
 * Spiegelt SaasyKit-Subscriptions (Central-DB) auf die Plan-Felder der
 * Betriebe (Tenant-DB). Die Zuordnung steht in company_subscriptions und wird
 * beim Checkout-Start aus dem Betriebsbereich angelegt. Beim Downgrade werden
 * keine Daten geloescht; ausgespielt wird nur, was CompanyEntitlementService
 * freigibt.
 */
class CompanyPlanService
{
    public const CHECKOUT_SESSION_KEY = 'premium.checkout_company';

    /**
     * Merkt sich vor dem Sprung in den SaasyKit-Checkout, fuer welchen
     * Betrieb gebucht wird (siehe bindPendingCheckout()). Stadt und Branche
     * nur beim Add-on Top-Platzierung (#6).
     */
    public function rememberCheckoutCompany(Tenant $tenant, Company $company, ?int $featuredCityId = null, ?int $featuredCategoryId = null): void
    {
        session()->put(self::CHECKOUT_SESSION_KEY, [
            'tenant_id' => (int) $tenant->getKey(),
            'company_id' => (int) $company->getKey(),
            'featured_city_id' => $featuredCityId,
            'featured_category_id' => $featuredCategoryId,
        ]);
    }

    /**
     * Ordnet eine im Checkout angelegte Subscription dem gemerkten Betrieb zu.
     */
    public function bindPendingCheckout(Subscription $subscription): ?CompanySubscription
    {
        $pending = session()->get(self::CHECKOUT_SESSION_KEY);

        if (! is_array($pending) || (int) ($pending['tenant_id'] ?? 0) !== (int) $subscription->tenant_id) {
            return null;
        }

        session()->forget(self::CHECKOUT_SESSION_KEY);

        return CompanySubscription::query()->updateOrCreate(
            ['subscription_id' => $subscription->getKey()],
            [
                'tenant_id' => $subscription->tenant_id,
                'company_id' => (int) $pending['company_id'],
                'featured_city_id' => isset($pending['featured_city_id']) ? (int) $pending['featured_city_id'] : null,
                'featured_category_id' => isset($pending['featured_category_id']) ? (int) $pending['featured_category_id'] : null,
            ],
        );
    }

    /**
     * Stripe-Metadaten der Zuordnung; leer, wenn die Subscription keinem Betrieb gehoert.
     *
     * @return array<string, string>
     */
    public function metadataFor(Subscription $subscription): array
    {
        $binding = $this->bindingFor($subscription);

        if ($binding === null) {
            return [];
        }

        return [
            'tenant_id' => (string) $binding->tenant_id,
            'company_id' => (string) $binding->company_id,
        ];
    }

    public function bindingFor(Subscription $subscription): ?CompanySubscription
    {
        return CompanySubscription::query()
            ->where('subscription_id', $subscription->getKey())
            ->first();
    }

    /**
     * Laufende Stufen-Subscription (active/past_due) des Betriebs, ohne Add-ons.
     */
    public function currentSubscription(Tenant $tenant, Company $company): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->whereIn('id', CompanySubscription::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('company_id', $company->getKey())
                ->select('subscription_id'))
            ->whereIn('status', [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::PAST_DUE->value])
            ->latest('id')
            ->get()
            ->first(fn (Subscription $subscription): bool => PlanTier::forPlanSlug($subscription->plan?->slug) !== null);
    }

    /**
     * Ende der laufenden Vertragsperiode: Vertragsbeginn plus so viele volle
     * Laufzeiten (12 Monate), dass das Ende in der Zukunft liegt.
     */
    public function termEndsAt(Subscription $subscription): Carbon
    {
        $months = max(1, (int) config('premium.contract.term_months', 12));
        $start = Carbon::parse($subscription->created_at ?? now());
        $end = $start->copy()->addMonths($months);

        while ($end->isPast()) {
            $end = $end->addMonths($months);
        }

        return $end;
    }

    /**
     * Letzter Tag, an dem fristgerecht zum Ende der laufenden Periode
     * gekuendigt werden kann (3 Monate vorher).
     */
    public function cancellationDeadline(Subscription $subscription): Carbon
    {
        return $this->termEndsAt($subscription)
            ->copy()
            ->subMonths(max(0, (int) config('premium.contract.notice_months', 3)));
    }

    /**
     * Nur innerhalb der Frist kuendbar; danach laeuft der Vertrag um eine
     * weitere Laufzeit weiter.
     */
    public function canCancelNow(Subscription $subscription): bool
    {
        return now()->lessThanOrEqualTo($this->cancellationDeadline($subscription));
    }

    /**
     * Uebernimmt Stufe und Laufzeit aus der Subscription (created/updated,
     * Planwechsel, Verlaengerung). Bei past_due bleibt die bisherige Laufzeit
     * stehen, damit die Grace Period greift.
     */
    public function syncFromSubscription(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::CANCELED->value
            || $subscription->status === SubscriptionStatus::INACTIVE->value) {
            $this->handleCancelled($subscription);

            return;
        }

        $tier = PlanTier::forPlanSlug($subscription->plan()->value('slug'));

        if ($tier === null || ! in_array($subscription->status, [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::PAST_DUE->value], true)) {
            return;
        }

        $this->updateCompany($subscription, function (Company $company) use ($subscription, $tier): void {
            $isNewPlan = $company->plan_tier !== $tier || $company->subscription_ref !== (string) $subscription->getKey();

            $company->plan_tier = $tier;
            $company->subscription_ref = (string) $subscription->getKey();

            if ($isNewPlan || $company->plan_started_at === null) {
                $company->plan_started_at = Carbon::now();
            }

            if ($subscription->status === SubscriptionStatus::ACTIVE->value) {
                $company->plan_ends_at = $subscription->ends_at;
                $company->plan_grace_until = null;
            }
        });
    }

    /**
     * Zahlungsausfall: Grace Period starten. Liefert true, wenn sie neu
     * gesetzt wurde (Stripe meldet jeden Wiederholungsversuch erneut).
     */
    public function handlePaymentFailed(Subscription $subscription): bool
    {
        if (PlanTier::forPlanSlug($subscription->plan()->value('slug')) === null) {
            return false;
        }

        $started = false;

        $this->updateCompany($subscription, function (Company $company) use (&$started): void {
            if ($company->plan_tier === PlanTier::Free || $company->plan_grace_until !== null) {
                return;
            }

            $now = Carbon::now();

            // Stripe verlaengert die Periode auch bei fehlgeschlagener Zahlung;
            // freigeschaltet bleibt ab jetzt nur noch die Grace Period.
            if ($company->plan_ends_at === null || $company->plan_ends_at->greaterThan($now)) {
                $company->plan_ends_at = $now;
            }

            $company->plan_grace_until = $now->copy()->addDays((int) config('premium.grace_period_days', 7));
            $started = true;
        });

        return $started;
    }

    /**
     * Subscription beendet (Kuendigung zum Periodenende ist erreicht oder
     * Stripe hat nach den Mahnungen gekuendigt). Der Downgrade selbst laeuft
     * ueber premium:process-expirations.
     */
    public function handleCancelled(Subscription $subscription): void
    {
        if (PlanTier::forPlanSlug($subscription->plan()->value('slug')) === null) {
            return;
        }

        $this->updateCompany($subscription, function (Company $company) use ($subscription): void {
            $now = Carbon::now();
            $endsAt = $subscription->ends_at;

            $company->plan_ends_at = $endsAt !== null && $endsAt->lessThan($now) ? $endsAt : $now;
        });
    }

    /**
     * Setzt den Betrieb auf Free und beendet seine Top-Platzierungen.
     * Muss im Tenant-Kontext laufen.
     */
    public function downgradeToFree(Company $company): void
    {
        $oldTier = $company->plan_tier;

        $company->forceFill([
            'plan_tier' => PlanTier::Free,
            'plan_grace_until' => null,
        ])->save();

        // Lazy aufgeloest: FeaturedPlacementService haengt selbst an diesem Service (#6)
        app(FeaturedPlacementService::class)->releaseForCompany($company);

        CompanyPlanChanged::dispatch((int) $company->getKey(), $oldTier, PlanTier::Free);
    }

    /**
     * Fuehrt $callback im Tenant aus und stellt den vorherigen Kontext wieder her.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runInTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previous = tenancy()->initialized ? tenant() : null;

        if ($previous !== null && $previous->getTenantKey() === $tenant->getTenantKey()) {
            return $callback();
        }

        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                tenancy()->initialize($previous);
            } else {
                tenancy()->end();
            }
        }
    }

    /**
     * @param  Closure(Company): void  $mutate
     */
    private function updateCompany(Subscription $subscription, Closure $mutate): void
    {
        $binding = $this->bindingFor($subscription);

        if ($binding === null) {
            return;
        }

        $tenant = Tenant::query()->find($binding->tenant_id);

        if ($tenant === null) {
            Log::warning('CompanyPlanService: Tenant der Zuordnung fehlt', [
                'subscription_id' => $subscription->getKey(),
                'tenant_id' => $binding->tenant_id,
            ]);

            return;
        }

        $this->runInTenant($tenant, function () use ($binding, $subscription, $mutate, $tenant): void {
            $company = Company::query()->find($binding->company_id);

            if ($company === null) {
                Log::warning('CompanyPlanService: Betrieb der Zuordnung fehlt', [
                    'subscription_id' => $subscription->getKey(),
                    'company_id' => $binding->company_id,
                ]);

                return;
            }

            // Eine andere laufende Subscription des Betriebs nicht ueberschreiben
            if ($company->subscription_ref !== null
                && $company->subscription_ref !== (string) $subscription->getKey()
                && $subscription->status !== SubscriptionStatus::ACTIVE->value) {
                return;
            }

            $oldTier = $company->plan_tier;

            $mutate($company);

            if (! $company->isDirty()) {
                return;
            }

            $company->save();

            CompanyPlanChanged::dispatch(
                (int) $company->getKey(),
                $oldTier,
                $company->plan_tier ?? PlanTier::Free,
                (string) $tenant->getTenantKey(),
            );
        });
    }
}
