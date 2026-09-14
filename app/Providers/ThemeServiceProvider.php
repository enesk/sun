<?php

namespace App\Providers;

use App\Themes\SunV2\BlogIndexViewComposer;
use App\Themes\SunV2\CityIndexViewComposer;
use App\Themes\SunV2\CityViewComposer;
use App\Themes\SunV2\ClaimViewComposer;
use App\Themes\SunV2\FooterViewComposer;
use App\Themes\SunV2\HomeViewComposer;
use App\Themes\SunV2\LegalPageViewComposer;
use App\Themes\SunV2\LoginViewComposer;
use App\Themes\SunV2\ProfileViewComposer;
use App\Themes\SunV2\SearchViewComposer;
use App\Themes\TenantStyleInjector;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        // Theme sun-v2: Zusatzdaten fuer Startseite und Footer
        View::composer('pages.home', HomeViewComposer::class);
        View::composer('pages.companies.index', SearchViewComposer::class);
        View::composer('pages.companies.show', ProfileViewComposer::class);
        View::composer('pages.companies.suggest-edit', ClaimViewComposer::class);
        View::composer('pages.cities.index', CityIndexViewComposer::class);
        View::composer('pages.cities.show', CityViewComposer::class);
        View::composer(['pages.blog.index', 'pages.blog.category', 'pages.blog.search', 'pages.blog.tag'], BlogIndexViewComposer::class);
        View::composer(['pages.impressum', 'pages.datenschutz', 'pages.blog.editorial'], LegalPageViewComposer::class);
        View::composer('auth.login', LoginViewComposer::class);
        View::composer('partials.sun.footer', FooterViewComposer::class);
    }
}
