<?php

declare(strict_types=1);

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;

/*
|--------------------------------------------------------------------------
| Cloudflare Turnstile (#1, Architektur in docs/turnstile.md)
|--------------------------------------------------------------------------
|
| Globale Vorgaben des Moduls app/Turnstile/. Je Portal ueberschreibt die
| Tenant-Konfiguration (tenants.data, Schluessel `turnstile`) einzelne Werte;
| App\Turnstile\Support\TenantTurnstileSettings legt beides uebereinander.
| Rangfolge: Tenant > diese Datei > .env-Vorgabe.
|
| WICHTIG: Sitekey und Secret kommen ausschliesslich aus der .env, hier ohne
| Vorgabewert — ein Literal als zweiter env()-Parameter ist ein eingecheckter
| Schluessel und wird von scripts/secret-scan.php abgewiesen. Der Secret wird
| nie an das Frontend ausgeliefert und steht nicht in der Datenbank.
| .env.example liefert die Cloudflare-Testschluessel (always pass); sie gelten
| auf jeder Domain inkl. localhost, sodass eine frische Installation laeuft,
| ohne ein Formular zu blockieren. Fehlt ein Schluessel, prueft die Rule nicht
| (VerificationOutcome::Skipped). Produktionsschluessel liegen im
| Passwort-Manager.
|
*/
return [

    /*
    |--------------------------------------------------------------------------
    | Schalter
    |--------------------------------------------------------------------------
    |
    | Globaler Notschalter des Moduls. Steht er auf false, prueft die Rule nicht
    | und die Blade-Komponente rendert nichts (VerificationOutcome::Skipped).
    | Der Rollout je Portal laeuft NICHT hierueber, sondern ueber den
    | Tenant-Schalter — siehe docs/turnstile.md, Abschnitt Rollout.
    |
    */

    'enabled' => (bool) env('TURNSTILE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Kontoanlage ueber Social Login (#6)
    |--------------------------------------------------------------------------
    |
    | Der OAuth-Callback kann kein Turnstile-Token tragen: er kommt vom
    | Anbieter, nicht aus einem Formular. Deshalb ist die Kontoanlage dort
    | gesperrt — bestehende Konten melden sich weiter an, eine unbekannte
    | E-Mail landet auf /register und damit am geschuetzten Formular.
    | Nur umstellen, wenn Social Login bewusst als Registrierungsweg
    | geoeffnet wird (dann ohne Turnstile, siehe docs/turnstile.md §11).
    |
    */

    'oauth_registration' => (bool) env('TURNSTILE_ALLOW_OAUTH_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Schluessel
    |--------------------------------------------------------------------------
    |
    | Ein Widget gilt fuer mehrere Portale; welches Portal welches Widget nutzt,
    | entscheidet die Gruppe weiter unten ueber den Hostnamen. Diese beiden
    | Werte sind Gruppe A und damit der Rueckfall fuer jeden Hostnamen, der in
    | keiner Gruppenliste steht.
    |
    | Ein einzelnes Portal kann sich darueber hinwegsetzen, indem es in
    | tenant_turnstile_settings eigene Werte hinterlegt (#9); der Secret liegt
    | dort verschluesselt. Regulaer bleibt das leer — Schluessel gehoeren in die
    | .env, nicht in die Datenbank.
    |
    */

    'site_key' => env('TURNSTILE_SITE_KEY'),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Widget-Gruppen (#14, docs/turnstile.md Abschnitt 8)
    |--------------------------------------------------------------------------
    |
    | Ein Cloudflare-Widget im Free-Plan traegt hoechstens 10 Hostnames, und ein
    | Token gilt nur fuer die Hostnames seines Widgets. Deshalb drei Widgets,
    | geschnitten nach der Rollout-Reihenfolge. Die Liste hier ist dieselbe wie
    | im Dashboard — weicht sie ab, scheitert der Hostname-Check (Abschnitt 3).
    |
    | `hostnames` traegt nur die eingetragenen Namen, keine Subdomains: wie bei
    | Cloudflare deckt ein Eintrag seine Subdomains mit ab, `www.` und
    | `apotheke.firmenfreund.de` laufen also ueber den Elterneintrag.
    |
    | Gruppe A ist zugleich die Vorgabe (site_key/secret_key oben) und greift
    | fuer jeden Hostnamen, der in keiner Liste steht — lokal und auf *.test
    | also die Testschluessel.
    |
    */

    'default_group' => 'A',

    'groups' => [

        'A' => [
            'name' => 'SUN Welle 1',
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
            'hostnames' => [
                'fahrschulefinder.de',
                'elektrikerportal.com',
                'sanitaerfinden.com',
            ],
        ],

        'B' => [
            'name' => 'SUN Welle 2',
            'site_key' => env('TURNSTILE_SITE_KEY_B'),
            'secret_key' => env('TURNSTILE_SECRET_KEY_B'),
            'hostnames' => [
                'sanitaerfinder.com',
                'malerfinder.de',
                'fliesenleger.io',
                'kfzwerkstatt.io',
                'findegutachter.de',
                'bodenlegerfinden.com',
                'tierarztportal.com',
                'energieberaterportal.net',
                'firmenfreund.net',
                // Deckt apotheke./arztfinder./klempner./unfallarzt./zahnarzt. mit ab.
                'firmenfreund.de',
            ],
        ],

        'C' => [
            'name' => 'SUN Welle 3',
            'site_key' => env('TURNSTILE_SITE_KEY_C'),
            'secret_key' => env('TURNSTILE_SECRET_KEY_C'),
            'hostnames' => [
                'geruestbauer.gmbh',
                'metallbauer.io',
                'mjet.net',
                'schluesseldienstportal.com',
                'speditionportal.com',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verhalten bei Stoerungen
    |--------------------------------------------------------------------------
    |
    | open   = Siteverify nicht erreichbar -> Anfrage laeuft weiter (Vorgabe).
    | closed = Siteverify nicht erreichbar -> Anfrage wird abgewiesen.
    |
    | Begruendung fuer open steht in docs/turnstile.md: ein Ausfall bei
    | Cloudflare darf nicht alle Registrierungen und Eintragungen aller Portale
    | stilllegen. Honeypot, Mindest-Ausfuellzeit und Rate-Limits (#8) greifen
    | weiter, und jeder Fall wird als VerificationOutcome::Error geloggt und
    | alarmiert (#12).
    |
    */

    'fail_mode' => env('TURNSTILE_FAIL_MODE', 'open'),

    // Harte Obergrenze fuer den Siteverify-Aufruf in Sekunden. Laeuft sie ab,
    // gilt der Fail-Mode. Cloudflare antwortet normal in weit unter 1 s.
    'timeout_seconds' => (int) env('TURNSTILE_TIMEOUT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker (#4)
    |--------------------------------------------------------------------------
    |
    | Faellt Siteverify aus, soll nicht jede Anfrage erst `timeout_seconds`
    | warten. Nach `threshold` Fehlern innerhalb von `window_seconds` geht der
    | Verifier fuer `cooldown_seconds` sofort in den Fail-Mode, ohne Cloudflare
    | noch anzurufen.
    |
    | `store` ist bewusst nicht der Standard-Store: in Produktion ist das
    | `database`, und im Tenant-Kontext zeigt die Standardverbindung auf die
    | Tenant-DB, die keine cache-Tabelle hat. Der file-Store wechselt je Portal
    | (App\Listeners\Tenant\SetTenantStorageUrl), der Breaker zaehlt also je
    | Portal — passend dazu, dass auch der Fail-Mode je Portal gilt.
    |
    */

    'circuit_breaker' => [
        'enabled' => (bool) env('TURNSTILE_BREAKER_ENABLED', true),
        'store' => env('TURNSTILE_BREAKER_STORE', 'file'),
        'threshold' => (int) env('TURNSTILE_BREAKER_THRESHOLD', 5),
        'window_seconds' => (int) env('TURNSTILE_BREAKER_WINDOW_SECONDS', 60),
        'cooldown_seconds' => (int) env('TURNSTILE_BREAKER_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pflichtpruefungen der Rule
    |--------------------------------------------------------------------------
    |
    | verify_hostname ist nur abschaltbar, um einen Zwischenfall zu entschaerfen
    | — regulaer bleibt es an. Weil ein Widget fuer mehrere Portale gilt, waere
    | ohne diesen Vergleich ein Token von Portal A auf Portal B einreichbar.
    | verify_action verhindert dasselbe zwischen zwei Formularen eines Portals.
    |
    */

    'verify_hostname' => (bool) env('TURNSTILE_VERIFY_HOSTNAME', true),
    'verify_action' => (bool) env('TURNSTILE_VERIFY_ACTION', true),

    /*
    |--------------------------------------------------------------------------
    | Aktionen
    |--------------------------------------------------------------------------
    |
    | Je Aktion: Vorgabe-Darstellung und ob sie ueberhaupt geprueft wird.
    | `enabled => false` heisst: Formular bleibt ungeschuetzt (Skipped). Die
    | Reihenfolge der Einfuehrung steht in docs/turnstile.md.
    |
    */

    'actions' => [

        TurnstileAction::Registration->value => [
            'mode' => env('TURNSTILE_MODE_REGISTRATION', TurnstileMode::Managed->value),
            'enabled' => true,
        ],

        TurnstileAction::CompanyListing->value => [
            'mode' => env('TURNSTILE_MODE_COMPANY_LISTING', TurnstileMode::Managed->value),
            'enabled' => true,
        ],

        // Angehaengt sind die Formulare seit #22 (Anfrage-Dialog, Bewertung,
        // Bewerbung, Newsletter, Korrekturvorschlag): Rule, Widget und
        // Tiefenverteidigung stehen im Code. Die Vorgabe bleibt bewusst
        // `false` — die Umsatzwege Anfrage und Bewerbung gehen erst mit dem
        // gestaffelten Rollout (#13) an, Portal fuer Portal ueber
        // tenant_turnstile_settings.actions_json im Admin (#9). Der Modus
        // steht auf non_interactive, damit kein echter Interessent und kein
        // Bewerber ein Puzzle loesen muss.
        TurnstileAction::LeadRequest->value => [
            'mode' => env('TURNSTILE_MODE_LEAD_REQUEST', TurnstileMode::NonInteractive->value),
            'enabled' => false,
        ],

        TurnstileAction::Contact->value => [
            'mode' => env('TURNSTILE_MODE_CONTACT', TurnstileMode::NonInteractive->value),
            'enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpunkte
    |--------------------------------------------------------------------------
    |
    | Konfigurierbar, damit Tests gegen einen eigenen Endpunkt laufen koennen
    | und eine Adressaenderung bei Cloudflare kein Code-Deployment braucht.
    | script_url gehoert in die CSP-Erlaubnis (#11).
    |
    */

    'script_url' => env('TURNSTILE_SCRIPT_URL', 'https://challenges.cloudflare.com/turnstile/v0/api.js'),
    'siteverify_url' => env('TURNSTILE_SITEVERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),

    /*
    |--------------------------------------------------------------------------
    | Verifikations-Log (#3, #12)
    |--------------------------------------------------------------------------
    */

    'log' => [
        // Jede Pruefung wird festgehalten (Ergebnis, Aktion, Hostname, Fehlercodes).
        'enabled' => (bool) env('TURNSTILE_LOG_ENABLED', true),

        // Aufbewahrung in Tagen; danach raeumt turnstile:prune (#12) ab.
        'retention_days' => (int) env('TURNSTILE_LOG_RETENTION_DAYS', 90),

        // Wie viele Zeilen turnstile:prune je Runde loescht. Das Log liegt in
        // der Tenant-DB auf rotierenden Platten; ein einzelnes grosses DELETE
        // haelt die Tabelle zu lange gesperrt.
        'prune_chunk' => (int) env('TURNSTILE_PRUNE_CHUNK', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alarme (#12)
    |--------------------------------------------------------------------------
    |
    | `turnstile:monitor` laeuft stuendlich, wertet je Portal die letzte Stunde
    | aus (App\Turnstile\Support\TurnstileStats::portal(), dieselbe
    | Aggregation wie das Widget) und schickt bei Auffaelligkeiten eine Mail.
    |
    | Hoechstens eine Mail je Portal, Alarmtyp und Stunde — gemerkt im Cache
    | unter `turnstile:alert:<tenant>:<typ>:<YmdH>`. Faellt der Cache aus, kann
    | eine Stunde doppelt melden; das ist der harmlosere Fehler als eine
    | verschluckte Meldung.
    |
    | Empfaenger: Komma-Liste in TURNSTILE_ALERT_RECIPIENTS. Bleibt sie leer,
    | gehen Alarme und Tagesbericht an alle Administratoren mit dem Recht
    | "update settings" (App\Turnstile\Support\BotProtectionRecipients) — also
    | an dieselben Personen, die die Bot-Schutz-Seiten im Panel sehen.
    |
    */

    'alerts' => [

        'enabled' => (bool) env('TURNSTILE_ALERTS_ENABLED', true),

        'recipients' => env('TURNSTILE_ALERT_RECIPIENTS'),

        /*
        | Cache-Store fuer die Entdopplung. null = Standard-Store. Der Monitor
        | laeuft zentral (ohne Tenancy), der file-Store zeigt dort also auf das
        | zentrale Verzeichnis und nicht in ein Portal.
        */
        'cache_store' => env('TURNSTILE_ALERT_CACHE_STORE'),

        /*
        | Fehlerquote: Anteil `error` an allen Pruefungen der letzten Stunde.
        | Ursache ist fast immer Siteverify selbst oder ein falsches Secret.
        | `min_checks` haelt stille Stunden draussen: 1 Fehler von 2 Pruefungen
        | sind 50 % und trotzdem kein Vorfall. Der zweite Alarm (error_count)
        | greift unabhaengig davon und faengt die echte Welle.
        */
        'error_share' => [
            'threshold' => (float) env('TURNSTILE_ALERT_ERROR_SHARE', 0.3),
            'min_checks' => (int) env('TURNSTILE_ALERT_ERROR_SHARE_MIN_CHECKS', 10),
        ],

        /*
        | Absolute Zahl an Fehlern in einer Stunde. Bei fail_mode=open lief das
        | Formular in dieser Zeit ohne Turnstile — darum ein eigener Alarm.
        */
        'error_count' => [
            'threshold' => (int) env('TURNSTILE_ALERT_ERROR_COUNT', 20),
        ],

        /*
        | Siteverify-Ausfall in Minuten statt Stunden (#19, Alarm SUN-TS-011).
        |
        | Der Listener App\Listeners\Turnstile\AlertOnSiteverifyUnreachable
        | zaehlt jeden Ausfall im Cache und meldet, sobald `threshold` davon
        | innerhalb von `window_seconds` zusammenkommen; danach erst wieder nach
        | `cooldown_seconds`. Ein einzelner Ausfall ist Rauschen, 20 in 5
        | Minuten sind ein Vorfall.
        |
        | Noetig neben `error_count` oben, weil `turnstile:monitor` nur
        | stuendlich laeuft: bei fail_mode=open ist das bis zu eine Stunde, in
        | der die Formulare ungeprueft durchlaufen.
        |
        | `store` wie beim Circuit Breaker bewusst `file` und nicht der
        | Standard-Store: der Listener laeuft im Portalkontext, dort zeigt der
        | file-Store in das Portal (SetTenantStorageUrl) und der Zaehler laeuft
        | damit je Portal — genau wie Schluessel und Fail-Mode.
        */
        'siteverify_unreachable' => [
            'threshold' => (int) env('TURNSTILE_ALERT_OUTAGE_THRESHOLD', 20),
            'window_seconds' => (int) env('TURNSTILE_ALERT_OUTAGE_WINDOW_SECONDS', 300),
            'cooldown_seconds' => (int) env('TURNSTILE_ALERT_OUTAGE_COOLDOWN_SECONDS', 1800),
            'store' => env('TURNSTILE_ALERT_OUTAGE_STORE', 'file'),
        ],

        /*
        | Blockierungsquote der Registrierung (action=registration): Anteil
        | `failed` an den Pruefungen der letzten Stunde.
        |
        | zu niedrig  -> das Formular wird umgangen (Token fehlt nie, nichts
        |                wird abgewiesen, obwohl Masse ankommt)
        | zu hoch     -> das Widget ist kaputt oder falsch konfiguriert, echte
        |                Nutzer kommen nicht durch
        |
        | Beide nur ab `min_registrations` Pruefungen in der Stunde; darunter
        | ist jede Quote Rauschen.
        */
        'block_share' => [
            'min_registrations' => (int) env('TURNSTILE_ALERT_BLOCK_MIN_REGISTRATIONS', 50),
            'low' => (float) env('TURNSTILE_ALERT_BLOCK_SHARE_LOW', 0.01),
            'high' => (float) env('TURNSTILE_ALERT_BLOCK_SHARE_HIGH', 0.95),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tagesbericht (#12)
    |--------------------------------------------------------------------------
    |
    | `turnstile:report` fasst den Vortag je Portal zusammen: Registrierungen,
    | Firmeneintraege, blockierte Pruefungen und neue Quarantaenefaelle (#10).
    | Empfaenger wie bei den Alarmen. `--no-mail` gibt den Bericht nur auf der
    | Konsole aus, `mail => false` schaltet den Versand dauerhaft ab.
    |
    */

    'report' => [
        'mail' => (bool) env('TURNSTILE_REPORT_MAIL', true),
    ],
];
