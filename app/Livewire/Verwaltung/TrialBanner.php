<?php

namespace App\Livewire\Verwaltung;

use App\Constants\SubscriptionStatus;
use App\Constants\SubscriptionType;
use App\Models\Subscription;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Trial-Banner für das Verwaltungs-Dashboard.
 *
 * Zeigt den Trial-Status an: verbleibende Tage, Fortschrittsbalken, CTA zum Upgrade.
 * Erscheint nur bei aktiver Trial-Subscription (LOCALLY_MANAGED + trial_ends_at).
 */
class TrialBanner extends Component
{
    public function render()
    {
        $tenant = tenant();

        if (! $tenant) {
            return view('livewire.verwaltung.trial-banner', ['trial' => null]);
        }

        $trialSubscription = Subscription::where('tenant_id', $tenant->id)
            ->where('type', SubscriptionType::LOCALLY_MANAGED)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->with('plan')
            ->first();

        if (! $trialSubscription) {
            return view('livewire.verwaltung.trial-banner', ['trial' => null]);
        }

        $trialEndsAt = Carbon::parse($trialSubscription->trial_ends_at);
        $daysRemaining = (int) now()->diffInDays($trialEndsAt, false);
        $totalDays = (int) Carbon::parse($trialSubscription->created_at)->diffInDays($trialEndsAt);
        $daysUsed = $totalDays - $daysRemaining;
        $progress = $totalDays > 0 ? round(($daysUsed / $totalDays) * 100) : 0;

        // Urgency-Stufen für visuelle Unterscheidung
        $urgency = match (true) {
            $daysRemaining <= 3 => 'critical',  // Rot
            $daysRemaining <= 7 => 'warning',   // Orange
            default => 'info',                   // Blau/Grün
        };

        return view('livewire.verwaltung.trial-banner', [
            'trial' => [
                'daysRemaining' => max($daysRemaining, 0),
                'totalDays' => $totalDays,
                'progress' => min($progress, 100),
                'urgency' => $urgency,
                'endsAt' => $trialEndsAt->format('d.m.Y'),
                'planName' => $trialSubscription->plan->name ?? 'Premium',
                'convertUrl' => route('checkout.convert-local-subscription', [
                    'subscriptionUuid' => $trialSubscription->uuid,
                ]),
            ],
        ]);
    }
}
