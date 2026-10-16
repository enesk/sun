<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(App\Http\Middleware\RedirectWwwToNonWww::class);

        /*
         * Die Cookies des Einwilligungs-Hinweises bleiben unverschluesselt
         * (#21). Geschrieben werden sie im Browser mit `document.cookie`
         * (resources/views/themes/sun-v2/js/modules/cookie-consent.js und
         * resources/js/app.js), Laravel sieht also einen Klartextwert.
         *
         * Ohne diese Ausnahme setzt EncryptCookies jeden Wert, den es nicht
         * entschluesseln kann, im Cookie-Beutel auf null —
         * `request()->cookie('cookie_consent_marketing')` liefert dann
         * IMMER null. Das ist der gefaehrlichste Fall von allen: null sieht
         * aus wie „nicht eingewilligt", der Fehler tarnt sich also als
         * korrektes Verhalten, und niemand merkt, dass die Einwilligung
         * ueberhaupt nicht gelesen wird.
         *
         * Keine Geheimnisse, keine Sitzungsbindung: der Inhalt ist "1" oder
         * "0" und wird ohnehin im Browser gesetzt und gelesen.
         */
        $middleware->encryptCookies(except: [
            'cookie_consent_given',
            'cookie_consent_statistics',
            'cookie_consent_marketing',
        ]);

        $middleware->appendToGroup('web', [
            App\Http\Middleware\ContentSecurityPolicy::class,
            App\Http\Middleware\BlockedUser::class,
            App\Http\Middleware\UpdateUserLastSeenAt::class,
            App\Http\Middleware\TrackReferralCode::class,
        ]);

        $middleware->alias([
            'sitemapped' => \App\Http\Middleware\Sitemapped::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            // Vorschau der Werbeplaetze im Panel (#6); ohne Token wirkungslos.
            'ad-editor' => \App\Ads\Http\Middleware\AdEditorMode::class,
        ]);

        $middleware->group('universal', [
            App\Providers\TenancyServiceProvider::TENANCY_INITIALIZER,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
