<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Content-Security-Policy (#11/#21, docs/turnstile.md Abschnitt 14)
|--------------------------------------------------------------------------
|
| Eine Stelle fuer alle Portale: App\Http\Middleware\ContentSecurityPolicy
| setzt daraus den Header. Es gibt bewusst KEINE Policy je Tenant — ein Portal
| unterscheidet sich in Farbe und Branche, nicht in seinen Skriptquellen.
|
| `mode` steuert, was der Header bewirkt:
|
|   off      Kein Header.
|   report   Content-Security-Policy-Report-Only: der Browser meldet
|            Verstoesse in der Konsole, blockiert aber nichts. Der Weg, eine
|            Aenderung vor dem Durchsetzen zu pruefen.
|   enforce  Content-Security-Policy: der Browser blockiert. Vorgabe seit #21.
|
| Seit #21 liefern die Themes keine ausfuehrbaren Inline-Skripte mehr aus; die
| Logik liegt in Modulen unter resources/js/ bzw. im Theme-JS und haengt an
| data-Attributen. 'unsafe-inline' steht darum nicht in `script-src` und gehoert
| dort auch nicht hinein — es wuerde die Policy entwerten.
|
| Drei Quellen leitet die Middleware aus anderen Konfigurationen ab, damit eine
| Adressaenderung an genau einer Stelle wirkt:
|   * Turnstile  aus config('turnstile.script_url'/'siteverify_url')
|   * Leadsystem aus config('leads.api_url')  — connect-src des Anfrage-Dialogs
|   * Vite-Dev   aus dem laufenden `npm run dev` (Vite::isRunningHot())
|
| Dazu kommt ein Nonce je Anfrage (App\Services\Security\CspNonce). Es gilt
| ausschliesslich fuer fremdes HTML, das nicht im Repository steht: die
| Werbe-Schnipsel aus ad_slots und config('app.tracking_scripts'). Beides sind
| Vorlagen von AdSense bzw. Google mit eigenen <script>-Bloecken.
|
*/
return [

    'mode' => env('CSP_MODE', 'enforce'),

    /*
    |--------------------------------------------------------------------------
    | Direktiven
    |--------------------------------------------------------------------------
    |
    | Quellenlisten je Direktive. Leere Liste = Direktive wird nicht gesetzt.
    | `frame-ancestors` steht hier nicht: das Bewertungs-Widget
    | (ReviewWidgetController) setzt sich dafuer einen eigenen Header, und den
    | ueberschreibt die Middleware nicht.
    |
    */

    'directives' => [

        'default-src' => ["'self'"],

        'script-src' => [
            "'self'",

            // Livewire 3 bringt Alpine mit, und Alpine wertet jeden
            // x-/wire-Ausdruck mit new Function() aus. Ohne 'unsafe-eval'
            // steht jede Livewire-Seite still (Checkout, Bewertung,
            // Uebernahme, Eintragung). Anders als 'unsafe-inline' laesst das
            // kein eingeschleustes <script> laufen.
            "'unsafe-eval'",

            // Google Analytics 4 und Tag Manager (resources/js/analytics.js)
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',

            // AdSense: Auto Ads und Anzeigenbloecke laden ueber mehrere Hosts
            // nach (resources/views/components/ad-slot.blade.php)
            'https://pagead2.googlesyndication.com',
            'https://tpc.googlesyndication.com',
            'https://partner.googleadservices.com',
            'https://adservice.google.com',
            'https://googleads.g.doubleclick.net',
            'https://fundingchoicesmessages.google.com',

            // reCAPTCHA (SaaSykit-Registrierung, config('app.recaptcha_enabled'))
            'https://www.google.com',
            'https://www.gstatic.com',

            // Lucide-Icons im Default-Theme (layouts/app.blade.php)
            'https://unpkg.com',

            // Paddle-Checkout (resources/views/payment-providers/paddle.blade.php)
            'https://cdn.paddle.com',

            // Eingebettete Funnel-Seite (resources/views/pages/funnel.blade.php)
            'https://funnels.widimedia.com',
        ],

        'frame-src' => [
            "'self'",

            // reCAPTCHA-Aufgabe
            'https://www.google.com',
            'https://www.gstatic.com',

            // Anzeigen rendern in iframes. pagead2 und ep2 kommen aus der
            // Browser-Messung #28: AdSense rahmt seine Bloecke selbst ein und
            // laedt zur Sichtbarkeitspruefung (Sodar) einen Rahmen von
            // ep2.adtrafficquality.google nach.
            'https://googleads.g.doubleclick.net',
            'https://tpc.googlesyndication.com',
            'https://pagead2.googlesyndication.com',
            'https://ep2.adtrafficquality.google',

            // Kartenausschnitt auf der Firmendetailseite der Themes
            // default/starter (pages/companies/show.blade.php)
            'https://maps.google.com',

            // Paddle-Overlay
            'https://buy.paddle.com',
            'https://sandbox-buy.paddle.com',
        ],

        'connect-src' => [
            "'self'",

            // Messwerte von GA4/GTM
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://*.google-analytics.com',
            'https://*.analytics.google.com',

            // AdSense meldet Sichtbarkeit und Fehler zurueck. ep1 ist die
            // Sodar-Konfiguration, gemessen in #28.
            'https://pagead2.googlesyndication.com',
            'https://googleads.g.doubleclick.net',
            'https://ep1.adtrafficquality.google',
        ],

        'style-src' => [
            "'self'",

            // Filament und die Portal-Themes setzen Stile am Element; ohne
            // 'unsafe-inline' bleibt das Layout kaputt. Anders als bei
            // script-src laesst sich damit kein Code ausfuehren.
            "'unsafe-inline'",

            // Poppins aus dem Google-CDN, eingebunden in
            // components/layouts/partials/head.blade.php (Registrierung,
            // Verifizierung, Checkout der SaaSykit-Layouts)
            'https://fonts.googleapis.com',
        ],
        'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'media-src' => ["'self'", 'data:', 'blob:'],
        'worker-src' => ["'self'", 'blob:'],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'object-src' => ["'none'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Zusaetzliche Quellen aus der .env
    |--------------------------------------------------------------------------
    |
    | Leerzeichengetrennt, werden an script-src/frame-src/connect-src gehaengt.
    | Fuer Dinge, die je Installation abweichen — nicht fuer Turnstile, das
    | Leadsystem oder den Vite-Dev-Server, die leitet die Middleware ab.
    |
    */

    'extra' => [
        'script-src' => env('CSP_EXTRA_SCRIPT_SRC'),
        'frame-src' => env('CSP_EXTRA_FRAME_SRC'),
        'connect-src' => env('CSP_EXTRA_CONNECT_SRC'),
    ],
];
