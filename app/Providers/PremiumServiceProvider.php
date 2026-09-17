<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Premium\CompanyEntitlementService;
use App\View\Directives\PremiumFeatureDirective;
use Illuminate\Support\ServiceProvider;

/**
 * Registrierung des Premium-Moduls (#4 ff.).
 *
 * Listener (App\Listeners\Premium) kommen ueber die Event-Discovery.
 */
class PremiumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: Memoization je Request bzw. Queue-Job
        $this->app->scoped(CompanyEntitlementService::class);
    }

    public function boot(): void
    {
        PremiumFeatureDirective::register();
    }
}
