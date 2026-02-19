<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\Subscribed;
use App\Models\Portal\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SyncCompanyPremiumStatus implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    public function handle(Subscribed $event): void
    {
        $subscription = $event->subscription;
        $tenant = $subscription->tenant;

        if (! $tenant) {
            Log::warning('SyncCompanyPremiumStatus: Subscription has no tenant', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $userId = $subscription->user_id;

        $tenant->run(function () use ($userId, $subscription) {
            $updated = Company::where('user_id', $userId)
                ->update(['is_premium' => true]);

            Log::info('SyncCompanyPremiumStatus: Activated premium', [
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'companies_updated' => $updated,
            ]);
        });
    }
}
