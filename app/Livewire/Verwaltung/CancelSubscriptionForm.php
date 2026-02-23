<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SubscriptionService;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CancelSubscriptionForm extends Component
{
    public string $subscriptionUuid;
    public string $reason = '';
    public string $additionalInfo = '';

    protected array $rules = [
        'reason' => 'required|in:too_expensive,missing_features,found_another_software,other',
        'additionalInfo' => 'nullable|string|max:1000',
    ];

    protected array $messages = [
        'reason.required' => 'Bitte wähle einen Grund aus.',
        'reason.in' => 'Ungültiger Grund.',
    ];

    public function mount(string $subscriptionUuid): void
    {
        $this->subscriptionUuid = $subscriptionUuid;
    }

    public function cancel(): void
    {
        $this->validate();

        $tenant = tenant();
        $user = Auth::user();

        $permissionService = app(TenantPermissionService::class);
        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_UPDATE_SUBSCRIPTIONS)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung für diese Aktion.');
            return;
        }

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $subscriptionService->findActiveByTenantAndSubscriptionUuid($tenant, $this->subscriptionUuid);

        if (! $subscription) {
            $this->dispatch('toast', type: 'error', message: 'Abonnement nicht gefunden.');
            return;
        }

        $paymentProvider = $subscription->paymentProvider;
        $paymentService = app(PaymentService::class);
        $strategy = $paymentService->getPaymentProviderBySlug($paymentProvider->slug);

        $subscriptionService->cancelSubscription(
            $subscription,
            $strategy,
            $this->reason,
            $this->additionalInfo ?: null,
        );

        session()->flash('success', 'Abonnement wird zum Ende der Abrechnungsperiode gekündigt.');

        $this->redirect(route('verwaltung.subscriptions.index'));
    }

    public function render()
    {
        $reasons = [
            'too_expensive' => 'Zu teuer',
            'missing_features' => 'Fehlende Funktionen',
            'found_another_software' => 'Bessere Alternative gefunden',
            'other' => 'Sonstiges',
        ];

        return view('livewire.verwaltung.cancel-subscription-form', compact('reasons'));
    }
}
