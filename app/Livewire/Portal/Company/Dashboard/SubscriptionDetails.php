<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Models\PaymentProvider;
use App\Models\Portal\Company;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PaymentProviders\PaymentService;
use App\Services\Premium\CompanyPlanService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Abo-Details und Kuendigung im Betriebsbereich (/firmenprofil/abo).
 *
 * Ersetzt fuer Inhaber die beiden Seiten der Verwaltung
 * (verwaltung.subscriptions.show/cancel): die verlangen die Berechtigung
 * PERMISSION_UPDATE_SUBSCRIPTIONS, die ein Betriebsinhaber nicht hat.
 * Gerechnet wird ausschliesslich mit dem Abo des eigenen Betriebs
 * (CompanyPlanService::currentSubscription), eine Uuid aus der Adresse gibt
 * es hier bewusst nicht.
 */
class SubscriptionDetails extends Component
{
    #[Locked]
    public int $companyId;

    /** Gruende wie im Kuendigungsformular der Verwaltung */
    public string $reason = '';

    public string $note = '';

    public bool $confirming = false;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Company $company): void
    {
        $this->companyId = (int) $company->getKey();
    }

    public function startCancel(): void
    {
        $this->reset('message', 'error');
        $this->confirming = true;
    }

    public function abortCancel(): void
    {
        $this->reset('reason', 'note', 'confirming');
    }

    public function cancel(CompanyPlanService $plans, SubscriptionService $subscriptions, PaymentService $payments): void
    {
        $this->reset('message', 'error');

        $this->validate([
            'reason' => 'required|in:too_expensive,missing_features,found_another_software,other',
            'note' => 'nullable|string|max:1000',
        ], attributes: ['reason' => __('premium.subscription.reason')]);

        $subscription = $this->subscription($plans);
        $provider = $subscription?->paymentProvider;

        if ($subscription === null || ! $subscriptions->canCancelSubscription($subscription) || ! $provider instanceof PaymentProvider) {
            $this->error = __('premium.plan.cancel_failed');

            return;
        }

        try {
            $cancelled = $subscriptions->cancelSubscription(
                $subscription,
                $payments->getPaymentProviderBySlug($provider->slug),
                $this->reason,
                $this->note !== '' ? $this->note : null,
            );
        } catch (Throwable $e) {
            report($e);
            $cancelled = false;
        }

        if (! $cancelled) {
            $this->error = __('premium.plan.cancel_failed');

            return;
        }

        $this->reset('reason', 'note', 'confirming');
        $this->message = __('premium.plan.cancel_done', ['datum' => $subscription->fresh()?->ends_at?->format('d.m.Y') ?? '']);
    }

    public function discard(CompanyPlanService $plans, SubscriptionService $subscriptions, PaymentService $payments): void
    {
        $this->reset('message', 'error');

        $subscription = $this->subscription($plans);
        $provider = $subscription?->paymentProvider;

        if ($subscription === null || ! $subscriptions->canDiscardSubscriptionCancellation($subscription) || ! $provider instanceof PaymentProvider) {
            $this->error = __('premium.subscription.discard_failed');

            return;
        }

        try {
            $discarded = $subscriptions->discardSubscriptionCancellation(
                $subscription,
                $payments->getPaymentProviderBySlug($provider->slug),
            );
        } catch (Throwable $e) {
            report($e);
            $discarded = false;
        }

        $this->message = $discarded ? __('premium.subscription.discard_done') : null;
        $this->error = $discarded ? null : __('premium.subscription.discard_failed');
    }

    public function render(CompanyPlanService $plans, SubscriptionService $subscriptions): View
    {
        $subscription = $this->subscription($plans);
        $subscription?->loadMissing(['plan', 'currency', 'interval', 'paymentProvider']);

        return view('livewire.portal.company.dashboard.subscription-details', [
            'company' => $this->company(),
            'subscription' => $subscription,
            'canCancel' => $subscription !== null && $subscriptions->canCancelSubscription($subscription),
            'canDiscard' => $subscription !== null && $subscriptions->canDiscardSubscriptionCancellation($subscription),
            'termEndsAt' => $subscription !== null ? $plans->termEndsAt($subscription) : null,
            'reasons' => [
                'too_expensive' => __('premium.subscription.reasons.too_expensive'),
                'missing_features' => __('premium.subscription.reasons.missing_features'),
                'found_another_software' => __('premium.subscription.reasons.found_another_software'),
                'other' => __('premium.subscription.reasons.other'),
            ],
        ]);
    }

    private function subscription(CompanyPlanService $plans): ?Subscription
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $plans->currentSubscription($tenant, $this->company()) : null;
    }

    private function company(): Company
    {
        return Company::ownedBy((int) Auth::id())->findOrFail($this->companyId);
    }
}
