<?php

namespace App\Providers;

use App\Themes\TenantStyleInjector;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class);

        $this->app->singleton(ThemeViewFinder::class, function ($app) {
            // IMPORTANT: Use the finder from the view factory, not app('view.finder')
            // which may return a NEW instance each time instead of the singleton.
            $viewFactory = $app->make('view');
            return new ThemeViewFinder($viewFactory->getFinder());
        });

        $this->app->singleton(TenantStyleInjector::class);
    }

    public function boot(): void
    {
        // Discover themes on boot so they're available everywhere
        $this->app->make(ThemeManager::class)->discover();

        // Capture original view paths before any theme modifies them
        $this->app->make(ThemeViewFinder::class)->initialize();
    }
}
