<?php

namespace App\Livewire\Verwaltung;

use App\Constants\DiscountConstants;
use App\Constants\PlanType;
use App\Constants\TenancyPermissionConstants;
use App\Mapper\SubscriptionStatusMapper;
use App\Models\Subscription;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SubscriptionService;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubscriptionDetail extends Component
{
    public string $subscriptionUuid;
    public bool $showDiscardModal = false;

    public function mount(string $subscriptionUuid): void
    {
        $this->subscriptionUuid = $subscriptionUuid;
    }

    public function discardCancellation(): void
    {
        $tenant = tenant();
        $user = Auth::user();

        $permissionService = app(TenantPermissionService::class);
        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_SUBSCRIPTIONS)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung für diese Aktion.');
            return;
        }

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $subscriptionService->findActiveByTenantAndSubscriptionUuid($tenant, $this->subscriptionUuid);

        if (! $subscription || ! $subscriptionService->canDiscardSubscriptionCancellation($subscription)) {
            $this->dispatch('toast', type: 'error', message: 'Kündigung kann nicht widerrufen werden.');
            return;
        }

        $paymentProvider = $subscription->paymentProvider;
        $paymentService = app(PaymentService::class);
        $strategy = $paymentService->getPaymentProviderBySlug($paymentProvider->slug);

        $subscriptionService->discardSubscriptionCancellation($subscription, $strategy);

        $this->dispatch('toast', type: 'success', message: 'Kündigung wurde widerrufen. Das Abonnement wird automatisch verlängert.');
        $this->showDiscardModal = false;
    }

    public function confirmDiscard(): void
    {
        $this->showDiscardModal = true;
    }

    public function cancelDiscard(): void
    {
        $this->showDiscardModal = false;
    }

    public function render()
    {
        $subscriptionService = app(SubscriptionService::class);
        $statusMapper = app(SubscriptionStatusMapper::class);

        $subscription = Subscription::where('uuid', $this->subscriptionUuid)
            ->with(['plan.product', 'currency', 'interval', 'paymentProvider', 'discounts', 'user'])
            ->firstOrFail();

        // Format price
        $interval = $subscription->interval->name;
        if ($subscription->interval_count > 1) {
            $interval = $subscription->interval_count . ' ' . __(str()->of($subscription->interval->name)->plural()->toString());
        }
        if ($subscription->plan->type === PlanType::SEAT_BASED->value) {
            $interval .= ' / ' . __('seat');
        }
        $subscription->formatted_price = money($subscription->price, $subscription->currency->code) . ' / ' . $interval;

        // Status
        $subscription->status_label = $statusMapper->mapForDisplay($subscription->status);
        $subscription->status_color = $statusMapper->mapColor($subscription->status);

        // Capabilities
        $subscription->can_change_plan = $subscriptionService->canChangeSubscriptionPlan($subscription);
        $subscription->can_cancel = $subscriptionService->canCancelSubscription($subscription);
        $subscription->can_discard_cancellation = $subscriptionService->canDiscardSubscriptionCancellation($subscription);
        $subscription->requires_verification = $subscriptionService->subscriptionRequiresUserVerification($subscription);
        $subscription->is_incomplete = $subscriptionService->isIncompleteSubscription($subscription);
        $subscription->is_past_due = $subscription->status === \App\Constants\SubscriptionStatus::PAST_DUE->value;

        // Active discount
        $subscription->active_discount = $subscription->discounts
            ->filter(fn ($d) => $d->valid_until === null || $d->valid_until > now())
            ->first();

        return view('livewire.verwaltung.subscription-detail', compact('subscription'));
    }
}
