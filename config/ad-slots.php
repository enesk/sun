<?php

declare(strict_types=1);

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Enums\AdProviderType;
use App\Ads\Providers\AdSenseProvider;
use App\Ads\Providers\HtmlProvider;

/*
|--------------------------------------------------------------------------
| Werbeplatz-Katalog v1 (Theme sun-v2)
|--------------------------------------------------------------------------
|
| Der Katalog ist die einzige Quelle fuer die Frage, WO Werbung erscheinen
| darf. Welche Werbung dort laeuft, steht in der Central-DB (ad_units,
| ad_placements, Pivot je Portal) — nie hier. Beschreibung der Regeln,
| des Datenmodells und des Editor-Modus: docs/ad-slots.md.
|
| Ein Anker ist nur dann zulaessig, wenn er zwischen zwei abgeschlossenen
| Sektionen oder zwischen zwei Elementen einer Liste sitzt. Nie innerhalb
| einer Karte, eines Grids mit mehreren Spalten oder eines Flex-Containers
| mit Geschwistern in derselben Zeile.
|
| Schluessel sind stabil: sie landen in der Datenbank. Ein Anker wird nie
| umbenannt, nur aufgenommen oder (mit Migration) entfernt; `version` wird
| bei jeder inhaltlichen Aenderung hochgezaehlt.
|
| Routen-Dimension: ein Seitentyp buendelt alle Routen mit demselben
| Seitenaufbau (AdPageType::routes()). Steht ein Anker nur in einem Teil
| dieser Routen, fuehrt der Slot `routes` — die Liste der Routennamen, auf
| denen er gerendert wird. Fehlt der Schluessel, gilt er fuer alle Routen des
| Seitentyps. Beschriftungen der Routen stehen in `route_labels` (Ticket #27).
|
| Theme-Dimension: ein Anker existiert nur in dem Theme, in dessen Views er
| eingebaut ist. Jeder Slot fuehrt darum `themes` — die Liste der Theme-Slugs,
| in denen sein Anker steht. Fehlt der Schluessel, gilt `defaults.themes`.
| Ein Portal bekommt nur die Anker seines aktiven Themes angeboten
| (`theme.active` am Tenant, Vorgabe `default`), nie den ganzen Katalog;
| sonst waere eine Position waehlbar und stumm (Ticket #14).
| Baut ein Ticket den Anker in ein weiteres Theme ein, gehoert der Slug hier
| in `themes` dazu — und `version` wird hochgezaehlt.
|
| Mindesthoehen: die Reservierung steht schon im HTML — sonst springt das
| Layout beim Nachladen (CLS). Reserviert wird `min-height` und nicht
| `height`, damit ein fluides Format (`in_feed`, `in_article`) nicht
| abgeschnitten wird. Bei `responsive` haengt die Hoehe des Werbemittels
| selbst an dieser Zahl: der AdSenseProvider setzt sie in den `style` des
| <ins> (Ticket #32).
|
*/

return [

    /*
     * Katalogversion. Steht im Cache-Schluessel des Resolvers und in der
     * Antwort von slots:ready an den Editor; eine Erhoehung macht alle
     * zwischengespeicherten Aufloesungen ungueltig.
     *
     * v2: Theme-Dimension (`themes`) je Slot ergaenzt (Ticket #14).
     * v3: zweiter Seitenspalten-Anker auf dem Firmenprofil
     *     (`company_sidebar_top`, Ticket #19).
     * v4: Routen-Dimension (`routes`) je Slot ergaenzt (Ticket #27).
     */
    'version' => 4,

    /*
     * AdSense-Vorgabe des Netzes. Ein Portal darf eine eigene Publisher-ID
     * hinterlegen (Tabelle `ad_settings`, Ticket #3/#10); ist sie leer, gilt
     * dieser Wert. Ohne beides wird kein AdSense-Werbemittel ausgespielt.
     */
    'adsense' => [
        'publisher_id' => env('ADSENSE_PUBLISHER_ID'),

        /*
         * AdSense Auto Ads (Ticket #18). Kein Anker im Katalog: das Skript
         * platziert selbst, ausserhalb jeder Reservierung (docs/ad-slots.md 1).
         * Eingeschaltet wird je Portal in `ad_settings.adsense_auto_ads`,
         * ausgespielt von App\View\Components\Sun\AutoAds.
         */
        'auto_ads' => [
            /*
             * Routen ohne Auto Ads. Uebernommen aus dem Altsystem
             * (App\View\Components\AdSlot::AUTO_ADS_BLOCKED_ROUTES): der
             * untere Anker-Banner setzt ein padding-bottom auf das <body> und
             * treibt CLS auf den Ratgeber-Seiten ueber 0,1
             * (docs/messungen/auto-ads-cls-messung-2026-09-09.md, Vorgabe #100).
             */
            'blocked_routes' => [
                'guide.index',
                'guide.category',
                'guide.show',
                'guide.preview',
                'portal.blog.search',
                'portal.blog.editorial',
                'portal.blog.tag',
            ],
        ],
    ],

    /*
     * Editor-Modus (Ticket #6, docs/ad-slots.md 6).
     *
     * `editor_origin` ist die Herkunft, mit der die postMessage-Bruecke
     * spricht — die zentrale Domain, auf der das Filament-Panel laeuft.
     * Beide Seiten geben sie als `targetOrigin` an und pruefen `event.origin`
     * dagegen; `'*'` kommt nicht vor. Ohne Eintrag gilt APP_URL, weil das
     * Panel dort liegt.
     *
     * `editor_token_minutes` ist die Gueltigkeit des signierten Tokens
     * ({@see App\Ads\EditorToken}). Kurz, weil eine signierte URL nicht
     * geheim ist: sie steht in der iframe-Adresse und im Verlauf.
     */
    'editor_origin' => env('ADS_EDITOR_ORIGIN', env('APP_URL')),

    'editor_token_minutes' => 30,

    /*
     * Dauer, die eine aufgeloeste Seitentyp-Karte im Cache liegt (Sekunden).
     * Steht hier und nicht im Resolver, damit der Rollout (#11) sie auf einem
     * Pilot-Portal kurz stellen kann. Geleert wird ohnehin bei jeder
     * Aenderung (App\Ads\Observers\AdCacheInvalidator).
     */
    'cache_ttl' => 3600,

    /*
     * Einwilligung (SUN-AD-009, Ticket #9, docs/ad-slots.md 9).
     *
     * Ein Werbemittel, dessen Provider `requiresConsent()` mit true
     * beantwortet (AdSense), laedt erst nach der Einwilligung in
     * Marketing-Cookies. Bis dahin steht der reservierte Container in
     * Katalog-Hoehe mit der Zeile "Anzeige" — und nichts darin: das Fragment
     * liegt in einem <template>, der Loader in einem zweiten. Beides ist
     * inert, es geht kein Request an Google (Akzeptanz 1).
     *
     * `cookie` ist der Name, den beide Einwilligungs-Hinweise im Browser
     * setzen (resources/js/app.js und themes/sun-v2/js/modules/cookie-consent.js).
     * Er steht in der Ausnahmeliste von EncryptCookies (bootstrap/app.php, #21) —
     * ohne die liefert request()->cookie() immer null.
     *
     * `event` ist das Ereignis, mit dem resources/js/consent-adapter.js die
     * Entscheidung im laufenden Besuch meldet. Daran haengt das Nachziehen
     * ohne Reload (Akzeptanz 2). Ein Wechsel des Einwilligungs-Werkzeugs
     * betrifft nur den Adapter und diesen Schluessel.
     *
     * `on_denied` entscheidet, was mit dem reservierten Platz passiert, wenn
     * die Einwilligung ausdruecklich VERWEIGERT wurde (Cookie = '0'):
     *
     *   reserve   Der Container bleibt in Katalog-Hoehe stehen.
     *   collapse  Der Container klappt ein (Vorgabe): wer Werbung ablehnt,
     *             soll keine dauerhaft leere graue Flaeche sehen. Die
     *             Entscheidung ist endgueltig bis zum Widerruf, es gibt also
     *             nichts mehr, wofuer Platz zu halten waere.
     *
     * Ohne Entscheidung (kein Cookie) gilt `on_denied` nicht — da wird
     * immer reserviert, denn die Einwilligung kann noch im selben Besuch
     * kommen und der Platz darf dann nicht wachsen (CLS).
     */
    'consent' => [
        'cookie' => 'cookie_consent_marketing',
        'event' => 'consent:marketing',
        'on_denied' => env('ADS_CONSENT_ON_DENIED', 'collapse'),
    ],

    /*
     * Eigenes Impression-/Viewability-Zaehlen (Ticket #12, docs/ad-slots.md 14).
     *
     * `false` schaltet es vollstaendig ab: die Komponente gibt dann kein
     * `data-ad-placement` aus, resources/js/ads.js richtet keinen Beobachter
     * ein, es geht kein Beacon los und der stuendliche Job verdichtet nichts.
     * Die Tabellen bleiben stehen — Abschalten ist kein Datenverlust.
     *
     * Gezaehlt wird provider-unabhaengig und damit auch fuer
     * Direktbuchungen ueber den HtmlProvider, fuer die es kein
     * AdSense-Reporting gibt.
     */
    'impression_tracking' => env('ADS_IMPRESSION_TRACKING', true),

    /*
     * Feineinstellungen des Zaehlens. Stehen bewusst nicht im JavaScript:
     * die Schwelle ist eine Messentscheidung und muss ohne Neubau der Assets
     * aenderbar sein (die Werte gehen als data-Attribute ins Markup).
     *
     * `threshold`/`dwell_ms` sind die MRC-Vorgabe fuer Display-Werbung:
     * 50 % der Flaeche, 1 Sekunde zusammenhaengend. Genauso zaehlt AdSense,
     * damit beide Zahlen vergleichbar bleiben.
     *
     * `max_entries` ist die Obergrenze der Meldungen in EINEM Beacon. Ein
     * Seitenaufruf hat hoechstens eine Handvoll belegte Anker; alles darueber
     * ist Muell und wird abgeschnitten, nicht abgelehnt.
     *
     * `retention_days` gilt fuer `ad_impression_daily`. 400 Tage, damit ein
     * Jahresvergleich moeglich bleibt — es sind Aggregate ohne Personenbezug,
     * Rohdaten entstehen ohnehin nie.
     */
    'impressions' => [
        'threshold' => 0.5,
        'dwell_ms' => 1000,
        'max_entries' => 20,
        'retention_days' => 400,
    ],

    /*
     * Registrierte Provider: Schluessel = Wert in `ad_units.provider`,
     * Wert = Klasse, die App\Ads\Contracts\AdProvider implementiert.
     *
     * Ein neuer Provider ist eine Klasse plus eine Zeile hier — Models,
     * Resolver, Komponente und Editor fassen ihn nie an, sie fragen
     * ausschliesslich die AdProviderRegistry. Beispiel in docs/ad-slots.md 5.3.
     */
    'providers' => [
        AdProviderType::ADSENSE->value => AdSenseProvider::class,
        AdProviderType::HTML->value => HtmlProvider::class,
    ],

    /*
     * Vorgaben fuer Inhalts-Anker. Ein Slot ueberschreibt sie nur, wenn er
     * eine andere Form hat (Banner, Seitenspalte).
     */
    'defaults' => [
        'min_height_mobile' => 280,
        'min_height_desktop' => 250,

        /*
         * Themes, in denen die Anker von Katalog v1 eingebaut werden (#5).
         * Die 32 Anker sind gegen das Markup von sun-v2 geschnitten; in
         * `default` und `starter` gibt es sie nicht. Welches Portal auf
         * sun-v2 umgestellt wird bzw. wo die Anker nachgezogen werden, ist
         * eine Produktentscheidung (siehe Ticket #14) — bis sie faellt
         * bleibt die Liste hier die Wahrheit, und Portale mit anderem Theme
         * bekommen keine Anker angeboten statt stummer Positionen.
         */
        'themes' => ['sun-v2'],
    ],

    /*
     * Beschriftungen der Routen (Ticket #27). Gebraucht, wo eine einzelne
     * Route benannt werden muss: die Routenwahl der Vorschau (#7) und der
     * Zusatz "nur auf …" an einem Anker, der nicht auf jeder Route des
     * Seitentyps steht. Fehlt ein Eintrag, zeigt die Oberflaeche den
     * Routennamen.
     */
    'route_labels' => [
        'home' => 'Startseite',
        'portal.companies.index' => 'Firmensuche',
        'portal.companies.show' => 'Firmenprofil',
        'portal.companies.show.city' => 'Firmenprofil mit Stadt im Pfad',
        'portal.cities.show' => 'Stadtseite',
        'guide.show' => 'Ratgeber-Artikel',
        'guide.index' => 'Ratgeber-Übersicht /ratgeber',
        'guide.category' => 'Ratgeber-Kategorie /ratgeber/kategorie/…',
    ],

    /*
     * Erlaubte Formate. `responsive` ist das AdSense-Standardformat,
     * `in_feed` und `in_article` sind die AdSense-Varianten fuer Listen
     * bzw. Textfluss; die festen Groessen gelten auch fuer eigenes HTML.
     *
     * `responsive` bleibt auf **jedem** Anker zulaessig, auch auf den
     * Bannern mit 90/100 px (Ticket #32, Weg 2): der AdSenseProvider gibt
     * dem <ins> die Hoehe der Reservierung mit und keine Format-Angabe,
     * damit AdSense die Hoehe nicht selbst bestimmt. Vorher sprengte
     * `data-ad-format="auto"` die Reservierung eines Banner-Ankers — CLS
     * 0,136 (docs/messungen/ads-cls-responsive-2026-10-16.md).
     */
    'formats' => [
        'responsive' => ['label' => 'Responsiv', 'width' => null, 'height' => null],
        'rectangle_300x250' => ['label' => 'Rechteck 300×250', 'width' => 300, 'height' => 250],
        'leaderboard_728x90' => ['label' => 'Banner 728×90', 'width' => 728, 'height' => 90],
        'in_feed' => ['label' => 'In-Feed (Listenanzeige)', 'width' => null, 'height' => null],
        'in_article' => ['label' => 'In-Article (Textanzeige)', 'width' => null, 'height' => null],
    ],

    /*
     * Der Katalog. Die Reihenfolge im Editor ergibt sich aus page_type und
     * `sort`, nicht aus der Reihenfolge in diesem Array.
     */
    'slots' => [

        // ---------------------------------------------------------------
        // Startseite — pages/home.blade.php
        // ---------------------------------------------------------------
        [
            'key' => 'home_after_hero',
            'label' => 'Startseite · unter dem Suchbereich',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 10,
        ],
        [
            'key' => 'home_after_services',
            'label' => 'Startseite · nach den Leistungen',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        [
            'key' => 'home_after_top_rated',
            'label' => 'Startseite · nach den Top-Betrieben',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 30,
        ],
        [
            'key' => 'home_after_guide',
            'label' => 'Startseite · nach den Ratgeber-Artikeln',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 40,
        ],
        [
            'key' => 'home_after_cities',
            'label' => 'Startseite · nach den Städten',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 50,
        ],
        [
            'key' => 'home_before_footer',
            'label' => 'Startseite · über der Fußzeile',
            'page_type' => AdPageType::HOME->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 60,
        ],

        // ---------------------------------------------------------------
        // Firmensuche — pages/companies/index.blade.php
        // ---------------------------------------------------------------
        [
            'key' => 'search_above_results',
            'label' => 'Suche · über der Ergebnisliste',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 10,
        ],
        [
            'key' => 'search_in_feed_3',
            'label' => 'Suche · nach dem 3. Ergebnis',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_feed', 'responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        [
            'key' => 'search_in_feed_8',
            'label' => 'Suche · nach dem 8. Ergebnis',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_feed', 'responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 30,
        ],
        [
            'key' => 'search_sidebar_sticky',
            'label' => 'Suche · Seitenspalte, klebend',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 600,
            'sort' => 40,
        ],
        [
            'key' => 'search_after_pagination',
            'label' => 'Suche · unter der Blätterleiste',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 50,
        ],
        [
            'key' => 'search_before_footer',
            'label' => 'Suche · über der Fußzeile',
            'page_type' => AdPageType::SEARCH->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 60,
        ],

        // ---------------------------------------------------------------
        // Firmenprofil — pages/companies/show.blade.php
        // ---------------------------------------------------------------
        [
            'key' => 'company_after_header_card',
            'label' => 'Profil · unter der Kopf-Karte (mobil)',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::MOBILE->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 0,
            'sort' => 10,
        ],
        [
            'key' => 'company_after_about',
            'label' => 'Profil · nach "Über uns"',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        [
            'key' => 'company_after_reviews',
            'label' => 'Profil · nach den Bewertungen',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 30,
        ],
        [
            /*
             * Zweiter Anker in der Seitenspalte (Ticket #19). Das Altsystem
             * pflegt auf dem Profil zwei Seitenspalten-Positionen
             * (`sidebar_top` und `sidebar_after_claim`, je 16 aktive Slots);
             * mit nur einem Anker im Katalog waeren 16 Plaetze beim Umstieg
             * weggefallen, weil der Resolver je Anker und Geraet nur eine
             * Belegung ausspielt. Vorbild ist der Ratgeber, der mit
             * `guide_sidebar_top` + `guide_sidebar_sticky` dieselbe Form hat.
             */
            'key' => 'company_sidebar_top',
            'label' => 'Profil · Seitenspalte, oben',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 250,
            'sort' => 35,
        ],
        [
            'key' => 'company_sidebar_sticky',
            'label' => 'Profil · Seitenspalte, klebend',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 600,
            'sort' => 40,
        ],
        [
            'key' => 'company_before_footer',
            'label' => 'Profil · über der Fußzeile',
            'page_type' => AdPageType::COMPANY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 50,
        ],

        // ---------------------------------------------------------------
        // Stadtseite — pages/cities/show.blade.php
        // ---------------------------------------------------------------
        [
            'key' => 'city_above_results',
            'label' => 'Stadtseite · über der Ergebnisliste',
            'page_type' => AdPageType::CITY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 10,
        ],
        [
            'key' => 'city_in_feed_3',
            'label' => 'Stadtseite · nach dem 3. Ergebnis',
            'page_type' => AdPageType::CITY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_feed', 'responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        [
            'key' => 'city_after_pagination',
            'label' => 'Stadtseite · unter der Blätterleiste',
            'page_type' => AdPageType::CITY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 30,
        ],
        [
            'key' => 'city_before_footer',
            'label' => 'Stadtseite · über der Fußzeile',
            'page_type' => AdPageType::CITY->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 40,
        ],

        // ---------------------------------------------------------------
        // Ratgeber-Artikel — guide/show.blade.php
        // Vor Block 5 (Key-Facts) nie eine Anzeige, und nie direkt vor
        // oder nach der Verweisleiste Firmensuche (Block 7).
        // ---------------------------------------------------------------
        [
            'key' => 'guide_after_key_facts',
            'label' => 'Ratgeber · nach den Kernaussagen',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 10,
        ],
        [
            'key' => 'guide_in_article_1',
            'label' => 'Ratgeber · im Text, nach dem 3. Absatz',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_article', 'responsive'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        [
            'key' => 'guide_in_article_2',
            'label' => 'Ratgeber · im Text, nach dem 8. Absatz',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_article', 'responsive'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 30,
        ],
        [
            'key' => 'guide_sidebar_top',
            'label' => 'Ratgeber · Seitenspalte, oben',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 250,
            'sort' => 40,
        ],
        [
            'key' => 'guide_sidebar_sticky',
            'label' => 'Ratgeber · Seitenspalte, klebend',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 600,
            'sort' => 50,
        ],
        [
            'key' => 'guide_before_footer',
            'label' => 'Ratgeber · über der Fußzeile',
            'page_type' => AdPageType::GUIDE->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 60,
        ],

        // ---------------------------------------------------------------
        // Ratgeber-Übersicht und -Kategorie
        // guide/index.blade.php, guide/category.blade.php
        // und die beiden Rückfall-Views pages/blog/index.blade.php und
        // pages/blog/category.blade.php: GuideController gibt an den
        // PublicBlogController ab, solange ein Portal keine Ratgeber-
        // Kategorien veröffentlicht hat. Der Routenname bleibt
        // `guide.index`/`guide.category`, der Seitentyp also `guide_index` —
        // die Anker stehen deshalb in allen vier Views (Ticket #23).
        //
        // Nicht jeder Anker dieses Seitentyps steht in jeder der vier Views.
        // Was sich an der Route festmachen lässt, steht seit #27 als `routes`
        // am Slot; der Rückfall auf die Blog-Views ist keine Route, sondern
        // eine Entscheidung im Controller und bleibt darum ungedeckt.
        // Stand #27:
        //
        //   after_first_block  alle vier Views            → alle Routen
        //   in_feed_5          guide/category + beide
        //                      Rückfall-Views             → alle Routen
        //                      (die Rückfall-Übersicht läuft auf
        //                      `guide.index`, eine Einschränkung auf
        //                      `guide.category` wäre dort falsch)
        //   sidebar_sticky     nur guide/category         → `guide.category`
        //   before_footer      alle vier Views            → alle Routen
        // ---------------------------------------------------------------
        [
            'key' => 'guide_index_after_first_block',
            'label' => 'Ratgeber-Übersicht · nach dem ersten Themenblock',
            'page_type' => AdPageType::GUIDE_INDEX->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 10,
        ],
        /*
         * Steht in guide/category.blade.php (einspaltige Themenliste) und in
         * den beiden Rückfall-Views am Schnitt zwischen den beiden
         * Kartenblöcken. Auf guide/index.blade.php entfällt der Anker: die
         * Kacheln liegen dort in einem mehrspaltigen Raster, in dem kein
         * Anker sitzen darf (Anker-Regel 3.2).
         */
        [
            'key' => 'guide_index_in_feed_5',
            'label' => 'Ratgeber-Übersicht · nach der 5. Themenkarte',
            'page_type' => AdPageType::GUIDE_INDEX->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['in_feed', 'responsive', 'rectangle_300x250'],
            'min_height_mobile' => 280,
            'min_height_desktop' => 250,
            'sort' => 20,
        ],
        /*
         * Nur guide/category.blade.php hat eine Seitenspalte (ab xl). Die
         * Übersicht /ratgeber ist einspaltig — in guide/index.blade.php wie
         * in der Rückfall-View pages/blog/index.blade.php. Der Anker bleibt
         * im Katalog, weil die Kategorieseite denselben Seitentyp trägt und
         * ihn rendert; auf /ratgeber ist er bewusst stumm, statt dort eine
         * Spalte allein für Werbung einzuziehen (Ticket #23). Seit #27 sagt
         * `routes` das auch dem Panel: auf /ratgeber wird der Anker nicht
         * mehr als belegbare Position dieser Seite angeboten.
         */
        [
            'key' => 'guide_index_sidebar_sticky',
            'label' => 'Ratgeber-Übersicht · Seitenspalte, klebend',
            'page_type' => AdPageType::GUIDE_INDEX->value,
            // Routen-Dimension (#27): auf `guide.index` gibt es keine
            // Seitenspalte, dort bleibt der Anker stumm.
            'routes' => ['guide.category'],
            'devices' => AdDevice::DESKTOP->value,
            'formats' => ['responsive', 'rectangle_300x250'],
            'min_height_mobile' => 0,
            'min_height_desktop' => 600,
            'sort' => 30,
        ],
        [
            'key' => 'guide_index_before_footer',
            'label' => 'Ratgeber-Übersicht · über der Fußzeile',
            'page_type' => AdPageType::GUIDE_INDEX->value,
            'devices' => AdDevice::ALL->value,
            'formats' => ['responsive', 'leaderboard_728x90'],
            'min_height_mobile' => 100,
            'min_height_desktop' => 90,
            'sort' => 40,
        ],

    ],

];
