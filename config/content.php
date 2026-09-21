<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Content-Panel, Budget-Notbremse und Leistungsdaten
|--------------------------------------------------------------------------
|
| Rest der alten Content-Pipeline (Rueckbau #23, Reduktion #35). Das
| Ratgebersystem liest config/guide.php; hier stehen nur noch Panel, die
| aeussere Budgetgrenze (App\Guide\Llm\ContentBudgetGuard), Search Console,
| AdSense, Metrik-Collector, Alarme, die alte Einstellungsseite und die
| Panel-Farben. Ziel laut docs/guide-system.md §9: nur noch config/guide.php.
|
| WICHTIG: Alle Provider-Zugangsdaten kommen ausschliesslich aus .env.
| Es werden keine API-Keys in der Datenbank abgelegt und keine Keys an das
| Frontend oder an das Content-Panel ausgeliefert.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Feature-Schalter
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('CONTENT_PIPELINE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Panel / Guard
    |--------------------------------------------------------------------------
    |
    | Das Content-Dashboard laeuft in einem eigenen Filament-Panel, meldet sich
    | aber ueber den gewoehnlichen SaaSykit-Login an ('web'-Guard,
    | App\Models\User). Zugang haben ausschliesslich Administratoren
    | (users.is_admin), geprueft in App\Models\User::canAccessPanel().
    |
    */

    'panel' => [
        'id' => 'content',
        'path' => env('CONTENT_PANEL_PATH', 'content'),
        'guard' => 'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tagesziele
    |--------------------------------------------------------------------------
    |
    | Grundlage der Kostenrechnung: 2 Artikel x 24 Tenants x 30 Tage = 1.440
    | Artikel pro Monat, 48 pro Tag.
    |
    */

    'targets' => [
        'articles_per_tenant_per_day' => (int) env('CONTENT_ARTICLES_PER_TENANT_PER_DAY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget-Guard
    |--------------------------------------------------------------------------
    |
    | Harte Obergrenzen in USD. Der ContentBudgetGuard (aeussere Notbremse
    | hinter App\Guide\Llm\BudgetGuard) prueft vor jedem Provider-Aufruf den
    | bereits verbrauchten Tagesbetrag. Bei
    | Ueberschreitung wird der Job released (siehe release_delay_seconds) und
    | ein Alarm im Content-Dashboard erzeugt.
    |
    | Kalkulation (Details in docs/content-pipeline.md):
    |   ~0.40 USD pro Artikel inkl. Retries -> ~19 USD/Tag Artikelproduktion
    |   + ~4 USD/Tag Themenfindung + ~1 USD/Tag Metriken = ~24 USD/Tag.
    |   Tagesbudget 35 USD gibt rund 45% Puffer.
    |
    */

    'budget' => [
        'daily_usd' => (float) env('CONTENT_BUDGET_DAILY_USD', 35.0),
        'monthly_usd' => (float) env('CONTENT_BUDGET_MONTHLY_USD', 1050.0),

        // Obergrenze je Tenant und Tag, damit ein einzelner Tenant das
        // Gesamtbudget nicht aufbraucht.
        'daily_usd_per_tenant' => (float) env('CONTENT_BUDGET_DAILY_USD_PER_TENANT', 2.5),

        // Harte Reissleine je einzelnem Artikel.
        'max_usd_per_article' => (float) env('CONTENT_BUDGET_MAX_USD_PER_ARTICLE', 1.5),

        // Anteil des Tagesbudgets je Provider (Summe darf 1.0 nicht ueberschreiten).
        'provider_share' => [
            'anthropic' => 0.75,
            'images' => 0.12,
        ],

        // Ab diesem Anteil des Tagesbudgets wird gewarnt (Dashboard + Mail).
        'warn_threshold' => 0.8,

        // Wie lange ein Job zurueckgestellt wird, wenn das Budget erschoepft ist.
        'release_delay_seconds' => 1800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Nur noch die Search Console (Leistungsdaten). Modell- und Bild-Provider
    | des Ratgebersystems stehen in config/guide.php.
    |
    */

    'providers' => [

        'search_console' => [
            // Absoluter Pfad zur JSON-Schluesseldatei des Service Accounts.
            // Ein Service Account bedient alle Properties; er muss in der
            // Search Console jeder Property als Nutzer eingetragen sein.
            // Der Schluessel gehoert ausserhalb des Web-Roots (z. B.
            // storage/app/private/google/search-console.json) — der
            // SearchConsoleClient verweigert einen Pfad unterhalb von public/.
            'credentials_path' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),

            'base_url' => env('GSC_BASE_URL', 'https://searchconsole.googleapis.com/webmasters/v3'),
            'token_url' => env('GOOGLE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'timeout' => 60,

            // Zugriffstoken gelten 3600 s; etwas frueher erneuern.
            'token_ttl' => 3300,

            // searchanalytics.query: hoechstes von der API erlaubtes rowLimit.
            'row_limit' => 5000,

            // Obergrenze der Paginierung je Property und Lauf, damit eine
            // grosse Property den Job nicht sprengt.
            'max_rows' => (int) env('GSC_MAX_ROWS', 25000),

            // 28-Tage-Fenster mit 3 Tagen Verzoegerung: GSC liefert die
            // letzten Tage nachtraeglich, dataState 'final' waere sonst leer.
            'lookback_days' => 28,
            'lag_days' => 3,
            'data_state' => 'final',

            'pricing' => [
                'per_request' => 0.0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AdSense (#23)
    |--------------------------------------------------------------------------
    |
    | Ertragsdaten je Ratgeber ueber die AdSense Management API v2
    | (accounts.reports.generate, Dimension PAGE_URL). Das Dienstkonto aus
    | GOOGLE_SERVICE_ACCOUNT_JSON muss im AdSense-Konto Zugriff haben; ohne
    | Freischaltung bleibt das Feature aus und der Collector schreibt
    | `pageviews` und `adsense_revenue_usd` als null statt als 0.
    |
    */

    'adsense' => [
        'enabled' => (bool) env('CONTENT_ADSENSE_ENABLED', false),

        // 'accounts/pub-1234567890123456'; das Praefix darf fehlen.
        'account' => env('ADSENSE_ACCOUNT_ID'),

        // Ohne eigene Angabe derselbe Schluessel wie fuer die Search Console.
        'credentials_path' => env('ADSENSE_SERVICE_ACCOUNT_JSON'),

        'base_url' => env('ADSENSE_BASE_URL', 'https://adsense.googleapis.com/v2'),
        'scope' => 'https://www.googleapis.com/auth/adsense.readonly',
        'currency' => env('ADSENSE_CURRENCY', 'USD'),
        'timeout' => 60,
        'max_rows' => (int) env('ADSENSE_MAX_ROWS', 5000),

        // AdSense meldet den Vortag vollstaendig; wie bei der Search Console
        // wird deshalb mit Verzoegerung gelesen.
        'lag_days' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrik-Collector (#23)
    |--------------------------------------------------------------------------
    |
    | Der Collector holt die Search-Console-Rohzeilen nach article_metrics_raw
    | (SearchConsoleRawFetcher) und verdichtet sie je Ratgeber zu einer
    | Tageszeile. Die Lernschleife fuer das Themen-Scoring ist mit der
    | Themenfindung entfallen (Rueckbau #23 des Ratgebersystems).
    |
    */

    'metrics' => [
        // Aufbewahrung der Tageszeilen. Drei Jahre, damit Jahresvergleiche
        // moeglich bleiben; die Tabelle bleibt trotzdem klein.
        'retention_days' => (int) env('CONTENT_METRICS_RETENTION_DAYS', 1095),

        // Alter in Tagen, zu dem ein Snapshot festgehalten wird.
        'snapshots' => [7, 30, 90],

        // Praefix, an dem eine Ratgeber-URL in den Search-Console-Zeilen
        // erkannt wird, und Aufbewahrung der Rohzeilen (article_metrics_raw,
        // SearchConsoleRawFetcher). Bis #23 unter sources.gsc_gap.
        'article_path_prefix' => env('CONTENT_RATGEBER_PREFIX', '/ratgeber/'),
        'raw_retention_days' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Toter Provider-Zugang (App\Guide\Llm\ProviderAccountGuard)
    |--------------------------------------------------------------------------
    |
    */

    'resilience' => [
        // Wie lange ein toter Zugang (Guthaben aufgebraucht, Schluessel
        // abgelehnt, #104) als tot gilt, bevor ein Probeaufruf ihn wieder
        // freigeben darf.
        'account_recheck_seconds' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrik-Queue, Anzeigezeit und Alarm-Mails
    |--------------------------------------------------------------------------
    */

    'pipeline' => [
        'queues' => [
            'metrics' => 'content-metrics',
        ],

        // Uhrzeit (Europe/Berlin) des Metrik-Collectors, nur Anzeige.
        'schedule' => [
            'metrics_at' => '06:30',
        ],

        // Kritische Alarme gehen einmalig sofort an die Administratoren.
        'alerts' => [
            'mail' => (bool) env('CONTENT_ALERT_MAIL', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regionen (#8, #11, #12)
    |--------------------------------------------------------------------------
    |
    | Schluessel ist immer der ISO-3166-2-Code ('DE-BY'); so stehen die
    | Bundeslaender in tenant_content_settings.preferred_states_json und in
    | guide_legacy_articles.region_code (#34).
    |
    */

    'regions' => [

        'states' => [
            'DE-BW' => ['name' => 'Baden-Wuerttemberg'],
            'DE-BY' => ['name' => 'Bayern'],
            'DE-BE' => ['name' => 'Berlin'],
            'DE-BB' => ['name' => 'Brandenburg'],
            'DE-HB' => ['name' => 'Bremen'],
            'DE-HH' => ['name' => 'Hamburg'],
            'DE-HE' => ['name' => 'Hessen'],
            'DE-MV' => ['name' => 'Mecklenburg-Vorpommern'],
            'DE-NI' => ['name' => 'Niedersachsen'],
            'DE-NW' => ['name' => 'Nordrhein-Westfalen'],
            'DE-RP' => ['name' => 'Rheinland-Pfalz'],
            'DE-SL' => ['name' => 'Saarland'],
            'DE-SN' => ['name' => 'Sachsen'],
            'DE-ST' => ['name' => 'Sachsen-Anhalt'],
            'DE-SH' => ['name' => 'Schleswig-Holstein'],
            'DE-TH' => ['name' => 'Thueringen'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot-Kennung (Auskunftsseite /bot)
    |--------------------------------------------------------------------------
    |
    | Rest der Quell-Connectoren (#7-#11, entfernt mit #23): User-Agent und
    | Auskunftsseite des Crawlers bleiben, weil BotInfoController sie zeigt.
    |
    */

    'sources' => [
        'http' => [
            'user_agent_template' => 'SUN-ContentBot/1.0 (+:bot_url)',
            'bot_path' => 'bot',
            'timeout' => (int) env('CONTENT_SOURCES_HTTP_TIMEOUT', 15),
            'robots_cache_seconds' => 21600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Veroeffentlichung
    |--------------------------------------------------------------------------
    |
    | Die bestehende Artikel-Tabelle des Portals heisst `posts` und fuehrt
    | `author_id` als Pflichtfeld auf einen Benutzer der Central-DB. Das
    | Content-Panel schreibt nie den angemeldeten Administrator hinein,
    | deshalb steht der veroeffentlichende Benutzer hier.
    |
    */

    'publishing' => [
        'author_user_id' => (int) env('CONTENT_AUTHOR_USER_ID', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Freigabeschwellen der alten Einstellungsseite
    |--------------------------------------------------------------------------
    */

    'quality' => [
        // Gesamtscore 0-100, Vorgabe der alten Einstellungsseite.
        'auto_approve_score' => (int) env('CONTENT_AUTO_APPROVE_SCORE', 85),

        // Vorgabe fuer Mandanten mit tenant_content_settings.is_ymyl. Sie
        // gilt nur, solange der Mandant keine eigene Schwelle gepflegt hat
        // (tenant_content_settings.auto_publish_threshold, #15).
        'ymyl_auto_approve_score' => (int) env('CONTENT_YMYL_AUTO_APPROVE_SCORE', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel-Farben (Ticket #30)
    |--------------------------------------------------------------------------
    |
    | Benannte Filament-Farben des Content-Panels. Der ContentPanelProvider
    | registriert sie unveraendert:
    |
    |   FilamentColor::register(config('content.colors'));
    |
    | Warum benannte Farben statt der generischen Rollen: die Markenfarbe des
    | Panels ist Teal, `primary` waere also teal. Ein Status "In Erstellung" in
    | Markenfarbe verschmilzt mit Schaltflaechen, aktiver Navigation und
    | Fokusring. Ausserdem sind info/warning/success/danger frei belegbar und
    | nicht identisch mit den Tokens in resources/css/content/theme.css — ohne
    | eigene Namen driften Filament-Badge und .content-status auseinander.
    | Kein Status darf `primary` verwenden.
    |
    | Aufbau der Skalen (nicht frei aendern):
    |   Schattierung  50 = --color-status-*-bg
    |   Schattierung 600 = --color-status-*-fg
    | Filament waehlt die Textschattierung eines Badges selbst: die erste
    | Schattierung aufsteigend, die auf Schattierung 50 mindestens 4,5:1
    | erreicht. Die Skalen sind so gesetzt, dass 100-500 diese Schwelle
    | verfehlen und die Wahl deterministisch auf 600 faellt — genau auf den
    | fg-Token. Wer eine Schattierung <= 500 abdunkelt, bricht das.
    |
    | Aenderungen immer in dieser Reihenfolge: theme.css -> diese Tabelle ->
    | tailwind.content.config.cjs.
    |
    */

    'colors' => [
        // Markenfarbe des Panels. Auch als Rolle `primary` gesetzt (theme.css),
        // hier zusaetzlich unter eigenem Namen fuer ->color('content').
        'content' => [
            50 => '#eefbf8', 100 => '#d3f5ee', 200 => '#a9ebe0', 300 => '#72dbcd',
            400 => '#3cc2b3', 500 => '#17a698', 600 => '#0d857b', 700 => '#0d6a63',
            800 => '#105450', 900 => '#114643', 950 => '#032927',
        ],

        // status-idea — Themenvorschlag
        'status-idea' => [
            50 => '#f4f5f7', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1',
            400 => '#94a3b8', 500 => '#7b8794', 600 => '#475569', 700 => '#334155',
            800 => '#1e293b', 900 => '#0f172a', 950 => '#020617',
        ],

        // status-scheduled — Eingeplant
        'status-scheduled' => [
            50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd',
            400 => '#60a5fa', 500 => '#3b82f6', 600 => '#1d4ed8', 700 => '#1e40af',
            800 => '#1e3a8a', 900 => '#172554', 950 => '#0d1233',
        ],

        // status-generating — In Erstellung. Violett, bewusst NICHT
        // die Teal-Markenfarbe des Panels.
        'status-generating' => [
            50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd',
            400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#6d28d9', 700 => '#5b21b6',
            800 => '#4c1d95', 900 => '#3b1673', 950 => '#2e1065',
        ],

        // status-review — Zur Pruefung
        'status-review' => [
            50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d',
            400 => '#fbbf24', 500 => '#d97706', 600 => '#b45309', 700 => '#92400e',
            800 => '#78350f', 900 => '#5c2a0c', 950 => '#451a03',
        ],

        // status-published — Veroeffentlicht
        'status-published' => [
            50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7',
            400 => '#34d399', 500 => '#059669', 600 => '#047857', 700 => '#065f46',
            800 => '#064e3b', 900 => '#04412f', 950 => '#022c22',
        ],

        // status-failed — Fehlgeschlagen
        'status-failed' => [
            50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5',
            400 => '#f87171', 500 => '#ef4444', 600 => '#b91c1c', 700 => '#991b1b',
            800 => '#7f1d1d', 900 => '#641818', 950 => '#450a0a',
        ],

        // status-archived — Zurueckgezogen. Warmes Grau, damit es sich
        // von "Themenvorschlag" (kuehles Grau) unterscheiden laesst.
        'status-archived' => [
            50 => '#f8fafc', 100 => '#f1f5f9', 200 => '#e7e7e2', 300 => '#cbd5e1',
            400 => '#94a3b8', 500 => '#7c8a88', 600 => '#64716f', 700 => '#55605e',
            800 => '#3d4a48', 900 => '#2a3533', 950 => '#16211f',
        ],

        // Bauform-Namen des Ratgeber-Dashboards (design/guide-dashboard.md
        // §2.3), keine neuen Statusrollen. Eigene Namen nur, damit das Badge
        // dieselbe Sonderform bekommt wie .content-status--unchanged/--paused
        // (Punkt bzw. Pausenzeichen in theme.css).
        //
        // "Geprueft, unveraendert": Flaeche status-idea-bg, Text text-base
        // (#3d4a48), Punkt status-published-dot. 100-500 wie status-idea,
        // damit die Textwahl deterministisch auf 600 faellt.
        'status-unchanged' => [
            50 => '#f4f5f7', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1',
            400 => '#94a3b8', 500 => '#7b8794', 600 => '#3d4a48', 700 => '#2a3533',
            800 => '#1e293b', 900 => '#0f172a', 950 => '#020617',
        ],

        // "Pausiert": Toene exakt status-scheduled, Pausenzeichen statt Punkt.
        'status-paused' => [
            50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd',
            400 => '#60a5fa', 500 => '#3b82f6', 600 => '#1d4ed8', 700 => '#1e40af',
            800 => '#1e3a8a', 900 => '#172554', 950 => '#0d1233',
        ],
    ],

];
