<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\CompanySubscription;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PaymentProviders\PaymentService;
use App\Services\Premium\CompanyPlanService;
use App\Services\Premium\FeaturedPlacementService;
use App\Services\SubscriptionService;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Buchung der Top-Platzierung im Betriebsbereich (#6).
 *
 * Gebucht wird je Branche des Betriebs in seiner Stadt, jeweils als eigenes
 * Add-on (featured-monthly). Hier wird nur geprueft und in den
 * SaasyKit-Checkout gesprungen; den Slot vergibt der Webhook ueber
 * FeaturedPlacementService. Kuendigung wirkt zum Periodenende.
 */
class FeaturedPlacementBooking extends Component
{
    use HasEntitlements;

    private const PRICE_KEY = 'featured_monthly';

    #[Locked]
    public int $companyId;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Company $company): void
    {
        $this->companyId = (int) $company->getKey();
    }

    public function book(int $categoryId, FeaturedPlacementService $placements, CompanyPlanService $plans)
    {
        $this->reset('message', 'error');

        $company = $this->company();
        $slug = TenantPremiumPricing::planSlug(self::PRICE_KEY);

        if ($slug === null || ! $this->tenant()->premiumPricing()->isFeaturedSaleEnabled()) {
            $this->error = __('Die Top-Platzierung ist derzeit nicht buchbar.');

            return null;
        }

        if (! $this->can(PremiumFeature::FeaturedPlacement)) {
            $this->error = __('Die Top-Platzierung ist nur mit dem Premium-Paket buchbar.');

            return null;
        }

        if ($company->city_id === null || ! $company->categories()->whereKey($categoryId)->exists()) {
            $this->error = __('Diese Branche ist für Ihren Betrieb nicht hinterlegt.');

            return null;
        }

        $alreadyBooked = FeaturedPlacement::query()
            ->active()
            ->forCityAndCategory((int) $company->city_id, $categoryId)
            ->where('company_id', $company->getKey())
            ->exists();

        if ($alreadyBooked) {
            $this->error = __('Diese Top-Platzierung ist bereits gebucht.');

            return null;
        }

        if ($placements->availableSlots((int) $company->city_id, $categoryId) === 0) {
            $this->error = __('Alle Top-Plätze in Ihrer Stadt und Branche sind vergeben.');

            return null;
        }

        $plans->rememberCheckoutCompany($this->tenant(), $company, (int) $company->city_id, $categoryId);

        return $this->redirectRoute('tenant.checkout.subscription', ['planSlug' => $slug]);
    }

    public function cancel(int $placementId, SubscriptionService $subscriptions, PaymentService $payments): void
    {
        $this->reset('message', 'error');

        $placement = FeaturedPlacement::query()
            ->active()
            ->where('company_id', $this->companyId)
            ->find($placementId);

        $subscription = $placement !== null ? $this->addonSubscription($placement) : null;

        if ($subscription === null || ! $subscriptions->canCancelSubscription($subscription) || $subscription->paymentProvider === null) {
            $this->error = __('Die Top-Platzierung kann derzeit nicht gekündigt werden.');

            return;
        }

        try {
            $cancelled = $subscriptions->cancelSubscription(
                $subscription,
                $payments->getPaymentProviderBySlug($subscription->paymentProvider->slug),
                'other',
            );
        } catch (Throwable $e) {
            report($e);
            $cancelled = false;
        }

        $this->message = $cancelled
            ? __('Ihre Top-Platzierung endet zum :datum. Bis dahin bleibt sie aktiv.', ['datum' => $subscription->ends_at?->format('d.m.Y')])
            : null;
        $this->error = $cancelled ? null : __('Die Kündigung ist fehlgeschlagen. Bitte versuchen Sie es später erneut.');
    }

    public function render(FeaturedPlacementService $placements): View
    {
        $company = $this->company()->load(['city', 'categories']);
        $pricing = $this->tenant()->premiumPricing();

        $active = FeaturedPlacement::query()
            ->active()
            ->where('company_id', $company->getKey())
            ->with(['city', 'category'])
            ->orderBy('city_id')
            ->orderBy('category_id')
            ->get()
            ->map(fn (FeaturedPlacement $placement): array => [
                'placement' => $placement,
                'subscription' => $this->addonSubscription($placement),
            ]);

        $options = $company->city_id === null ? collect() : $company->categories
            ->reject(fn ($category): bool => $active->contains(fn (array $row): bool => $row['placement']->city_id === (int) $company->city_id
                && $row['placement']->category_id === (int) $category->getKey()))
            ->map(fn ($category): array => [
                'category' => $category,
                'free' => $placements->availableSlots((int) $company->city_id, (int) $category->getKey()),
            ])
            ->values();

        return view('livewire.portal.company.featured-placement-booking', [
            'company' => $company,
            'active' => $active,
            'options' => $options,
            'entitled' => $this->can(PremiumFeature::FeaturedPlacement),
            'available' => $pricing->isFeaturedSaleEnabled(),
            'priceCents' => $pricing->grossCents(self::PRICE_KEY),
            'maxSlots' => $placements->maxSlots(),
            'currency' => (string) config('premium.currency', 'EUR'),
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    /**
     * Add-on-Subscription der Platzierung, nur wenn sie diesem Betrieb zugeordnet ist.
     */
    private function addonSubscription(FeaturedPlacement $placement): ?Subscription
    {
        if ($placement->subscription_ref === null) {
            return null;
        }

        $bound = CompanySubscription::query()
            ->where('subscription_id', (int) $placement->subscription_ref)
            ->where('tenant_id', $this->tenant()->getKey())
            ->where('company_id', $this->companyId)
            ->exists();

        return $bound ? Subscription::query()->with('paymentProvider')->find((int) $placement->subscription_ref) : null;
    }

    private function company(): Company
    {
        return Company::ownedBy((int) Auth::id())->whereKey($this->companyId)->firstOrFail();
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
