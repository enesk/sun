<?php

use App\Guide\Http\Middleware\EnsureContentPreviewAccess;
use App\Guide\Http\Middleware\HandleGuideRedirects;
use App\Guide\Seo\GuideSitemapGenerator;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\GuidePreviewController;
use App\Http\Controllers\IndexNowKeyController;
use App\Http\Controllers\Portal\AuthorController;
use App\Http\Controllers\Portal\BlogFeedController;
use App\Http\Controllers\Portal\CategoryController;
use App\Http\Controllers\Portal\CompanyController;
use App\Http\Controllers\Portal\CompanyRegistrationController;
use App\Http\Controllers\Portal\ExclusiveLeadController;
use App\Http\Controllers\Portal\LlmsTxtController;
use App\Http\Controllers\Portal\OwnerDashboardController;
use App\Http\Controllers\Portal\OwnerInquiryController;
use App\Http\Controllers\Portal\OwnerJobController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\PremiumPricingController;
use App\Http\Controllers\Portal\PublicBlogController;
use App\Http\Controllers\Portal\PublicCityController;
use App\Http\Controllers\Portal\PublicFaqController;
use App\Http\Controllers\Portal\PublicJobController;
use App\Http\Controllers\Portal\ReviewInviteController;
use App\Http\Controllers\Portal\ReviewWidgetController;
use App\Http\Controllers\Portal\StaticPageController;
use App\Http\Controllers\Portal\StatsBeaconController;
use App\Http\Controllers\Portal\TrackingController;
use App\Http\Controllers\Portal\VerificationDocumentController;
use App\Http\Controllers\Verwaltung\VerwaltungAdController;
use App\Http\Controllers\Verwaltung\VerwaltungBlogController;
use App\Http\Controllers\Verwaltung\VerwaltungCategoryController;
use App\Http\Controllers\Verwaltung\VerwaltungCityController;
use App\Http\Controllers\Verwaltung\VerwaltungClaimController;
use App\Http\Controllers\Verwaltung\VerwaltungCompanyController;
use App\Http\Controllers\Verwaltung\VerwaltungController;
use App\Http\Controllers\Verwaltung\VerwaltungEditSuggestionController;
use App\Http\Controllers\Verwaltung\VerwaltungFaqController;
use App\Http\Controllers\Verwaltung\VerwaltungInvitationController;
use App\Http\Controllers\Verwaltung\VerwaltungJobController;
use App\Http\Controllers\Verwaltung\VerwaltungOrderController;
use App\Http\Controllers\Verwaltung\VerwaltungProfileController;
use App\Http\Controllers\Verwaltung\VerwaltungReferralController;
use App\Http\Controllers\Verwaltung\VerwaltungReviewController;
use App\Http\Controllers\Verwaltung\VerwaltungRoleController;
use App\Http\Controllers\Verwaltung\VerwaltungSettingsController;
use App\Http\Controllers\Verwaltung\VerwaltungStatisticsController;
use App\Http\Controllers\Verwaltung\VerwaltungSubscriptionController;
use App\Http\Controllers\Verwaltung\VerwaltungTeamController;
use App\Http\Controllers\Verwaltung\VerwaltungTransactionController;
use App\Http\Controllers\Verwaltung\VerwaltungUserController;
use App\Http\Middleware\EnsureHasCompany;
use App\Http\Middleware\EnsureTenantDashboardAccess;
use App\Http\Middleware\TrackCompanyPageView;
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

    // Ratgeber-Sitemap: dynamisch aus dem Seiten-Cache, den der Publisher verwirft (#18)
    Route::get('/sitemap-ratgeber.xml', function (GuideSitemapGenerator $sitemap) {
        // Schema wie im Sitemap-Job, damit Index und Teil-Sitemap dieselben URLs nennen
        $xml = $sitemap->xml((app()->environment('production') ? 'https' : 'http').'://'.request()->getHost());
        if ($xml === null) {
            abort(404);
        }
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.GuideSitemapGenerator::CACHE_TTL,
        ]);
    })->name('guide.sitemap');

    Route::get('/sitemap-{name}.xml', function (string $name) {
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);
        $path = storage_path("app/public/sitemap-{$name}.xml");
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, ['Content-Type' => 'application/xml']);
    })->where('name', '[a-z0-9\-]+')->name('portal.sitemap.part');

    Route::get('/robots.txt', function (\App\Services\RobotsTxtBuilder $robots) {
        $path = storage_path('app/public/robots.txt');
        if (!file_exists($path)) {
            // Fallback bis der Sitemap-Job die Datei geschrieben hat — gleicher
            // Inhalt inkl. KI-Crawler-Regeln (#18).
            return response($robots->build('https://'.request()->getHost()), 200)
                ->header('Content-Type', 'text/plain');
        }
        return response()->file($path, ['Content-Type' => 'text/plain']);
    })->name('portal.robots');

    // KI-Sichtbarkeit: Kurzindex und Volltexte fuer LLM-Crawler (#18)
    Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('portal.llms');
    Route::get('/llms-full.txt', [LlmsTxtController::class, 'full'])->name('portal.llms-full');

    // ads.txt (dynamisch pro Tenant)
    Route::get('/ads.txt', function () {
        $content = \App\Models\Portal\AdSetting::query()->value('ads_txt_content');
        if (empty($content)) {
            abort(404);
        }
        return response($content, 200)->header('Content-Type', 'text/plain');
    })->name('portal.ads-txt');

    // RSS-Feed (#204)
    Route::get('/ratgeber/feed', [BlogFeedController::class, 'rss'])->name('portal.blog.feed');

    // Auskunftsseite des Recherche-Bots (#26). Die Adresse steht im
    // User-Agent der Quell-Connectoren und muss erreichbar sein.
    Route::get('/bot', \App\Http\Controllers\Portal\BotInfoController::class)->name('portal.bot-info');

    // IndexNow-Schluesseldatei (#21). Das Muster ist eng genug, dass es
    // robots.txt, ads.txt und llms.txt nicht anfasst: nur Hex, mindestens
    // acht Zeichen.
    Route::get('/{key}.txt', IndexNowKeyController::class)
        ->where('key', '[a-f0-9]{8,128}')
        ->name('portal.indexnow-key');
});

// Anfragen aus dem Leadsystem (#31): ohne Session und CSRF, Signatur prueft der Controller.
Route::middleware([
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
    Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    'throttle:120,1',
])->group(function () {
    Route::post('/webhooks/leads', App\Http\Controllers\Webhooks\LeadWebhookController::class)
        ->name('portal.webhooks.leads');
});

// Bewertungs-Widget (#12): ohne Session und CSRF, CORS und Rahmen setzt der Controller.
Route::middleware([
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
    Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    'throttle:240,1',
])->group(function () {
    Route::get('/widget/{companySlug}.js', [ReviewWidgetController::class, 'script'])
        ->where('companySlug', '\d+-[a-z0-9\-]+')
        ->name('portal.reviews.widget.script');
    Route::get('/widget/{companySlug}', [ReviewWidgetController::class, 'show'])
        ->where('companySlug', '\d+-[a-z0-9\-]+')
        ->name('portal.reviews.widget');
});

Route::middleware([
    'web',
    'universal',
    App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
    App\Http\Middleware\ResolveTheme::class,
])->group(function () {

    // Portal Homepage
    Route::get('/', [PortalHomeController::class, 'index'])->name('home');

    // Funnel
    Route::get('/funnel', fn () => view('pages.funnel'))->name('funnel');

    // Firmenverzeichnis
    Route::get('/firmen', [CompanyController::class, 'index'])->name('portal.companies.index');

    // Kategorien
    Route::get('/kategorien', [CategoryController::class, 'index'])->name('portal.categories.index');
    Route::get('/kategorien/{slug}', [CategoryController::class, 'show'])->name('portal.categories.show');

    // Firma eintragen (Multi-Step Wizard)
    Route::get('/eintragen', [CompanyRegistrationController::class, 'create'])->name('portal.companies.create');

    // Jobbörse — öffentlich (#180)
    Route::get('/jobs', [PublicJobController::class, 'index'])->name('portal.jobs.index');
    Route::get('/jobs/{slug}', [PublicJobController::class, 'show'])->name('portal.jobs.show');
    Route::post('/jobs/{slug}/bewerben', [PublicJobController::class, 'apply'])->name('portal.jobs.apply');

    // Preisseite fuer Betriebe (#17)
    Route::get('/premium', PremiumPricingController::class)->name('portal.premium.pricing');

    // FAQ (#198)
    Route::get('/faq', [PublicFaqController::class, 'index'])->name('portal.faqs.index');

    // Ratgeber — öffentlich (#203, themengetrieben seit #17). Übersicht,
    // Kategorie und Artikel laufen über den GuideController; Portale ohne
    // Ratgeber-Kategorien bekommen dort weiter die bisherigen Blog-Seiten.
    Route::get('/ratgeber', [GuideController::class, 'index'])->name('guide.index');
    Route::get('/ratgeber/suche', [PublicBlogController::class, 'search'])->name('portal.blog.search');
    Route::get('/ratgeber/redaktion', [StaticPageController::class, 'editorial'])->name('portal.blog.editorial');
    // guide.category muss vor guide.show stehen (#17)
    Route::get('/ratgeber/kategorie/{slug}', [GuideController::class, 'category'])
        ->middleware(HandleGuideRedirects::class)
        ->name('guide.category');
    Route::get('/ratgeber/tag/{slug}', [PublicBlogController::class, 'tag'])->name('portal.blog.tag');

    // Vorschau einer Ratgeber-Fassung aus Pruef-Queue und Versionshistorie
    // (#16): signiert, nur fuer Redaktions-Accounts, gleicher View wie
    // guide.show. Muss vor der Slug-Route stehen, sonst schluckt diese den Pfad.
    Route::get('/ratgeber/fassung/{version}', [GuidePreviewController::class, 'show'])
        ->whereNumber('version')
        ->middleware(['signed', EnsureContentPreviewAccess::class])
        ->name('guide.preview');

    // Alte Kategorie- und Artikeladressen leiten vor der 404-Seite mit 301 um (#18)
    Route::get('/ratgeber/{slug}', [GuideController::class, 'show'])
        ->middleware(HandleGuideRedirects::class)
        ->name('guide.show');

    // Autorenprofil der Redaktion (#18)
    Route::get('/autor/{slug}', [AuthorController::class, 'show'])->name('portal.author.show');

    // Bewertungslink (#12): fuehrt zum geoeffneten Bewertungsformular im Profil
    Route::get('/bewerten/{companySlug}', ReviewInviteController::class)
        ->where('companySlug', '\d+-.+')
        ->name('portal.reviews.invite');

    // Verifizierungsnachweis (#11): signiert, nur fuer Administratoren
    Route::get('/pruefung/nachweise/{verification}', VerificationDocumentController::class)
        ->whereNumber('verification')
        ->middleware(['auth', 'signed'])
        ->name('portal.verifications.document');

    // Städteseiten (#213)
    Route::get('/staedte', [PublicCityController::class, 'index'])->name('portal.cities.index');
    Route::get('/staedte/{slug}', [PublicCityController::class, 'show'])->name('portal.cities.show');

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
            Route::delete('/bewertungen/{review}/antwort', [OwnerDashboardController::class, 'deleteReviewResponse'])->name('reviews.delete-response');
            Route::get('/bewertungen/qr-code.svg', [OwnerDashboardController::class, 'reviewQrCode'])->name('reviews.qr-code');
            Route::get('/statistiken', [OwnerDashboardController::class, 'stats'])->name('stats');
            Route::get('/statistiken/api', [OwnerDashboardController::class, 'statsApi'])->name('stats.api');
            Route::get('/einstellungen', [OwnerDashboardController::class, 'settings'])->name('settings');
            Route::get('/premium', [OwnerDashboardController::class, 'premium'])->name('premium');
            // Mein Plan und Upsell-Banner (#17)
            Route::get('/mein-plan', [OwnerDashboardController::class, 'plan'])->name('plan');
            // Abo-Details und Kuendigung im Panel statt in der Verwaltung
            Route::get('/abo', [OwnerDashboardController::class, 'subscription'])->name('subscription');
            Route::post('/upsell-hinweis/ausblenden', [OwnerDashboardController::class, 'dismissUpsellBanner'])->name('upsell-banner.dismiss');

            // Anfragen aus dem Leadsystem (#33); Zugriff ueber CompanyInquiryPolicy
            Route::get('/anfragen', [OwnerInquiryController::class, 'index'])->name('inquiries.index');
            Route::get('/anfragen/{inquiry}', [OwnerInquiryController::class, 'show'])->whereNumber('inquiry')->name('inquiries.show');
            Route::patch('/anfragen/{inquiry}/status', [OwnerInquiryController::class, 'updateStatus'])->whereNumber('inquiry')->name('inquiries.status');

            // Stellenanzeigen (#179, #181 Premium-Gate)
            // Index: Soft-Lock im Controller (zeigt locked-View für Free-User)
            Route::get('/stellenanzeigen', [OwnerJobController::class, 'index'])->name('jobs.index');
            // Delete: Kein Premium nötig (Aufräumen nach Downgrade erlaubt)
            Route::delete('/stellenanzeigen/{id}', [OwnerJobController::class, 'destroy'])->name('jobs.destroy');

            // Premium-geschützte Job-Aktionen
            Route::middleware([\App\Http\Middleware\EnsurePremiumCompany::class])
                ->group(function () {
                    Route::get('/stellenanzeigen/erstellen', [OwnerJobController::class, 'create'])->name('jobs.create');
                    Route::get('/stellenanzeigen/{id}/bearbeiten', [OwnerJobController::class, 'edit'])->name('jobs.edit');
                    Route::post('/stellenanzeigen/{id}/toggle', [OwnerJobController::class, 'toggle'])->name('jobs.toggle');
                    Route::get('/stellenanzeigen/{id}/bewerbungen', [OwnerJobController::class, 'applications'])->name('jobs.applications');
                    Route::post('/stellenanzeigen/{jobId}/bewerbungen/{applicationId}/status', [OwnerJobController::class, 'updateApplicationStatus'])->name('jobs.applications.status');
                });
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

            // --- Stellenanzeigen (#182) ---
            Route::get('/stellenanzeigen', [VerwaltungJobController::class, 'index'])->name('jobs.index');
            Route::get('/stellenanzeigen/{id}', [VerwaltungJobController::class, 'show'])->name('jobs.show');

            // --- FAQ (#196) ---
            Route::get('/faq', [VerwaltungFaqController::class, 'index'])->name('faqs.index');

            // --- Ratgeber-Blog (#201, #202) ---
            Route::get('/ratgeber', [VerwaltungBlogController::class, 'index'])->name('blog.index');
            Route::get('/ratgeber/erstellen', [VerwaltungBlogController::class, 'create'])->name('blog.create');
            Route::get('/ratgeber/{id}/bearbeiten', [VerwaltungBlogController::class, 'edit'])->name('blog.edit');
            Route::delete('/ratgeber/{id}', [VerwaltungBlogController::class, 'destroy'])->name('blog.destroy');
            Route::get('/ratgeber/kategorien', [VerwaltungBlogController::class, 'categories'])->name('blog.categories');
            Route::get('/ratgeber/tags', [VerwaltungBlogController::class, 'tags'])->name('blog.tags');

            // --- Werbung (Ad-Slots) ---
            Route::get('/werbung', [VerwaltungAdController::class, 'index'])->name('ads.index');
            Route::get('/werbung/erstellen', [VerwaltungAdController::class, 'create'])->name('ads.create');
            Route::post('/werbung', [VerwaltungAdController::class, 'store'])->name('ads.store');
            Route::put('/werbung/ads-txt', [VerwaltungAdController::class, 'updateAdsTxt'])->name('ads.update-ads-txt');
            Route::get('/werbung/{id}/bearbeiten', [VerwaltungAdController::class, 'edit'])->name('ads.edit');
            Route::put('/werbung/{id}', [VerwaltungAdController::class, 'update'])->name('ads.update');
            Route::delete('/werbung/{id}', [VerwaltungAdController::class, 'destroy'])->name('ads.destroy');

            // --- Statistiken (#174) ---
            Route::get('/statistiken', [VerwaltungStatisticsController::class, 'index'])->name('statistics.index');
            Route::get('/statistiken/firma/{id}', [VerwaltungStatisticsController::class, 'show'])->name('statistics.show');
            // JSON-API für Livewire/AJAX
            Route::get('/statistiken/api/overview', [VerwaltungStatisticsController::class, 'apiOverview'])->name('statistics.api.overview');
            Route::get('/statistiken/api/company/{id}', [VerwaltungStatisticsController::class, 'apiCompany'])->name('statistics.api.company');
            Route::get('/statistiken/api/top-companies', [VerwaltungStatisticsController::class, 'apiTopCompanies'])->name('statistics.api.top-companies');
        });

    // Tracking: Contact Click (AJAX POST aus dem Frontend)
    Route::post('/tracking/contact-click', [TrackingController::class, 'contactClick'])
        ->name('portal.tracking.contact-click');

    // Betriebsstatistik (#15): Klick-Beacon aus resources/js/stats-beacon.js
    Route::post('/stats/beacon', StatsBeaconController::class)
        ->middleware('throttle:120,1')
        ->name('portal.stats.beacon');

    // Exklusive Anfragen aus dem Profil-Dialog (#9): Weg beim Oeffnen, Absenden
    Route::get('/anfrage/{company}/weg', [ExclusiveLeadController::class, 'route'])
        ->whereNumber('company')
        ->middleware('throttle:60,1')
        ->name('portal.leads.route');
    Route::post('/anfrage/{company}', [ExclusiveLeadController::class, 'store'])
        ->whereNumber('company')
        ->middleware('throttle:10,1')
        ->name('portal.leads.store');

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
        ->middleware(TrackCompanyPageView::class)
        ->where(['citySlug' => '[a-z0-9\-]+', 'companySlug' => '\d+-.+'])
        ->name('portal.companies.show.city');

    // Firmen-Detailseite: /{id}-{slug} (Default, muss LETZTE Route sein)
    Route::get('/{companySlug}', [CompanyController::class, 'show'])
        ->middleware(TrackCompanyPageView::class)
        ->where('companySlug', '\d+-.+')
        ->name('portal.companies.show');
});
