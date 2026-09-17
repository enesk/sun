<?php

declare(strict_types=1);

namespace App\Listeners\Premium;

use App\Events\Subscription\InvoicePaymentFailed;
use App\Events\Subscription\Subscribed;
use App\Events\Subscription\SubscriptionCancelled;
use App\Events\Subscription\SubscriptionRenewed;
use App\Mail\Premium\CompanyPaymentFailed;
use App\Services\Premium\CompanyPlanService;
use App\Services\Premium\FeaturedPlacementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Spiegelt SaasyKit-Subscription-Events auf die Plan-Felder des Betriebs (#5).
 *
 * Die Events kommen aus dem Stripe-Webhook im Central-Kontext;
 * CompanyPlanService wechselt fuer die Aenderung in den Tenant der Zuordnung.
 * Subscriptions ohne Eintrag in company_subscriptions werden ignoriert.
 * Add-on-Subscriptions (Top-Platzierung, #6) gehen an FeaturedPlacementService.
 */
class SyncCompanyPlanFromSubscription implements ShouldQueue
{
    public function __construct(
        private readonly CompanyPlanService $plans,
        private readonly FeaturedPlacementService $placements,
    ) {}

    public function handleSubscribed(Subscribed $event): void
    {
        if ($this->placements->isAddonSubscription($event->subscription)) {
            $this->placements->syncFromSubscription($event->subscription);

            return;
        }

        $this->plans->syncFromSubscription($event->subscription);
    }

    public function handleRenewed(SubscriptionRenewed $event): void
    {
        if ($this->placements->isAddonSubscription($event->subscription)) {
            $this->placements->syncFromSubscription($event->subscription);

            return;
        }

        $this->plans->syncFromSubscription($event->subscription);
    }

    public function handleCancelled(SubscriptionCancelled $event): void
    {
        if ($this->placements->isAddonSubscription($event->subscription)) {
            $this->placements->syncFromSubscription($event->subscription);

            return;
        }

        $this->plans->handleCancelled($event->subscription);
    }

    public function handlePaymentFailed(InvoicePaymentFailed $event): void
    {
        $subscription = $event->subscription;

        if (! $this->plans->handlePaymentFailed($subscription)) {
            return;
        }

        $email = $subscription->user?->email;

        if ($email !== null) {
            Mail::to($email)->send(new CompanyPaymentFailed($subscription));
        }
    }
}
