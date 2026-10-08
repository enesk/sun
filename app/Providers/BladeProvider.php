<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SubscriptionService;
use App\Turnstile\View\Components\Turnstile;
use App\Turnstile\View\Components\TurnstileScripts;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Liegt im Modul app/Turnstile (docs/turnstile.md §2) und nicht unter
        // App\View\Components, darum die Zuordnung von Hand: <x-turnstile />.
        Blade::component(Turnstile::class, 'turnstile');
        Blade::component(TurnstileScripts::class, 'turnstile-scripts');

        Blade::if('subscribed', function (?string $productSlug = null, ?Tenant $tenant = null) {
            /** @var User $user */
            $user = auth()->user();

            /** @var SubscriptionService $subscriptionService */
            $subscriptionService = app(SubscriptionService::class);

            return $subscriptionService->isUserSubscribed($user, $productSlug, $tenant);
        });

        Blade::if('notsubscribed', function (?string $productSlug = null, ?Tenant $tenant = null) {
            /** @var User $user */
            $user = auth()->user();

            /** @var SubscriptionService $subscriptionService */
            $subscriptionService = app(SubscriptionService::class);

            return ! $subscriptionService->isUserSubscribed($user, $productSlug, $tenant);
        });

        Blade::if('trialing', function (?string $productSlug = null, ?Tenant $tenant = null) {
            /** @var User $user */
            $user = auth()->user();

            /** @var SubscriptionService $subscriptionService */
            $subscriptionService = app(SubscriptionService::class);

            return $subscriptionService->isUserTrialing($user, $productSlug, $tenant);
        });

        Blade::if('purchased', function (?string $productSlug = null, ?Tenant $tenant = null) {
            /** @var User $user */
            $user = auth()->user();

            /** @var OrderService $orderService */
            $orderService = app(OrderService::class);

            return $orderService->hasUserOrdered($user, $productSlug, $tenant);
        });
    }
}
