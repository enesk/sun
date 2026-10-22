<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentProviders\PaddleController;
use App\Http\Controllers\RoadmapController;
use App\Services\PlanService;
use App\Services\SessionService;
use App\Services\TenantCreationService;
use App\Services\UserDashboardService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| If you want the URL to be added to the sitemap, add a "sitemapped" middleware to the route (it has to GET route)
|
*/

Route::get('/', function () {
    return view('home');
})->name('home')->middleware('sitemapped');

Route::get('/dashboard', function (UserDashboardService $dashboardService) {
    return redirect($dashboardService->getUserDashboardUrl(Auth::user()));
})->name('dashboard')->middleware('auth');

Auth::routes();

// Einstieg ins Content-Panel (#14). Das Panel hat kein eigenes Formular,
// angemeldet wird ueber den SaaSykit-Login (Guard 'web', inkl. 2FA und
// Captcha); danach fuehrt die intended URL zurueck ins Panel.
Route::get('/'.trim((string) config('content.panel.path', 'content'), '/').'/login', function () {
    $panelUrl = url(config('content.panel.path', 'content'));

    if (Auth::check()) {
        return redirect($panelUrl);
    }

    Redirect::setIntendedUrl($panelUrl);

    return redirect()->route('login');
})->name('content.login');

Route::get('/plan/start', function (
    TenantCreationService $tenantCreationService,
    SessionService $sessionService,
    PlanService $planService,
) {
    if ($planService->getDefaultProduct() !== null) {
        if (! auth()->check()) {
            $sessionService->setCreateTenantForFreePlanUser(true);
        } else {
            $tenantCreationService->createTenantForFreePlanUser(auth()->user());
        }
    }

    return redirect()->route('register');
})->name('plan.start');

Route::get('/email/verify', function () {
    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    $user = $request->user();

    // Auf einer Portal-Domain zurueck ins Portal statt auf die SaaSykit-Dankeseite.
    if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
        return redirect('/');
    }

    if ($user->hasVerifiedEmail()) {
        return redirect()->route('registration.thank-you');
    }

    return redirect('/');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::get('/phone/verify', function () {
    return view('verify.sms-verification');
})->name('user.phone-verify')
    ->middleware('auth');

Route::get('/phone/verified', function () {
    return view('verify.sms-verification-success');
})->name('user.phone-verified')
    ->middleware('auth');

Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/registration/thank-you', function () {
    return view('auth.thank-you');
})->middleware('auth')->name('registration.thank-you');

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.redirect');

Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.callback');

Route::get('/checkout/plan/{planSlug}', [
    App\Http\Controllers\SubscriptionCheckoutController::class,
    'subscriptionCheckout',
])->name('checkout.subscription');

Route::get('/checkout/convert-subscription/{subscriptionUuid}', [
    App\Http\Controllers\SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckout',
])->name('checkout.convert-local-subscription');

Route::get('/already-subscribed', function () {
    return view('checkout.already-subscribed');
})->name('checkout.subscription.already-subscribed');

Route::get('/checkout/subscription/success', [
    App\Http\Controllers\SubscriptionCheckoutController::class,
    'subscriptionCheckoutSuccess',
])->name('checkout.subscription.success')->middleware('auth');

Route::get('/checkout/convert-subscription-success', [
    App\Http\Controllers\SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckoutSuccess',
])->name('checkout.convert-local-subscription.success')->middleware('auth');

Route::get('/payment-provider/paddle/payment-link', [
    PaddleController::class,
    'paymentLink',
])->name('payment-link.paddle');

Route::get('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    App\Http\Controllers\SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan')->middleware('auth');

Route::post('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    App\Http\Controllers\SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan.post')->middleware('auth');

Route::get('/subscription/change-plan-thank-you', [
    App\Http\Controllers\SubscriptionController::class,
    'success',
])->name('subscription.change-plan.thank-you')->middleware('auth');

// blog
Route::controller(BlogController::class)
    ->prefix('/blog')
    ->group(function () {
        Route::get('/', 'all')->name('blog')->middleware('sitemapped');
        Route::get('/category/{slug}', 'category')->name('blog.category');
        Route::get('/{slug}', 'view')->name('blog.view');
    });

Route::get('/terms-of-service', function () {
    return view('pages.terms-of-service');
})->name('terms-of-service')->middleware('sitemapped');

Route::get('/privacy-policy', function () {
    return view('pages.privacy-policy');
})->name('privacy-policy')->middleware('sitemapped');

// Product checkout routes

Route::get('/buy/product/{productSlug}/{quantity?}', [
    App\Http\Controllers\ProductCheckoutController::class,
    'addToCart',
])->name('buy.product');

Route::get('/checkout/product', [
    App\Http\Controllers\ProductCheckoutController::class,
    'productCheckout',
])->name('checkout.product');

Route::get('/checkout/product/success', [
    App\Http\Controllers\ProductCheckoutController::class,
    'productCheckoutSuccess',
])->name('checkout.product.success')->middleware('auth');

// roadmap

Route::controller(RoadmapController::class)
    ->prefix('/roadmap')
    ->group(function () {
        Route::get('/', 'index')->name('roadmap');
        Route::get('/i/{itemSlug}', 'viewItem')->name('roadmap.viewItem');
        Route::get('/suggest', 'suggest')->name('roadmap.suggest')->middleware('auth');
    });

// Invitations

Route::get('/invitations', [
    App\Http\Controllers\InvitationController::class,
    'index',
])->name('invitations')->middleware('auth');

// Invoice

Route::controller(InvoiceController::class)
    ->prefix('/invoice')
    ->group(function () {
        Route::get('/generate/{transactionUuid}', 'generate')->name('invoice.generate');
        Route::get('/preview', 'preview')->name('invoice.preview');
    });

// Vorschau eines Werbemittels im Admin-Panel (#10). Eigene Route und nicht
// `srcdoc`, weil fremdes Markup nicht unter der CSP des Panels laufen darf;
// der Zugang liegt beim Betreiber (AdsAdminAccess prueft der Controller).
// Nicht unter /admin, damit sie nicht mit den Routen des Filament-Panels
// konkurriert.
Route::get('/werbemittel/{adUnit}/vorschau', App\Ads\Http\Controllers\AdUnitPreviewController::class)
    ->middleware('auth')
    ->whereNumber('adUnit')
    ->name('admin.ads.unit-preview');

/*
| Anhang einer Anfrage ansehen (SUN-RQ, Enes 22.10.2026)
|
| Die Nachweise der Uebernahmeantraege standen in der Inbox nur als
| Dateiname — ohne Weg, sie zu oeffnen. Die Dateien liegen auf der privaten
| Platte und bekommen keine oeffentliche URL; diese Route ist der einzige
| Zugang und prueft Anmeldung, Administratorrecht und die Zugehoerigkeit des
| Anhangs zur Anfrage. Nicht unter /admin, damit sie nicht mit den Routen des
| Filament-Panels konkurriert.
*/
Route::get('/anfrage/{request}/anhang/{attachment}', App\ProfileRequests\Http\Controllers\RequestAttachmentController::class)
    ->middleware('auth')
    ->whereNumber('request')
    ->whereNumber('attachment')
    ->name('admin.requests.attachment');

/*
| Zentrale Anfragen-Inbox: Magic-Link und Stornolink (SUN-RQ #6)
|
| Hier und nicht in routes/tenant.php: `profile_requests` liegt in der
| Central-DB, die Bestaetigung braucht also keinen Tenant-Kontext. Die
| Routen antworten damit auf jeder Portal-Domain — genau dort landen die
| Links, weil die Bestaetigungsmail auf der Domain ihres Portals signiert
| wird (App\ProfileRequests\Verification\RequestVerifier::links()).
|
| Ohne Anmeldung erreichbar, deshalb durchgesetzt. Die Signatur der URL und
| der Token-Hash in der Tabelle muessen beide stimmen; `resend` versendet
| eine Mail und ist deshalb am engsten begrenzt.
*/
Route::controller(App\Http\Controllers\RequestVerificationController::class)
    ->prefix('/anfrage')
    ->group(function () {
        Route::get('/{request}/bestaetigen/{token}', 'verify')
            ->middleware('throttle:20,1')
            ->whereNumber('request')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('profile-requests.verify');

        Route::get('/{request}/ablehnen/{token}', 'cancel')
            ->middleware('throttle:20,1')
            ->whereNumber('request')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('profile-requests.cancel');

        // Methodenname bewusst nicht 'resend': Route::controller loest den
        // String ueber class_exists() auf, und PHP-Klassennamen sind nicht
        // gross-/kleinschreibungsabhaengig — die Klasse `Resend` des
        // Mailproviders faengt 'resend' ab ("Invalid route action").
        Route::post('/{request}/neuer-link', 'resendLink')
            ->middleware('throttle:5,10')
            ->whereNumber('request')
            ->name('profile-requests.verify.resend');
    });

/*
| Oeffentliche Statusseite eines Vorgangs (#10)
|
| Ausserhalb der Gruppe oben: `Route::controller()` deutet ein
| String-Handle als Methode des Gruppen-Controllers, und der Klassenname
| der Livewire-Komponente ist ein String.
|
| Signiert, aber ohne Ablauf — ein Vorgang laeuft Wochen, und eine Adresse,
| die nach 72 Stunden ins Leere zeigt, erzeugt genau die Rueckfrage, die die
| Seite ersparen soll. Dafuer `throttle`, `noindex` und ein Inhalt, der nur
| aus dem besteht, was der Betrieb selbst eingereicht hat.
|
| Der Routenname ist der, den der Dialog am Profil (#4) schon erwartet
| (App\Livewire\Portal\ProfileRequestDialog::statusUrl()).
*/
Route::get('/anfrage/{request}/status', App\Livewire\Public\RequestStatus::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('request')
    ->name('portal.requests.status');
