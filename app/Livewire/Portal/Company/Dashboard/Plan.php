<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PlanTier;
use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\PaymentProvider;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PaymentProviders\PaymentService;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyPlanService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * "Mein Plan" im Betriebsbereich (#17), Seite /firmenprofil/mein-plan.
 *
 * Zeigt Stufe, Laufzeit, naechste Abbuchung und gebuchte Top-Platzierungen.
 * Planwechsel laufen ueber PlanCheckout auf /firmenprofil/premium,
 * Rechnungen und Zahlungsdaten ueber die SaasyKit-Abo-Verwaltung; gekuendigt
 * wird hier direkt zum Periodenende.
 */
class Plan extends Component
{
    use HasEntitlements;

    #[Locked]
    public int $companyId;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Company $company): void
    {
        $this->companyId = (int) $company->getKey();
    }

    public function cancel(CompanyPlanService $plans, SubscriptionService $subscriptions, PaymentService $payments): void
    {
        $this->reset('message', 'error');

        $current = $plans->currentSubscription($this->tenant(), $this->company());
        $provider = $current?->paymentProvider;

        if ($current === null || ! $subscriptions->canCancelSubscription($current) || ! $provider instanceof PaymentProvider) {
            $this->error = __('premium.plan.cancel_failed');

            return;
        }

        try {
            $cancelled = $subscriptions->cancelSubscription(
                $current,
                $payments->getPaymentProviderBySlug($provider->slug),
                'other',
            );
        } catch (Throwable $e) {
            report($e);
            $cancelled = false;
        }

        $this->message = $cancelled
            ? __('premium.plan.cancel_done', ['datum' => $current->ends_at?->format('d.m.Y')])
            : null;
        $this->error = $cancelled ? null : __('premium.plan.cancel_failed');
    }

    public function render(CompanyPlanService $plans, CompanyEntitlementService $entitlements, SubscriptionService $subscriptions): View
    {
        $company = $this->company();
        $tier = $entitlements->effectiveTier($company);
        $subscription = $plans->currentSubscription($this->tenant(), $company);
        $subscription?->loadMissing(['currency', 'paymentProvider']);

        $placements = FeaturedPlacement::query()
            ->active()
            ->where('company_id', $company->getKey())
            ->with(['city', 'category'])
            ->orderBy('starts_at')
            ->get();

        return view('livewire.portal.company.dashboard.plan', [
            'company' => $company,
            'tier' => $tier,
            'subscription' => $subscription,
            'canCancel' => $subscription !== null && $subscriptions->canCancelSubscription($subscription),
            'nextCharge' => $this->nextCharge($subscription),
            'features' => $this->features($tier),
            'placements' => $placements,
            'featuredEntitled' => $this->can(PremiumFeature::FeaturedPlacement),
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    /**
     * @return array{date: \Carbon\Carbon, amount: int, currency: string}|null
     */
    private function nextCharge(?Subscription $subscription): ?array
    {
        if ($subscription === null || $subscription->is_canceled_at_end_of_cycle || $subscription->ends_at === null) {
            return null;
        }

        return [
            'date' => $subscription->ends_at,
            'amount' => (int) $subscription->price,
            'currency' => (string) ($subscription->currency->code ?? config('premium.currency', 'EUR')),
        ];
    }

    /**
     * Alle Features mit Freischaltung der aktuellen Stufe und der niedrigsten
     * Stufe, die sie enthaelt.
     *
     * @return list<array{feature: PremiumFeature, unlocked: bool, required: ?PlanTier}>
     */
    private function features(PlanTier $tier): array
    {
        return array_map(fn (PremiumFeature $feature): array => [
            'feature' => $feature,
            'unlocked' => $tier->hasFeature($feature),
            'required' => collect(PlanTier::cases())->first(fn (PlanTier $candidate): bool => $candidate->hasFeature($feature)),
        ], PremiumFeature::cases());
    }

    private function company(): Company
    {
        return Company::ownedBy((int) Auth::id())->whereKey($this->companyId)->with('city')->firstOrFail();
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
