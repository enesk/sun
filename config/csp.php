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
| Verstoesse werden seit #51 serverseitig gesammelt: die Middleware haengt
| `report-uri` UND `report-to` an die Policy und setzt dazu den Header
| `Reporting-Endpoints`. Beide, weil Chrome auf `report-to` umgestellt hat und
| Safari/Firefox weiter nur `report-uri` lesen. Ziel ist POST /csp-bericht
| (App\Http\Controllers\Portal\CspReportController), Auswertung mit
| `php artisan csp:berichte`, Pruning mit `php artisan csp:prune`.
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
    | Sammelstelle fuer Verstoesse (#51)
    |--------------------------------------------------------------------------
    |
    | `path` ist die Adresse, die in `report-uri`, in `report-to` und im Header
    | `Reporting-Endpoints` landet. Sie muss zur Route `portal.csp-report` in
    | routes/tenant.php passen — wird hier etwas geaendert, dort mit aendern.
    |
    | `group` ist der Name der Berichtsgruppe fuer `report-to`; er verbindet die
    | Direktive mit dem Eintrag im `Reporting-Endpoints`-Header und hat sonst
    | keine Bedeutung.
    |
    | `enabled => false` laesst die Route stehen, setzt aber keine der beiden
    | Direktiven und schreibt nichts. Das ist der Schalter, wenn eine Flut
    | durchkommt, die die Obergrenzen nicht abfangen.
    |
    */

    'report' => [
        'enabled' => (bool) env('CSP_REPORT_ENABLED', true),

        'path' => '/csp-bericht',
        'group' => 'csp-bericht',

        // Groesserer Rumpf wird verworfen. Ein CSP-Bericht hat wenige hundert
        // Byte; Chrome bundelt mehrere, bleibt aber weit darunter.
        'max_body_bytes' => (int) env('CSP_REPORT_MAX_BODY_BYTES', 64000),

        // Hoechstens so viele NEUE Zeilen je Portal und Stunde. Erhoehungen
        // bestehender Zeilen sind nie betroffen, der Zaehler bleibt richtig.
        // Schutz gegen Quellen mit Cache-Buster in der URL, bei denen jeder
        // Aufruf einen neuen Fingerprint ergibt.
        'max_new_rows_per_hour' => (int) env('CSP_REPORT_MAX_NEW_ROWS_PER_HOUR', 200),

        // Aufbewahrung in Tagen; danach raeumt csp:prune ab. Kuerzer als beim
        // Turnstile-Log (90 Tage): ein CSP-Befund wird behoben oder in die
        // Policy aufgenommen, eine alte Zeile sagt danach nichts mehr.
        'retention_days' => (int) env('CSP_REPORT_RETENTION_DAYS', 30),

        // Zeilen je Loeschrunde von csp:prune.
        'prune_chunk' => (int) env('CSP_REPORT_PRUNE_CHUNK', 1000),
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
