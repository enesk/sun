<?php

namespace App\Livewire\Verwaltung;

use App\Constants\SubscriptionStatus;
use App\Constants\SubscriptionType;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use App\Models\Subscription;
use Livewire\Component;

/**
 * Downgrade-Modal nach Trial-Ende.
 *
 * Zeigt dem User personalisierte Daten (Profilaufrufe, Bewertungen, etc.)
 * und motiviert zum Premium-Upgrade.
 * Erscheint einmalig nach Trial-Ende (Session-gesteuert).
 */
class DowngradeModal extends Component
{
    public bool $showModal = false;

    public function mount(): void
    {
        $tenant = tenant();
        if (! $tenant) {
            return;
        }

        // Prüfe: Gibt es eine abgelaufene Trial-Subscription?
        $expiredTrial = Subscription::where('tenant_id', $tenant->id)
            ->where('type', SubscriptionType::LOCALLY_MANAGED)
            ->whereIn('status', [
                SubscriptionStatus::INACTIVE->value,
                SubscriptionStatus::CANCELED->value,
            ])
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->exists();

        if (! $expiredTrial) {
            return;
        }

        // Bereits aktiv zahlend? Dann kein Modal.
        $hasActiveSubscription = Subscription::where('tenant_id', $tenant->id)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->whereNull('trial_ends_at')
            ->exists();

        if ($hasActiveSubscription) {
            return;
        }

        // Session-Flag: Modal nur einmal pro Session zeigen
        if (! session()->has('downgrade_modal_shown')) {
            $this->showModal = true;
            session()->put('downgrade_modal_shown', true);
        }
    }

    public function dismiss(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        $data = ['stats' => null];

        if ($this->showModal) {
            $data['stats'] = $this->getPersonalizedStats();
        }

        return view('livewire.verwaltung.downgrade-modal', $data);
    }

    private function getPersonalizedStats(): array
    {
        $user = auth()->user();
        $company = Company::where('user_id', $user?->id)->first();

        $totalReviews = 0;
        $avgRating = 0;
        $answeredReviews = 0;

        if ($company) {
            $totalReviews = $company->reviews()->count();
            $avgRating = $company->rating ?? 0;
            $answeredReviews = $company->reviews()
                ->whereNotNull('owner_response')
                ->count();
        }

        $galleryCount = 0;
        if ($company) {
            $galleryCount = $company->getMedia('gallery')->count();
        }

        // Letzte Subscription für Trial-Dauer
        $subscription = Subscription::where('tenant_id', tenant()->id)
            ->whereNotNull('trial_ends_at')
            ->latest('trial_ends_at')
            ->first();

        $convertUrl = null;
        if ($subscription) {
            $convertUrl = route('checkout.convert-local-subscription', [
                'subscriptionUuid' => $subscription->uuid,
            ]);
        }

        return [
            'companyName' => $company->name ?? 'Ihr Unternehmen',
            'totalReviews' => $totalReviews,
            'avgRating' => round($avgRating, 1),
            'answeredReviews' => $answeredReviews,
            'galleryCount' => $galleryCount,
            'convertUrl' => $convertUrl,
        ];
    }
}
