<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company;

use App\Enums\PlanTier;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\PaymentProviders\PaymentService;
use App\Services\Premium\CompanyPlanService;
use App\Services\SubscriptionService;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Plan-Buchung im Betriebsbereich (#5).
 *
 * Neubuchung: Betrieb in der Session merken und in den SaasyKit-Checkout
 * springen; CheckoutService legt dann die Zuordnung in company_subscriptions
 * an. Bestehendes Abo: Planwechsel mit Proration bzw. Kuendigung zum
 * Periodenende ueber SubscriptionService.
 */
class PlanCheckout extends Component
{
    #[Locked]
    public int $companyId;

    public string $billing = 'monthly';

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Company $company): void
    {
        $this->companyId = (int) $company->getKey();
    }

    public function checkout(string $tier, CompanyPlanService $plans, SubscriptionService $subscriptions, PaymentService $payments)
    {
        $this->reset('message', 'error');

        $key = $this->priceKey($tier);
        $slug = $key !== null ? TenantPremiumPricing::planSlug($key) : null;

        if ($slug === null || ! $this->pricing()->isAvailable($key)) {
            $this->error = __('Dieses Paket ist derzeit nicht buchbar.');

            return null;
        }

        $company = $this->company();
        $current = $plans->currentSubscription($this->tenant(), $company);

        if ($current === null) {
            $plans->rememberCheckoutCompany($this->tenant(), $company);

            return $this->redirectRoute('tenant.checkout.subscription', ['planSlug' => $slug]);
        }

        if ($current->plan?->slug === $slug) {
            $this->error = __('Dieses Paket ist bereits gebucht.');

            return null;
        }

        if (! $subscriptions->canChangeSubscriptionPlan($current) || $current->paymentProvider === null) {
            $this->error = __('Ein Paketwechsel ist gerade nicht möglich. Bitte prüfen Sie Ihre Zahlungsdaten.');

            return null;
        }

        try {
            $changed = $subscriptions->changePlan(
                $current,
                $payments->getPaymentProviderBySlug($current->paymentProvider->slug),
                $slug,
                (bool) config('app.payment.proration_enabled', true),
            );
        } catch (Throwable $e) {
            report($e);
            $changed = false;
        }

        if (! $changed) {
            $this->error = __('Der Paketwechsel ist fehlgeschlagen. Bitte versuchen Sie es später erneut.');

            return null;
        }

        $this->message = __('Ihr Paket wurde umgestellt. Die Differenz wird anteilig verrechnet.');

        return null;
    }

    public function cancel(CompanyPlanService $plans, SubscriptionService $subscriptions, PaymentService $payments): void
    {
        $this->reset('message', 'error');

        $current = $plans->currentSubscription($this->tenant(), $this->company());

        if ($current === null || ! $subscriptions->canCancelSubscription($current) || $current->paymentProvider === null) {
            $this->error = __('Das Abo kann derzeit nicht gekündigt werden.');

            return;
        }

        $cancelled = $subscriptions->cancelSubscription(
            $current,
            $payments->getPaymentProviderBySlug($current->paymentProvider->slug),
            'other',
        );

        $this->message = $cancelled
            ? __('Ihr Abo endet zum :datum. Bis dahin bleiben alle Funktionen aktiv.', ['datum' => $current->ends_at?->format('d.m.Y')])
            : null;
        $this->error = $cancelled ? null : __('Die Kündigung ist fehlgeschlagen. Bitte versuchen Sie es später erneut.');
    }

    public function render(CompanyPlanService $plans): View
    {
        $pricing = $this->pricing();
        $tiers = [];

        foreach ([PlanTier::Pro, PlanTier::Premium] as $tier) {
            $monthly = $pricing->grossCents("{$tier->value}_monthly");
            $yearly = $pricing->grossCents("{$tier->value}_yearly");

            $tiers[$tier->value] = [
                'label' => $tier->label(),
                'available' => $pricing->isAvailable($this->priceKey($tier->value) ?? ''),
                'monthly' => $monthly,
                'yearly' => $yearly,
                'free_months' => $monthly !== null && $yearly !== null ? $this->freeMonths($monthly, $yearly) : 0,
            ];
        }

        $current = $plans->currentSubscription($this->tenant(), $this->company());

        return view('livewire.portal.company.plan-checkout', [
            'tiers' => $tiers,
            'current' => $current,
            'currentSlug' => $current?->plan?->slug,
            'currency' => (string) config('premium.currency', 'EUR'),
        ]);
    }

    /**
     * Rabatt des Jahresplans in ganzen Freimonaten gegenueber 12 x Monatspreis.
     */
    private function freeMonths(int $monthlyCents, int $yearlyCents): int
    {
        return max(0, (int) round(($monthlyCents * 12 - $yearlyCents) / $monthlyCents));
    }

    private function priceKey(string $tier): ?string
    {
        $planTier = PlanTier::tryFrom($tier);

        if ($planTier === null || $planTier === PlanTier::Free || ! in_array($this->billing, ['monthly', 'yearly'], true)) {
            return null;
        }

        return "{$planTier->value}_{$this->billing}";
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

    private function pricing(): TenantPremiumPricing
    {
        return $this->tenant()->premiumPricing();
    }
}
