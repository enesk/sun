<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\InvoicePaymentFailed;
use App\Models\CompanySubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendInvoicePaymentFailedNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoicePaymentFailed $event): void
    {
        // Betriebs-Abos bekommen die Premium-Mail (SyncCompanyPlanFromSubscription, #5)
        if (CompanySubscription::query()->where('subscription_id', $event->subscription->id)->exists()) {
            return;
        }

        Mail::to($event->subscription->user->email)
            ->send(new \App\Mail\Subscription\InvoicePaymentFailed($event->subscription));
    }
}
