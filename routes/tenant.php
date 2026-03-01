<?php

use App\Http\Controllers\Portal\CategoryController;
use App\Http\Controllers\Portal\CompanyController;
use App\Http\Controllers\Portal\CompanyRegistrationController;
use App\Http\Controllers\Portal\OwnerDashboardController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\StaticPageController;
use App\Http\Controllers\Verwaltung\VerwaltungCategoryController;
use App\Http\Controllers\Verwaltung\VerwaltungCityController;
use App\Http\Controllers\Verwaltung\VerwaltungCompanyController;
use App\Http\Controllers\Verwaltung\VerwaltungController;
use App\Http\Controllers\Verwaltung\VerwaltungOrderController;
use App\Http\Controllers\Verwaltung\VerwaltungEditSuggestionController;
use App\Http\Controllers\Verwaltung\VerwaltungReviewController;
use App\Http\Controllers\Verwaltung\VerwaltungSubscriptionController;
use App\Http\Controllers\Verwaltung\VerwaltungTransactionController;
use App\Http\Controllers\Verwaltung\VerwaltungUserController;
use App\Http\Controllers\Verwaltung\VerwaltungTeamController;
use App\Http\Controllers\Verwaltung\VerwaltungRoleController;
use App\Http\Controllers\Verwaltung\VerwaltungInvitationController;
use App\Http\Controllers\Verwaltung\VerwaltungSettingsController;
use App\Http\Controllers\Verwaltung\VerwaltungProfileController;
use App\Http\Controllers\Verwaltung\VerwaltungClaimController;
use App\Http\Controllers\Verwaltung\VerwaltungReferralController;
use App\Http\Middleware\EnsureHasCompany;
use App\Http\Middleware\EnsureTenantDashboardAccess;
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
            $domain = request()->getHost();
            $scheme = 'https';
            return response("User-agent: *\nAllow: /\nDisallow: /firmenprofil\nDisallow: /verwaltung\nDisallow: /login\nDisallow: /register\n\nSitemap: {$scheme}://{$domain}/sitemap.xml\n", 200)
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
            Route::post('/bewertungen/{review}/antwort', [OwnerDashboardController::class, 'respondToReview'])->name('reviews.respond');
            Route::get('/statistiken', [OwnerDashboardController::class, 'stats'])->name('stats');
            Route::get('/einstellungen', [OwnerDashboardController::class, 'settings'])->name('settings');
            Route::get('/premium', [OwnerDashboardController::class, 'premium'])->name('premium');
        });

    // ========================================================================
    // Verwaltung (Custom Tenant Dashboard — replaces Filament Dashboard)
    // ========================================================================
    Route::middleware(['auth', EnsureTenantDashboardAccess::class])
        ->prefix('/verwaltung')
        ->name('verwaltung.')
        ->group(function () {
            // Overview
            Route::get('/', [VerwaltungController::class, 'index'])->name('index');

            // --- Portal Management ---
            // Companies (#106)
            Route::get('/firmen', [VerwaltungCompanyController::class, 'index'])->name('companies.index');
            Route::get('/firmen/erstellen', [VerwaltungCompanyController::class, 'create'])->name('companies.create');
            Route::get('/firmen/{id}/bearbeiten', [VerwaltungCompanyController::class, 'edit'])->name('companies.edit');
            Route::delete('/firmen/{id}', [VerwaltungCompanyController::class, 'destroy'])->name('companies.destroy');

            // Reviews (#107)
            Route::get('/bewertungen', [VerwaltungReviewController::class, 'index'])->name('reviews.index');

            // Edit Suggestions (#150)
            Route::get('/aenderungsvorschlaege', [VerwaltungEditSuggestionController::class, 'index'])->name('edit-suggestions.index');

            // Claim-Anträge (#169)
            Route::get('/claim-antraege', [VerwaltungClaimController::class, 'index'])->name('claims.index');

            // Categories (#108)
            Route::get('/kategorien', [VerwaltungCategoryController::class, 'index'])->name('categories.index');
            Route::get('/kategorien/erstellen', [VerwaltungCategoryController::class, 'create'])->name('categories.create');
            Route::get('/kategorien/{id}/bearbeiten', [VerwaltungCategoryController::class, 'edit'])->name('categories.edit');
            Route::delete('/kategorien/{id}', [VerwaltungCategoryController::class, 'destroy'])->name('categories.destroy');

            // Cities (#108)
            Route::get('/staedte', [VerwaltungCityController::class, 'index'])->name('cities.index');
            Route::get('/staedte/erstellen', [VerwaltungCityController::class, 'create'])->name('cities.create');
            Route::get('/staedte/{id}/bearbeiten', [VerwaltungCityController::class, 'edit'])->name('cities.edit');
            Route::delete('/staedte/{id}', [VerwaltungCityController::class, 'destroy'])->name('cities.destroy');

            // --- Finanzen (#109) ---
            // Subscriptions
            Route::get('/abonnements', [VerwaltungSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('/abonnements/{uuid}', [VerwaltungSubscriptionController::class, 'show'])->name('subscriptions.show');
            Route::get('/abonnements/{uuid}/kuendigen', [VerwaltungSubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

            // Transactions
            Route::get('/zahlungen', [VerwaltungTransactionController::class, 'index'])->name('transactions.index');

            // Orders
            Route::get('/bestellungen', [VerwaltungOrderController::class, 'index'])->name('orders.index');
            Route::get('/bestellungen/{uuid}', [VerwaltungOrderController::class, 'show'])->name('orders.show');

            // --- Team (#110) ---
            // Users
            Route::get('/benutzer', [VerwaltungUserController::class, 'index'])->name('users.index');

            // Teams
            Route::get('/teams', [VerwaltungTeamController::class, 'index'])->name('teams.index');
            Route::get('/teams/erstellen', [VerwaltungTeamController::class, 'create'])->name('teams.create');
            Route::get('/teams/{uuid}/bearbeiten', [VerwaltungTeamController::class, 'edit'])->name('teams.edit');

            // Roles
            Route::get('/rollen', [VerwaltungRoleController::class, 'index'])->name('roles.index');
            Route::get('/rollen/erstellen', [VerwaltungRoleController::class, 'create'])->name('roles.create');
            Route::get('/rollen/{id}/bearbeiten', [VerwaltungRoleController::class, 'edit'])->name('roles.edit');

            // Invitations
            Route::get('/einladungen', [VerwaltungInvitationController::class, 'index'])->name('invitations.index');
            Route::get('/einladungen/erstellen', [VerwaltungInvitationController::class, 'create'])->name('invitations.create');

            // --- Einstellungen ---
            Route::get('/einstellungen', [VerwaltungSettingsController::class, 'general'])->name('settings.general');
            Route::get('/einstellungen/theme', [VerwaltungSettingsController::class, 'theme'])->name('settings.theme');
            Route::get('/einstellungen/rechtliches', [VerwaltungSettingsController::class, 'legal'])->name('settings.legal');

            // Profile
            Route::get('/profil', [VerwaltungProfileController::class, 'index'])->name('profile');

            // Referrals (#112)
            Route::get('/empfehlungen', [VerwaltungReferralController::class, 'index'])->name('referrals.index');
        });

    // PROF-1: Änderung vorschlagen — Landingpage
    Route::get('/firma/{slug}/aenderung-vorschlagen', [CompanyController::class, 'suggestEdit'])
        ->name('companies.suggest-edit');

    // Claim-Verifizierung: Dokument-Upload nach Claim-Request
    Route::get('/firma/{slug}/verifizierung', [CompanyController::class, 'claimVerification'])
        ->middleware('auth')
        ->name('companies.claim-verification');

    // ==============================
    // Checkout-Routes (gespiegelt von web.php, mit tenant. Prefix)
    // Subscriptions nutzen CentralConnection → schreiben in Central DB
    // Routes müssen auf Tenant-Domain verfügbar sein damit die Session funktioniert
    // WICHTIG: Route-Namen mit tenant. Prefix um Konflikte mit web.php zu vermeiden
    // ==============================
    Route::get('/checkout/plan/{planSlug}', [
        \App\Http\Controllers\SubscriptionCheckoutController::class,
        'subscriptionCheckout',
    ])->name('tenant.checkout.subscription');

    Route::get('/checkout/convert-subscription/{subscriptionUuid}', [
        \App\Http\Controllers\SubscriptionCheckoutController::class,
        'convertLocalSubscriptionCheckout',
    ])->name('tenant.checkout.convert-local-subscription');

    Route::get('/already-subscribed', function () {
        return view('checkout.already-subscribed');
    })->name('tenant.checkout.subscription.already-subscribed');

    Route::get('/checkout/subscription/success', [
        \App\Http\Controllers\SubscriptionCheckoutController::class,
        'subscriptionCheckoutSuccess',
    ])->name('tenant.checkout.subscription.success')->middleware('auth');

    Route::get('/checkout/convert-subscription-success', [
        \App\Http\Controllers\SubscriptionCheckoutController::class,
        'convertLocalSubscriptionCheckoutSuccess',
    ])->name('tenant.checkout.convert-local-subscription.success')->middleware('auth');

    Route::get('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
        \App\Http\Controllers\SubscriptionController::class,
        'changePlan',
    ])->name('tenant.subscription.change-plan')->middleware('auth');

    Route::post('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
        \App\Http\Controllers\SubscriptionController::class,
        'changePlan',
    ])->name('tenant.subscription.change-plan.post')->middleware('auth');

    Route::get('/subscription/change-plan-thank-you', [
        \App\Http\Controllers\SubscriptionController::class,
        'success',
    ])->name('tenant.subscription.change-plan.thank-you')->middleware('auth');

    // Firmen-Detailseite: /{citySlug}/{id}-{slug} (konfigurierbar pro Tenant)
    Route::get('/{citySlug}/{companySlug}', [CompanyController::class, 'showWithCity'])
        ->where(['citySlug' => '[a-z0-9\-]+', 'companySlug' => '\d+-.+'])
        ->name('portal.companies.show.city');

    // Firmen-Detailseite: /{id}-{slug} (Default, muss LETZTE Route sein)
    Route::get('/{companySlug}', [CompanyController::class, 'show'])
        ->where('companySlug', '\d+-.+')
        ->name('portal.companies.show');
});
