<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\SubscriptionCancelled;
use App\Models\Portal\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RevokeCompanyPremiumStatus implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    public function handle(SubscriptionCancelled $event): void
    {
        $subscription = $event->subscription;
        $tenant = $subscription->tenant;

        if (! $tenant) {
            Log::warning('RevokeCompanyPremiumStatus: Subscription has no tenant', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        $userId = $subscription->user_id;

        $tenant->run(function () use ($userId, $subscription) {
            $updated = Company::where('user_id', $userId)
                ->update(['is_premium' => false]);

            Log::info('RevokeCompanyPremiumStatus: Deactivated premium', [
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'companies_updated' => $updated,
            ]);
        });
    }
}
