<?php

use App\Http\Controllers\Portal\CategoryController;
use App\Http\Controllers\Portal\CompanyController;
use App\Http\Controllers\Portal\CompanyRegistrationController;
use App\Http\Controllers\Portal\OwnerDashboardController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\StaticPageController;
use App\Http\Middleware\EnsureHasCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// SEO: Sitemap & Robots (no session/theme needed, lightweight)
Route::middleware([
    'universal',
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
])->group(function () {
    Route::get('/sitemap.xml', function () {
        $path = storage_path('app/public/sitemap.xml');
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, ['Content-Type' => 'application/xml']);
    })->name('portal.sitemap');

    Route::get('/robots.txt', function () {
        $path = storage_path('app/public/robots.txt');
        if (!file_exists($path)) {
            $scheme = app()->environment('production') ? 'https' : 'http';
            $domain = request()->getHost();
            return response("User-agent: *\nDisallow: /firmenprofil/\n\nSitemap: {$scheme}://{$domain}/sitemap.xml\n", 200)
                ->header('Content-Type', 'text/plain');
        }
        return response()->file($path, ['Content-Type' => 'text/plain']);
    })->name('portal.robots');
});

Route::middleware([
    'web',
    'universal',
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
    App\Http\Middleware\ResolveTheme::class,
])->group(function () {

    // Portal Homepage
    Route::get('/', [PortalHomeController::class, 'index'])->name('home');

    // Firmenverzeichnis
    Route::get('/firmen', [CompanyController::class, 'index'])->name('portal.companies.index');

    // Kategorien
    Route::get('/kategorien', [CategoryController::class, 'index'])->name('portal.categories.index');
    Route::get('/kategorien/{slug}', [CategoryController::class, 'show'])->name('portal.categories.show');

    // Firma eintragen (Multi-Step Wizard)
    Route::get('/eintragen', [CompanyRegistrationController::class, 'create'])->name('portal.companies.create');

    // Statische Seiten (Impressum, Datenschutz)
    Route::get('/impressum', [StaticPageController::class, 'impressum'])->name('portal.impressum');
    Route::get('/datenschutz', [StaticPageController::class, 'datenschutz'])->name('portal.datenschutz');

    // Auth Routes für Firmeninhaber (Tenant-Kontext)
    Auth::routes();

    // Firmenprofil-Dashboard (Owner Area)
    Route::middleware(['auth', EnsureHasCompany::class])
        ->prefix('/firmenprofil')
        ->name('portal.owner.')
        ->group(function () {
            Route::get('/', [OwnerDashboardController::class, 'index'])->name('dashboard');
            Route::get('/bearbeiten', [OwnerDashboardController::class, 'edit'])->name('edit');
            Route::get('/bewertungen', [OwnerDashboardController::class, 'reviews'])->name('reviews');
            Route::get('/statistiken', [OwnerDashboardController::class, 'stats'])->name('stats');
            Route::get('/einstellungen', [OwnerDashboardController::class, 'settings'])->name('settings');
            Route::get('/premium', [OwnerDashboardController::class, 'premium'])->name('premium');
        });

    // Firmen-Detailseite (Catch-All mit ID-Prefix, muss LETZTE Route sein)
    Route::get('/{companySlug}', [CompanyController::class, 'show'])
        ->where('companySlug', '\d+-.+')
        ->name('portal.companies.show');
});
