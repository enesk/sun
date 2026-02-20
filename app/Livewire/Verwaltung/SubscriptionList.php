<?php

namespace App\Livewire\Verwaltung;

use App\Constants\PlanType;
use App\Constants\SubscriptionStatus;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TransactionStatus;
use App\Mapper\SubscriptionStatusMapper;
use App\Models\Subscription;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SubscriptionService;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubscriptionList extends Component
{
    public bool $showDiscardModal = false;
    public ?string $discardingUuid = null;

    public function discardCancellation(string $uuid): void
    {
        $tenant = tenant();
        $user = Auth::user();

        $permissionService = app(TenantPermissionService::class);
        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_SUBSCRIPTIONS)) {
            session()->flash('error', 'Keine Berechtigung für diese Aktion.');
            return;
        }

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $subscriptionService->findActiveByTenantAndSubscriptionUuid($tenant, $uuid);

        if (! $subscription || ! $subscriptionService->canDiscardSubscriptionCancellation($subscription)) {
            session()->flash('error', 'Kündigung kann nicht widerrufen werden.');
            return;
        }

        $paymentProvider = $subscription->paymentProvider;
        $paymentService = app(PaymentService::class);
        $strategy = $paymentService->getPaymentProviderBySlug($paymentProvider->slug);

        $subscriptionService->discardSubscriptionCancellation($subscription, $strategy);

        session()->flash('success', 'Kündigung wurde widerrufen. Das Abonnement wird automatisch verlängert.');
        $this->showDiscardModal = false;
        $this->discardingUuid = null;
    }

    public function confirmDiscard(string $uuid): void
    {
        $this->discardingUuid = $uuid;
        $this->showDiscardModal = true;
    }

    public function cancelDiscard(): void
    {
        $this->showDiscardModal = false;
        $this->discardingUuid = null;
    }

    public function render()
    {
        $tenant = tenant();
        $subscriptionService = app(SubscriptionService::class);
        $statusMapper = app(SubscriptionStatusMapper::class);

        $subscriptions = Subscription::where('tenant_id', $tenant->id)
            ->with(['plan.product', 'currency', 'interval', 'paymentProvider', 'discounts'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function (Subscription $sub) use ($subscriptionService, $statusMapper) {
                // Format price
                $interval = $sub->interval->name;
                if ($sub->interval_count > 1) {
                    $interval = $sub->interval_count . ' ' . __(str()->of($sub->interval->name)->plural()->toString());
                }
                if ($sub->plan->type === PlanType::SEAT_BASED->value) {
                    $interval .= ' / ' . __('seat');
                }
                $sub->formatted_price = money($sub->price, $sub->currency->code) . ' / ' . $interval;

                // Status badge
                $sub->status_label = $statusMapper->mapForDisplay($sub->status);
                $sub->status_color = $statusMapper->mapColor($sub->status);

                // Capabilities
                $sub->can_change_plan = $subscriptionService->canChangeSubscriptionPlan($sub);
                $sub->can_cancel = $subscriptionService->canCancelSubscription($sub);
                $sub->can_discard_cancellation = $subscriptionService->canDiscardSubscriptionCancellation($sub);
                $sub->requires_verification = $subscriptionService->subscriptionRequiresUserVerification($sub);
                $sub->is_incomplete = $subscriptionService->isIncompleteSubscription($sub);
                $sub->is_past_due = $sub->status === SubscriptionStatus::PAST_DUE->value;

                // Active discount
                $sub->active_discount = $sub->discounts
                    ->filter(fn ($d) => $d->valid_until === null || $d->valid_until > now())
                    ->first();

                return $sub;
            });

        return view('livewire.verwaltung.subscription-list', compact('subscriptions'));
    }
}
