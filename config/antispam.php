<?php

declare(strict_types=1);

use App\AntiSpam\Detectors\BurstRegistrationDetector;
use App\AntiSpam\Detectors\DisposableDomainDetector;
use App\AntiSpam\Detectors\EmptyCompanyDataDetector;
use App\AntiSpam\Detectors\OwnerNameAsCompanyDetector;
use App\AntiSpam\Detectors\RandomNameDetector;
use App\AntiSpam\Detectors\UnverifiedAfterDaysDetector;

/*
|--------------------------------------------------------------------------
| Tiefenverteidigung gegen Formular-Missbrauch (#8, docs/turnstile.md §11)
|--------------------------------------------------------------------------
|
| Zweite Schutzschicht neben Cloudflare Turnstile. Weil Turnstile mit
| fail_mode=open laeuft (docs/turnstile.md §5), ist dieses Paket bei einem
| Ausfall von Siteverify die EINZIGE verbleibende Schicht — es darf deshalb
| nie von Cloudflare abhaengen und nie ueber das Netz gehen.
|
| Vier Mittel, strikt getrennt:
|
|   1. Honeypot        — unsichtbares Feld, Name je Session zufaellig
|   2. Mindest-Ausfuellzeit — verschluesselter Zeitstempel beim Rendern
|   3. Rate-Limits     — Laravel RateLimiter, Schluessel mit Portal-Praefix
|   4. Wegwerf-E-Mail  — gepflegte Domain-Liste im Repo + Tenant-Ergaenzungen
|
| Treffer bei 1 und 2 werden STILL abgewiesen: der Besucher sieht eine
| Erfolgsmeldung, es entsteht kein Datensatz. Ein Bot soll nicht lernen,
| woran er gescheitert ist. Treffer bei 3 und 4 sind sichtbar, weil sie
| echte Menschen treffen koennen (geteilte IP, Wegwerf-Adresse aus Versehen).
|
| Jeder Treffer landet in turnstile_verifications (Tenant-DB) mit
| outcome=failed und dem jeweiligen Code in error_codes_json — dieselbe
| Tabelle wie die Turnstile-Pruefungen, damit #9 und #12 nur eine Quelle
| auswerten muessen.
|
| Je Portal ueberschreibbar: tenant_turnstile_settings (`rate_limits_json`,
| `blocklist_domains_json`), gepflegt im Admin (#9). Die Werte werden rekursiv
| ueber diese Datei gelegt, siehe App\AntiSpam\Support\AntiSpamConfig — dort
| steht auch, welcher Admin-Wert welchen Schluessel hier ueberschreibt.
|
*/
return [

    /*
    |--------------------------------------------------------------------------
    | Schalter
    |--------------------------------------------------------------------------
    |
    | Globaler Notschalter. Steht er auf false, rendert <x-antispam-fields />
    | nichts, die Rules bestehen und die Limits zaehlen nicht. Nur fuer den
    | Zwischenfall — die Schicht ist billig und soll laufen.
    |
    */

    'enabled' => (bool) env('ANTISPAM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Honeypot
    |--------------------------------------------------------------------------
    |
    | Der Feldname wird je Session aus `field_names` gewaehlt und mit einem
    | Zufallssuffix versehen; ein Bot kann ihn also nicht hart verdrahten.
    | Die Namen sind absichtlich plausibel: ein Feld namens `honeypot` fuellt
    | niemand aus.
    |
    | Versteckt wird per CSS off-screen, NICHT display:none — ein Teil der
    | Bots prueft genau darauf und laesst solche Felder dann leer.
    |
    */

    'honeypot' => [
        'enabled' => true,

        /** @var list<string> Basisnamen; der wirksame Name bekommt ein Zufallssuffix. */
        'field_names' => [
            'website_url',
            'homepage',
            'company_url',
            'email_confirm',
            'phone_confirm',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mindest-Ausfuellzeit
    |--------------------------------------------------------------------------
    |
    | Beim Rendern wandert Crypt::encryptString(Zeitstempel) in ein Hidden-Feld
    | und wird serverseitig entschluesselt. Verschluesselt, damit der Wert nicht
    | manipulierbar ist; der Zeitstempel selbst ist kein Geheimnis, die
    | Signatur ist der Zweck.
    |
    | `min_seconds`: schneller abgeschickt heisst Bot. 3 s sind fuer ein
    | Formular mit Name, Mail und Passwort nicht zu schaffen.
    |
    | `max_seconds`: ein sehr alter Zeitstempel (Tab stand eine Nacht offen)
    | gilt als abgelaufen und NICHT als Treffer — sonst sperrt die Regel
    | Menschen aus, die sie schuetzen soll.
    |
    | `require_token`: fehlt das Feld ganz, zaehlt das als Treffer. Nur
    | abschalten, wenn eine Rule an einem Formular ohne
    | <x-antispam-fields /> haengt — dann ist aber das Formular falsch
    | gebaut, nicht diese Vorgabe.
    |
    */

    'timing' => [
        'enabled' => true,
        'min_seconds' => 3,
        'max_seconds' => 86400,
        'require_token' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate-Limits
    |--------------------------------------------------------------------------
    |
    | Registriert als benannte Limiter in App\Providers\AppServiceProvider
    | (App\AntiSpam\Support\RateLimitGuard::register()). Der Schluessel traegt
    | immer den Portal-Praefix, damit die Limits je Portal zaehlen und nicht
    | portaluebergreifend.
    |
    | Je Portal ueberschreiben `rate_limits_json` in tenant_turnstile_settings
    | die Werte fuer registration und company_listing; das Login-Limit hat im
    | Admin keine Entsprechung und gilt netzweit.
    |
    | Die IP wird nie im Klartext zum Schluessel: gezaehlt wird auf dem
    | HMAC-Hash (TurnstileVerification::hashIp()), derselbe Hash wie im Log.
    |
    | `max` 0 schaltet ein einzelnes Limit ab.
    |
    */

    'limits' => [

        // POST /register (zentral und je Portal)
        'registration' => [
            'ip' => ['max' => 5, 'minutes' => 60],
            'email' => ['max' => 3, 'minutes' => 1440],
        ],

        // App\Livewire\Portal\CompanySignup
        //
        // `user` greift nur fuer Angemeldete (Gaeste haben kein Signal, dann
        // zaehlt allein `ip`). Ohne dieses Limit war der Eintragsweg eines
        // Kontos ungebremst — in den Produktionsdaten stehen zehn Eintraege
        // desselben Nutzers in 23 Sekunden (#16, docs/turnstile.md §1.3 B).
        // Im Admin (#9) gibt es dafuer kein Feld, der Wert gilt netzweit.
        'company_listing' => [
            'ip' => ['max' => 10, 'minutes' => 60],
            'user' => ['max' => 5, 'minutes' => 60],
        ],

        // POST /login, Schluessel aus IP-Hash UND E-Mail
        'login' => [
            'ip_email' => ['max' => 10, 'minutes' => 1],
        ],

        /*
        | Die uebrigen oeffentlichen Schreibwege (#22). Sie liefen bisher
        | entweder ganz ohne Grenze (Anfrage, Newsletter) oder mit einer
        | handgeschriebenen (Bewerbung, Korrekturvorschlag) — die zaehlte auf
        | der Klartext-IP und portaluebergreifend. Im Admin (#9) haben diese
        | Abschnitte keine Entsprechung, die Werte gelten netzweit.
        */

        // App\Http\Controllers\Portal\ExclusiveLeadController
        //
        // Umsatzweg: absichtlich die weiteste Grenze. Ein Handwerker-Suchender
        // fragt mehrere Betriebe am selben Abend an, und hinter einer IP
        // koennen mehrere Haushalte stehen (Mobilfunk-NAT).
        'lead_request' => [
            'ip' => ['max' => 20, 'minutes' => 60],
        ],

        // App\Livewire\Portal\SubmitReviewForm
        //
        // Eine Bewertung je Betrieb und Sitzung sperrt die Komponente selbst;
        // dieses Limit deckt den Fall ab, dass jemand die Sitzung wegwirft.
        'review' => [
            'ip' => ['max' => 5, 'minutes' => 60],
        ],

        // App\Http\Controllers\Portal\PublicJobController::apply
        //
        // `email` greift breiter als die Dublettenpruefung des Controllers:
        // die gilt je Stelle, das Limit je Bewerber.
        'job_application' => [
            'ip' => ['max' => 5, 'minutes' => 60],
            'email' => ['max' => 10, 'minutes' => 1440],
        ],

        // App\Livewire\Portal\NewsletterSubscribeForm
        'newsletter' => [
            'ip' => ['max' => 5, 'minutes' => 60],
            'email' => ['max' => 3, 'minutes' => 1440],
        ],

        // App\Livewire\Portal\SuggestEditForm und SuggestEditModal
        'suggest_edit' => [
            'ip' => ['max' => 5, 'minutes' => 60],
        ],

        /*
        | Profiluebernahme "Ist das dein Betrieb?" (#26),
        | App\Livewire\Portal\ClaimModal und ClaimForm. Drei Wege, drei
        | Abschnitte: vorher zaehlte je Weg ein handgeschriebener Zaehler auf
        | der Klartext-IP und ohne Portal-Praefix. Im Admin (#9) haben diese
        | Abschnitte keine Entsprechung, die Werte gelten netzweit.
        */

        // Registrierungsreiter: legt ein Konto an, deshalb dieselben Grenzen
        // wie `registration`.
        'claim_registration' => [
            'ip' => ['max' => 5, 'minutes' => 60],
            'email' => ['max' => 3, 'minutes' => 1440],
        ],

        // Anmeldereiter: Passwortversuche, deshalb dieselbe enge Grenze wie
        // `login` (eigener Zaehler, damit der Reiter die zentrale Anmeldung
        // nicht mitsperrt).
        'claim_login' => [
            'ip_email' => ['max' => 10, 'minutes' => 1],
        ],

        // Uebernahme-Antrag des angemeldeten Wegs. `user` greift nur fuer
        // Angemeldete — und genau die sind hier der Regelfall.
        'claim' => [
            'ip' => ['max' => 10, 'minutes' => 60],
            'user' => ['max' => 5, 'minutes' => 60],
        ],

        // Einspruch gegen eine fremde Uebernahme: selten, deshalb eng.
        'claim_dispute' => [
            'ip' => ['max' => 3, 'minutes' => 60],
        ],

        // Nachweis-Upload nach der Uebernahme (#27),
        // App\Livewire\Portal\ClaimVerification. Angemeldeter Weg mit
        // offenem Antrag, deshalb kein Captcha und ein weiter Rahmen: ein
        // Nachreichen abgelehnter Unterlagen darf nicht am Limit haengen.
        'claim_upload' => [
            'ip' => ['max' => 10, 'minutes' => 60],
            'user' => ['max' => 10, 'minutes' => 60],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wegwerf-E-Mail-Adressen
    |--------------------------------------------------------------------------
    |
    | Liste im Repo: resources/antispam/disposable-domains.txt, eine Domain je
    | Zeile, `#` ist Kommentar, `*` als Platzhalter erlaubt (tempmail.* deckt
    | tempmail.de, tempmail.net ... ab). Gepflegt mit `antispam:refresh-domains`.
    |
    | Portal-Ergaenzungen stehen in tenant_turnstile_settings.
    | blocklist_domains_json und werden im Admin gepflegt (#9).
    | `allowed_domains` hier nimmt eine Domain wieder heraus, falls eine Liste
    | zu grob ist — das gilt netzweit und hat absichtlich kein Admin-Feld.
    |
    | `mx_check` ist optional und standardmaessig AUS: er geht ueber DNS,
    | kostet Zeit im Request und schlaegt bei langsamen Resolvern falsch an.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ausnahmen von der Sperrliste
    |--------------------------------------------------------------------------
    |
    | Domains, die trotz Treffer auf der Sperrliste zugelassen werden. Gilt
    | netzweit und schlaegt die Datei wie auch die Portal-Ergaenzungen. Gedacht
    | fuer den Fall, dass eine fremde Liste eine Domain zu Unrecht fuehrt.
    |
    */

    'allowed_domains' => [],

    /*
    |--------------------------------------------------------------------------
    | Maschineller GET-Verkehr (#17, docs/bot-traffic.md)
    |--------------------------------------------------------------------------
    |
    | Gilt NICHT fuer Formulare — die deckt Turnstile ab. Hier geht es um
    | Seitenabrufe: 530.152 Ereignisse in 30 Tagen auf elektrikerportal.com,
    | davon rund 175.000 nachweisbar maschinell (docs/turnstile.md §1.3 C).
    |
    | Diese Liste SPERRT nichts. Ein Treffer bedeutet: der Aufruf zaehlt nicht
    | in der Statistik eines Betriebs (tracking_events, company_events, #9).
    | Das Abweisen gehoert in die Cloudflare-WAF, siehe docs/bot-traffic.md §2;
    | eine App-Sperre wuerde Suchmaschinen und die IndexNow-Wege aus
    | docs/guide-golive.md §1.4 mittreffen.
    |
    | Gelesen wird die Liste ausschliesslich von
    | App\AntiSpam\Support\BotTraffic — dort haengen TrackingService und
    | App\Services\Premium\CompanyStatsRecorder dran. Vorher gab es zwei
    | Listen mit zwei Staenden.
    |
    | `user_agents`: Teilstrings, klein geschrieben. Absichtlich grob — ein
    | falscher Treffer kostet eine Zeile in der Statistik, ein Durchrutscher
    | verfaelscht sie.
    |
    | `bare_user_agents`: exakte Werte. Ein nackter `Mozilla/5.0` ohne Produkt,
    | Plattform und Engine kommt von keinem Browser (11.241 Ereignisse aus 48
    | Netzen).
    |
    | `ip_prefixes`: CIDR, IPv4 und IPv6. Fuer Scraper, die sich als Browser
    | ausgeben und nur am Netz und am Abrufmuster zu erkennen sind. Die
    | Alibaba-Netze 47.79.200.0-47.79.207.255 riefen je /24 zwischen 9.000 und
    | 10.500 verschiedene Firmenprofile ab. Geprueft wird die volle Adresse aus
    | dem Request, nicht die auf /24 gekuerzte Spalte in tracking_events.
    |
    */

    'bot_traffic' => [
        'enabled' => true,

        /** @var list<string> Teilstrings im User-Agent, klein geschrieben. */
        'user_agents' => [
            // generisch
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'adsbot',
            'facebookexternalhit', 'linkedinbot', 'twitterbot', 'embedly',
            'whatsapp', 'telegram', 'preview', 'headless', 'phantomjs',
            'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
            'statuscake', 'monitor', 'scrapy',

            // Bibliotheken und Werkzeuge
            'python-requests', 'python-urllib', 'python-httpx', 'httpx/',
            'aiohttp', 'requests/', 'curl/', 'wget/', 'libwww', 'lwp::',
            'go-http-client', 'java/', 'jakarta', 'apache-httpclient',
            'httpclient', 'okhttp', 'axios/', 'node-fetch', 'got/', 'undici',
            'guzzle', 'php/', 'ruby/', 'perl', 'postmanruntime', 'insomnia',
            'restsharp', 'winhttp', 'powershell', 'zgrab', 'masscan',

            // namentlich belegt, docs/turnstile.md §1.3 C
            'dataquality', 'leadresearch', 'lead-research', 'datacollection',
            '-probe/', 'authorized-probe', 'webapp-mapper', 'google-apps-script',
            'beanserver', 'apps-script', 'dataprovider', 'barkrowler',
            'serpstat',
        ],

        /** @var list<string> Exakte User-Agents, die kein Browser sendet. */
        'bare_user_agents' => [
            'mozilla/5.0',
            'mozilla/4.0',
            'mozilla',
            'mozilla/5.0 (compatible)',
            'opera',
            'unknown',
            'null',
        ],

        /** @var list<string> Netze in CIDR-Schreibweise. */
        'ip_prefixes' => [
            // Alibaba Cloud (Frankfurt), Scraper der Firmenprofile
            '47.79.200.0/21',
        ],
    ],

    'disposable' => [
        'enabled' => true,
        'list_path' => 'antispam/disposable-domains.txt',
        'mx_check' => (bool) env('ANTISPAM_MX_CHECK', false),

        // Quelle fuer antispam:refresh-domains. Ohne Schluessel, oeffentlich.
        'refresh_url' => 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Herkunft mitschreiben (§1.4: fehlte bisher ganz)
    |--------------------------------------------------------------------------
    |
    | IP-Hash und User-Agent beim Anlegen von Konto und Firmeneintrag.
    | users.registration_ip_hash / registration_user_agent,
    | companies.created_ip_hash / created_user_agent. Immer der HMAC-Hash,
    | nie die Adresse — damit die Spalte keine personenbezogene Vorratsdaten
    | ist und trotzdem Herkunftsvergleiche in #10 erlaubt.
    |
    */

    'origin_logging' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bestandsbereinigung: Quarantaene verdaechtiger Konten und Eintraege (#10)
    |--------------------------------------------------------------------------
    |
    | Grundlage sind die Erkennungsregeln aus docs/turnstile.md §7. Jede Regel
    | ist eine Klasse unter app/AntiSpam/Detectors/ und steht unten mit ihrem
    | Gewicht; ein neues Muster braucht genau eine Zeile hier und keine
    | Aenderung an `antispam:scan`.
    |
    | Gewertet wird additiv, gedeckelt auf 100. Die Gewichte sind so gewaehlt,
    | dass §7 eingehalten wird: eine EINZELNE Regel reicht nur bei R1
    | (Firmenname ist der Name des Inhabers) — alle uebrigen brauchen eine
    | zweite Regel, um die Schwelle zu erreichen.
    |
    | Ein Treffer loescht nichts. Er setzt `suspected_bot_at`, legt einen
    | Eintrag still (is_active = 0, dieselbe Stellung wie "pending") und sperrt
    | ein Konto (is_blocked = 1). Beides ist umkehrbar — "Freigeben" im
    | Admin stellt den vorherigen Zustand wieder her und nimmt den Datensatz
    | dauerhaft aus der Erkennung (`suspected_bot_cleared_at`).
    |
    | Erst `antispam:expire-quarantine` setzt nach `quarantine_days` ein
    | `deleted_at` — Soft-Delete, nie ein hartes Loeschen.
    |
    */

    'suspected_bots' => [

        /*
        | Ab diesem Wert (0..100) gilt ein Datensatz als sichtungswuerdig.
        | Ueberschreibbar je Lauf mit `antispam:scan --threshold=`.
        */
        'threshold' => (int) env('ANTISPAM_BOT_THRESHOLD', 70),

        /*
        | Tage ohne Double-Opt-in, ab denen ein Konto auffaellt (R3/R4).
        */
        'unverified_after_days' => 7,

        /*
        | Tage in Quarantaene bis zum Soft-Delete (R-Frist aus dem Ticket).
        | Gezaehlt ab `suspected_bot_at`, nur fuer Datensaetze ohne Freigabe.
        */
        'quarantine_days' => 14,

        /*
        | Serien aus derselben Herkunft (R9). Verglichen wird der HMAC-Hash
        | der IP, nie die Adresse; Klartext-IPs gibt es im Bestand nicht.
        | `same_minute` zaehlt Anmeldungen derselben Minute unabhaengig von
        | der Herkunft — das Muster einer Welle, nicht einer Person.
        */
        'burst' => [
            'window_minutes' => 60,
            'min_same_ip' => 5,
            'min_same_minute' => 3,
            'min_same_domain' => 5,
        ],

        /*
        | Regeln und ihr Gewicht. Reihenfolge ist nur Darstellung.
        */
        'detectors' => [
            OwnerNameAsCompanyDetector::class => 70,
            DisposableDomainDetector::class => 50,
            RandomNameDetector::class => 45,
            BurstRegistrationDetector::class => 40,
            UnverifiedAfterDaysDetector::class => 35,
            EmptyCompanyDataDetector::class => 35,
        ],
    ],

];
