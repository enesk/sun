<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Content-Pipeline
|--------------------------------------------------------------------------
|
| Zentrale Konfiguration der vollautomatischen Ratgeber-Pipeline.
| Die Architektur- und Kostenentscheidungen dahinter stehen in
| docs/content-pipeline.md.
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
        'topic_candidates_per_tenant_per_day' => (int) env('CONTENT_TOPIC_CANDIDATES_PER_DAY', 40),
        'max_articles_per_day_global' => (int) env('CONTENT_MAX_ARTICLES_PER_DAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget-Guard
    |--------------------------------------------------------------------------
    |
    | Harte Obergrenzen in USD. Der BudgetGuard vor dem LlmClient prueft vor
    | jedem Provider-Aufruf den bereits verbrauchten Tagesbetrag. Bei
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
            'dataforseo' => 0.10,
            'voyage' => 0.03,
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
    | Preise in USD pro 1 Mio. Tokens bzw. pro Request. Sie dienen dem
    | Kosten-Logging und dem Budget-Guard und muessen bei Preisaenderungen
    | des Anbieters nachgezogen werden.
    |
    */

    'providers' => [

        'anthropic' => [
            // 'api' = Messages API mit ANTHROPIC_API_KEY (Guthaben),
            // 'cli' = Claude-CLI mit dem Claude-Abo (CLAUDE_CODE_OAUTH_TOKEN
            // aus 'claude setup-token'). Kosten werden bei 'cli' mit 0 geloggt,
            // die Grenze setzt dann das Abo-Kontingent, nicht content.budget.
            'driver' => env('CONTENT_LLM_DRIVER', 'api'),
            'cli_binary' => env('CLAUDE_CLI_BINARY'),
            'cli_oauth_token' => env('CLAUDE_CODE_OAUTH_TOKEN'),

            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'model' => env('CONTENT_LLM_MODEL', 'claude-sonnet-5'),
            'max_tokens' => (int) env('CONTENT_LLM_MAX_TOKENS', 16000),
            'effort' => env('CONTENT_LLM_EFFORT', 'medium'),
            'timeout' => (int) env('CONTENT_LLM_TIMEOUT', 300),

            // Sampling-Parameter. claude-sonnet-5 lehnt temperature/top_p mit
            // HTTP 400 ab — der Wert wird deshalb nur mitgesendet, wenn er hier
            // gesetzt ist (null = weglassen). Fuer aeltere Modelle einstellbar.
            'temperature' => env('CONTENT_LLM_TEMPERATURE') === null
                ? null
                : (float) env('CONTENT_LLM_TEMPERATURE'),

            // Erweitertes Denken. Bei erzwungenem Tool-Use (tool_choice=tool)
            // liefert 'disabled' die verlaesslichste Schemaausgabe; 'adaptive'
            // ist moeglich, kostet aber Output-Tokens ohne Nutzen fuer JSON.
            'thinking' => env('CONTENT_LLM_THINKING', 'disabled'),

            // Name des einzigen Werkzeugs, ueber das die Antwort erzwungen wird.
            'tool_name' => 'emit',

            // Zusaetzliche Versuche bei schemawidriger Antwort (AK: 2 Retries).
            'schema_retries' => (int) env('CONTENT_LLM_SCHEMA_RETRIES', 2),

            // Prompt-Caching auf dem System-Prompt. System-Prompts und
            // Styleguides sind je Branche identisch, das spart bei 48
            // Artikeln/Tag den Grossteil der Eingabekosten.
            'prompt_cache' => (bool) env('CONTENT_LLM_PROMPT_CACHE', true),

            'pricing' => [
                'input_per_mtok' => 2.00,
                'output_per_mtok' => 10.00,
                'cache_write_per_mtok' => 2.50,
                'cache_read_per_mtok' => 0.20,
            ],
        ],

        'dataforseo' => [
            'login' => env('DATAFORSEO_LOGIN'),
            'password' => env('DATAFORSEO_PASSWORD'),
            'base_url' => env('DATAFORSEO_BASE_URL', 'https://api.dataforseo.com/v3'),
            'location_code' => (int) env('DATAFORSEO_LOCATION_CODE', 2276), // Deutschland
            'language_code' => env('DATAFORSEO_LANGUAGE_CODE', 'de'),
            'timeout' => 60,
            'pricing' => [
                'serp_advanced_per_request' => 0.0020,
                'keyword_volume_per_request' => 0.0050,
                'autocomplete_per_request' => 0.0006,
                'trends_per_request' => 0.0060,
            ],
            // Harte Tagesgrenze an Requests, unabhaengig vom USD-Budget.
            'max_requests_per_day' => (int) env('DATAFORSEO_MAX_REQUESTS_PER_DAY', 1500),

            // Google-Trends-Explore (#8).
            'trends' => [
                'path' => 'keywords_data/google_trends/explore/live',
                'time_range' => env('DATAFORSEO_TRENDS_TIME_RANGE', 'past_90_days'),
                'item_types' => ['google_trends_graph', 'google_trends_queries_list'],

                // API-Limit des Endpunkts: hoechstens 5 Keywords je Anfrage,
                // deshalb laufen die Seed-Keywords in Batches.
                'keywords_per_request' => 5,

                // Antworten werden so lange vorgehalten, dass ein
                // Wiederholungslauf am selben Tag nichts kostet.
                'cache_seconds' => (int) env('DATAFORSEO_TRENDS_CACHE_SECONDS', 86400),

                // Eigenes Tageskontingent nur fuer diesen Endpunkt, zusaetzlich
                // zur Gesamtgrenze des Providers. 20 Mandanten x 1 Batch x
                // (Bund + 16 Laender) = 340 Anfragen/Tag.
                'max_requests_per_day' => (int) env('DATAFORSEO_TRENDS_MAX_REQUESTS_PER_DAY', 400),

                // 'value' einer Rising Query, ab der Google von "Breakout"
                // spricht. Darauf wird signal_strength = 1.0 normiert.
                'breakout_value' => 5000,
            ],

            /*
            |------------------------------------------------------------------
            | SERP, Suchvolumen und Autocomplete (#10)
            |------------------------------------------------------------------
            |
            | Die drei Endpunkte des SerpInsightService. Sie laufen nicht im
            | Scheduler, sondern auf Zuruf aus Scoring (#12) und Generator
            | (#14). Ihr Zwischenspeicher steht auf 14 Tage — dieselbe Frist,
            | die auch keyword_clusters.serp_json gilt.
            |
            */
            'serp' => [
                'path' => 'serp/google/organic/live/advanced',

                // Tiefe 20 deckt Seite 1 und 2 ab; ausgewertet werden die
                // ersten serp.top_results organischen Treffer.
                'depth' => 20,

                // Klicktiefe 1 laedt die Folgefragen der PAA-Box nach.
                'people_also_ask_click_depth' => 1,
                'device' => 'desktop',
                'os' => 'windows',
                'cache_seconds' => (int) env('DATAFORSEO_SERP_CACHE_SECONDS', 1209600), // 14 Tage
                'max_requests_per_day' => (int) env('DATAFORSEO_SERP_MAX_REQUESTS_PER_DAY', 400),
            ],

            'search_volume' => [
                'path' => 'keywords_data/google_ads/search_volume/live',

                // API-Limit des Endpunkts.
                'keywords_per_request' => 700,
                'cache_seconds' => (int) env('DATAFORSEO_VOLUME_CACHE_SECONDS', 1209600),
                'max_requests_per_day' => (int) env('DATAFORSEO_VOLUME_MAX_REQUESTS_PER_DAY', 400),
            ],

            'autocomplete' => [
                'path' => 'serp/google/autocomplete/live/advanced',

                // Vorschlagsliste des Suchfelds einer Ergebnisseite.
                'client' => 'gws-wiz-serp',
                'cache_seconds' => (int) env('DATAFORSEO_AUTOCOMPLETE_CACHE_SECONDS', 1209600),
                'max_requests_per_day' => (int) env('DATAFORSEO_AUTOCOMPLETE_MAX_REQUESTS_PER_DAY', 400),
            ],
        ],

        'voyage' => [
            'api_key' => env('VOYAGE_API_KEY'),
            'base_url' => env('VOYAGE_BASE_URL', 'https://api.voyageai.com/v1'),
            'model' => env('VOYAGE_MODEL', 'voyage-3.5'),
            'dimensions' => (int) env('VOYAGE_DIMENSIONS', 1024),
            'timeout' => 60,

            // input_type der Voyage-API: 'document' beim Indexieren,
            // 'query' beim Suchen. EmbeddingClient::embed() nutzt document.
            'default_input_type' => 'document',

            // Groesste Menge Texte pro Request (Voyage-Limit: 128).
            'batch_size' => (int) env('VOYAGE_BATCH_SIZE', 128),
            'pricing' => [
                'input_per_mtok' => 0.06,
            ],
        ],

        'images' => [
            'driver' => env('CONTENT_IMAGE_DRIVER', 'fal'),
            'fal' => [
                'api_key' => env('FAL_API_KEY'),
                'base_url' => env('FAL_BASE_URL', 'https://fal.run'),

                // flux/schnell reicht fuer ein Titelbild und kostet einen
                // Bruchteil von flux/dev (~0.003 statt ~0.025 USD je Bild).
                // Wer das Modell wechselt, zieht 'per_image' mit nach — daraus
                // rechnet der BudgetGuard den Anteil 'images' des Tagesbudgets.
                'model' => env('FAL_IMAGE_MODEL', 'fal-ai/flux/schnell'),
                'image_size' => env('FAL_IMAGE_SIZE', 'landscape_16_9'),
                'num_inference_steps' => (int) env('FAL_IMAGE_STEPS', 4),

                // fal.ai antwortet synchron; das Bild wird anschliessend von
                // der zurueckgelieferten URL geladen.
                'timeout' => 120,
                'pricing' => [
                    'per_image' => (float) env('FAL_IMAGE_PRICE_USD', 0.003),
                ],
            ],
            'unsplash' => [
                'access_key' => env('UNSPLASH_ACCESS_KEY'),
                'base_url' => 'https://api.unsplash.com',
                'timeout' => 30,

                // Treffer je Suche. Aus ihnen waehlt der HeroImageGenerator
                // das erste Bild mit ausreichender Breite.
                'per_page' => (int) env('UNSPLASH_PER_PAGE', 10),
                'orientation' => 'landscape',
                'content_filter' => 'high',

                // Pflicht laut API-Richtlinie: bei Verwendung eines Bildes wird
                // der download_location-Endpunkt des Treffers ausgeloest.
                'trigger_download' => (bool) env('UNSPLASH_TRIGGER_DOWNLOAD', true),

                // utm-Parameter der Attributionslinks, ebenfalls Pflicht.
                'utm_source' => env('UNSPLASH_UTM_SOURCE', 'sun_ratgeber'),
                'pricing' => [
                    'per_image' => 0.0,
                ],
            ],
        ],

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
    | Metrik-Collector und Lernschleife (#23)
    |--------------------------------------------------------------------------
    |
    | Der Collector verdichtet die Search-Console-Rohzeilen aus
    | article_metrics_raw (#9) je Ratgeber zu einer Tageszeile und ruft dafuer
    | bewusst keine zweite Schnittstelle auf. Die Lernschleife aggregiert
    | daraus woechentlich Klicks je Artikel nach Cluster, Region und Branche;
    | das Ergebnis geht als Teilscore `performance` ins Themen-Scoring (#12).
    |
    */

    'metrics' => [
        // Aufbewahrung der Tageszeilen. Drei Jahre, damit Jahresvergleiche
        // moeglich bleiben; die Tabelle bleibt trotzdem klein.
        'retention_days' => (int) env('CONTENT_METRICS_RETENTION_DAYS', 1095),

        // Alter in Tagen, zu dem ein Snapshot festgehalten wird.
        'snapshots' => [7, 30, 90],

        // Ab wann ein Artikel als abrutschend gilt (#24).
        'refresh' => [
            'compare_days' => 14,
            'position_drop' => 5.0,
            'ctr_drop_ratio' => 0.4,

            // Rauschgrenze: unter so vielen Impressionen im Vergleichsfenster
            // ist jede Bewegung Zufall.
            'min_impressions' => 100,
        ],

        'learning' => [
            // Fenster der Aggregation.
            'window_days' => (int) env('CONTENT_LEARNING_WINDOW_DAYS', 90),

            // Ein Artikel zaehlt erst mit, wenn er lange genug online ist.
            'min_age_days' => 14,

            // Gruppen unter dieser Artikelzahl liefern keine belastbare
            // Rangliste und bleiben ohne Boost.
            'min_articles' => 3,

            // Vorhaltezeit der Aggregation im Cache.
            'cache_minutes' => 360,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Refresh-Loop (#24)
    |--------------------------------------------------------------------------
    |
    | Ein abrutschender Artikel wird aktualisiert, nicht neu geschrieben: der
    | RefreshSelector sucht Kandidaten (needs_refresh aus dem Metrik-Collector
    | oder abgelaufene Faktenschnipsel), der RefreshArticleJob laesst genau die
    | betroffenen Abschnitte ueberarbeiten und veroeffentlicht die neue Fassung
    | unter derselben URL.
    |
    | Refreshes laufen ausserhalb des Tagesziels von zwei neuen Artikeln und
    | haben ein eigenes, engeres Kontingent: drei je Mandant und Tag. Ein
    | Refresh kostet rund ein Viertel eines neuen Artikels (nur die
    | betroffenen Abschnitte plus Qualitaetsgate), das Tagesbudget deckt das
    | ohne Anpassung.
    |
    */

    'refresh' => [
        // Harte Obergrenze je Mandant und Tag (Abnahmekriterium #24).
        'max_per_tenant_per_day' => (int) env('CONTENT_REFRESH_MAX_PER_TENANT_PER_DAY', 3),

        // Sperrfrist nach einer Aktualisierung. Ohne sie liefe derselbe
        // Artikel jeden Tag erneut durch: der Metrik-Collector vergleicht ein
        // 14-Tage-Fenster und setzt die Marke sonst gleich wieder.
        'cooldown_days' => (int) env('CONTENT_REFRESH_COOLDOWN_DAYS', 30),

        // Mindestalter des Artikels. Frueher liegen noch keine belastbaren
        // Zahlen vor, und die Erstveroeffentlichung ist noch in der Indexierung.
        'min_age_days' => (int) env('CONTENT_REFRESH_MIN_AGE_DAYS', 30),

        // Hoechstens so viele Abschnitte werden je Lauf ueberarbeitet. Mehr
        // waere faktisch ein Neuschreiben — dafuer gibt es den Generator.
        'max_sections' => (int) env('CONTENT_REFRESH_MAX_SECTIONS', 4),

        // Wie viele Kandidaten der Selektor hoechstens durchsieht, bevor er
        // nach Dringlichkeit sortiert. Begrenzt die Arbeit je Portal.
        'candidate_pool' => (int) env('CONTENT_REFRESH_CANDIDATE_POOL', 100),

        // Uhrzeit des Tageslaufs (Europe/Berlin). Er liegt nach dem
        // Metrik-Collector (06:30), damit er dessen frische Marken vorfindet,
        // und vor dem Veroeffentlichungsfenster.
        'run_at' => env('CONTENT_REFRESH_RUN_AT', '07:15'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate-Limits pro Provider
    |--------------------------------------------------------------------------
    |
    | Werden per Redis-Limiter durchgesetzt. Die Anthropic-Werte sind auf Tier 2
    | ausgelegt und muessen bei einem Tier-Wechsel angepasst werden.
    |
    */

    'rate_limits' => [
        'anthropic' => [
            'requests_per_minute' => (int) env('CONTENT_ANTHROPIC_RPM', 400),
            'input_tokens_per_minute' => (int) env('CONTENT_ANTHROPIC_ITPM', 350000),
            'output_tokens_per_minute' => (int) env('CONTENT_ANTHROPIC_OTPM', 70000),
            'max_concurrency' => (int) env('CONTENT_ANTHROPIC_CONCURRENCY', 4),
        ],
        'dataforseo' => [
            'requests_per_minute' => (int) env('CONTENT_DATAFORSEO_RPM', 120),
            'max_concurrency' => (int) env('CONTENT_DATAFORSEO_CONCURRENCY', 8),
        ],
        'voyage' => [
            'requests_per_minute' => (int) env('CONTENT_VOYAGE_RPM', 300),
            'max_concurrency' => 4,
        ],
        'images' => [
            'requests_per_minute' => (int) env('CONTENT_IMAGES_RPM', 30),
            'max_concurrency' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry & Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Jeder Provider-Aufruf wird 3x mit exponentiellem Backoff wiederholt.
    | Nach failure_threshold aufeinanderfolgenden Fehlern oeffnet der Circuit
    | Breaker (Cache-Flag) fuer open_seconds; betroffene Jobs werden released
    | statt zu scheitern, damit ein Provider-Ausfall nicht die Tageskette killt.
    |
    */

    'resilience' => [
        'retry' => [
            'attempts' => 3,
            'base_delay_ms' => 2000,
            'multiplier' => 3,
            'jitter_ms' => 500,
        ],
        // Wie lange ein toter Zugang (Guthaben aufgebraucht, Schluessel
        // abgelehnt, #104) als tot gilt, bevor ein Probeaufruf ihn wieder
        // freigeben darf.
        'account_recheck_seconds' => 3600,
        'circuit_breaker' => [
            'enabled' => true,
            'cache_prefix' => 'content:cb:',
            'failure_threshold' => 5,
            'open_seconds' => 900,
            'half_open_probes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline & Queues
    |--------------------------------------------------------------------------
    */

    'pipeline' => [
        'queues' => [
            'sources' => 'content-sources',
            'discovery' => 'content-discovery',
            'llm' => 'content-llm',
            'assets' => 'content-assets',
            'publish' => 'content-publish',
            'metrics' => 'content-metrics',
        ],

        // Zeitzone aller Scheduler-Eintraege der Pipeline (#22). Die
        // Anwendung laeuft in UTC; die Redaktionszeiten sind deutsche.
        'timezone' => env('CONTENT_SCHEDULE_TIMEZONE', 'Europe/Berlin'),

        // Uhrzeiten (Europe/Berlin) der Tagespipeline.
        'schedule' => [
            // Themenfindung und Scoring laufen als eine Kette.
            'discover_at' => '02:00',
            'score_at' => '02:00',
            'select_at' => '03:00',
            'generate_at' => '03:30',
            'report_at' => '20:00',
            // Nur Anzeige. Massgeblich fuer den Publisher (#21) sind die
            // Zeiten des Mandanten und darunter publishing.window.
            'publish_window' => [
                env('CONTENT_PUBLISH_WINDOW_START', '07:00'),
                env('CONTENT_PUBLISH_WINDOW_END', '19:00'),
            ],
            'metrics_at' => '06:30',
        ],

        // Slot-Wachhund (#22). Er laeuft alle `every_minutes` Minuten, aber
        // erst `grace_minutes` nach dem Generatorlauf: davor ist ein leerer
        // Slot der Normalzustand.
        'watchdog' => [
            'every_minutes' => (int) env('CONTENT_WATCHDOG_EVERY_MINUTES', 30),
            'grace_minutes' => (int) env('CONTENT_WATCHDOG_GRACE_MINUTES', 45),

            // Anlaeufe je Slot und Tag, danach gibt der Wachhund auf und
            // meldet einen Alarm.
            'max_attempts' => (int) env('CONTENT_WATCHDOG_MAX_ATTEMPTS', 3),
        ],

        // Alarme (#22). Kritische Alarme gehen einmalig sofort an die
        // Administratoren; abends stehen sie noch einmal gesammelt im
        // Tagesbericht.
        'alerts' => [
            'mail' => (bool) env('CONTENT_ALERT_MAIL', true),
        ],

        // Ein Entwurf, der laenger als das hier in einem In-Flight-Status haengt,
        // wird vom Orchestrator als haengend gemeldet.
        'stuck_after_minutes' => 45,

        // Wie viele Generierungsversuche ein Entwurf bekommt, bevor er auf
        // DraftStatus::FAILED gesetzt wird.
        'max_generation_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Regionen (#8, #11, #12)
    |--------------------------------------------------------------------------
    |
    | Schluessel ist immer der ISO-3166-2-Code; genau der landet in
    | source_items.region_code und in topic_candidates.region_code. Er ist
    | zugleich der geo-Parameter von Google Trends ('DE-BY').
    |
    | 'location_code' ist die Geotarget-ID, die DataForSEO und Google Ads
    | verwenden (Quelle: Google-Ads-Geotargets 2024-08-13, identisch mit
    | /v3/keywords_data/google_trends/locations). Sie steht hier fest, damit
    | kein Lauf erst eine Standortliste abrufen muss.
    |
    */

    'regions' => [

        'country' => [
            'iso' => 'DE',
            'trends_geo' => 'DE',
            'location_code' => (int) env('DATAFORSEO_LOCATION_CODE', 2276),
        ],

        'states' => [
            'DE-BW' => ['name' => 'Baden-Wuerttemberg', 'location_code' => 20228, 'location_name' => 'Baden-Wurttemberg,Germany'],
            'DE-BY' => ['name' => 'Bayern', 'location_code' => 20229, 'location_name' => 'Bavaria,Germany'],
            'DE-BE' => ['name' => 'Berlin', 'location_code' => 20226, 'location_name' => 'Berlin,Germany'],
            'DE-BB' => ['name' => 'Brandenburg', 'location_code' => 20227, 'location_name' => 'Brandenburg,Germany'],
            'DE-HB' => ['name' => 'Bremen', 'location_code' => 20230, 'location_name' => 'Bremen,Germany'],
            'DE-HH' => ['name' => 'Hamburg', 'location_code' => 20232, 'location_name' => 'Hamburg,Germany'],
            'DE-HE' => ['name' => 'Hessen', 'location_code' => 20231, 'location_name' => 'Hessen,Germany'],
            'DE-MV' => ['name' => 'Mecklenburg-Vorpommern', 'location_code' => 20233, 'location_name' => 'Mecklenburg-Vorpommern,Germany'],
            'DE-NI' => ['name' => 'Niedersachsen', 'location_code' => 20234, 'location_name' => 'Lower Saxony,Germany'],
            'DE-NW' => ['name' => 'Nordrhein-Westfalen', 'location_code' => 20235, 'location_name' => 'North Rhine-Westphalia,Germany'],
            'DE-RP' => ['name' => 'Rheinland-Pfalz', 'location_code' => 20236, 'location_name' => 'Rhineland-Palatinate,Germany'],
            'DE-SL' => ['name' => 'Saarland', 'location_code' => 20238, 'location_name' => 'Saarland,Germany'],
            'DE-SN' => ['name' => 'Sachsen', 'location_code' => 20239, 'location_name' => 'Saxony,Germany'],
            'DE-ST' => ['name' => 'Sachsen-Anhalt', 'location_code' => 20240, 'location_name' => 'Saxony-Anhalt,Germany'],
            'DE-SH' => ['name' => 'Schleswig-Holstein', 'location_code' => 20237, 'location_name' => 'Schleswig-Holstein,Germany'],
            'DE-TH' => ['name' => 'Thueringen', 'location_code' => 20241, 'location_name' => 'Thuringia,Germany'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quell-Connectoren (#7-#11)
    |--------------------------------------------------------------------------
    |
    | Das Connector-Framework laeuft je Tenant als eigener Queue-Job auf der
    | Queue 'content-sources' (eigenes Supervisor-Programm, siehe
    | config/horizon.php). Neue Connectoren werden ausschliesslich im
    | ContentServiceProvider registriert, der Runner kennt sie nicht namentlich.
    |
    */

    'sources' => [
        'queue' => env('CONTENT_SOURCES_QUEUE', 'content-sources'),

        // Timeout des Queue-Jobs je Connector und Tenant.
        'job_timeout' => (int) env('CONTENT_SOURCES_JOB_TIMEOUT', 300),

        'http' => [
            'user_agent_template' => 'SUN-ContentBot/1.0 (+:bot_url)',
            'bot_path' => 'bot',
            'timeout' => (int) env('CONTENT_SOURCES_HTTP_TIMEOUT', 15),
            'connect_timeout' => (int) env('CONTENT_SOURCES_HTTP_CONNECT_TIMEOUT', 5),

            // robots.txt wird je Host geprueft und fuer diese Dauer zwischengespeichert.
            'respect_robots' => (bool) env('CONTENT_SOURCES_RESPECT_ROBOTS', true),
            'robots_cache_seconds' => 21600,
        ],

        // Aufbewahrung der Rohsignale. Aeltere source_items werden vom
        // Prune-Command geloescht, sofern sie an keinem Thema, Entwurf oder
        // Faktenschnipsel haengen.
        'retention_days' => (int) env('CONTENT_SOURCES_RETENTION_DAYS', 30),

        // Ab so vielen Fehlern in Folge je Connector wird ein Dashboard-Alarm
        // ausgeloest (ProviderState bleibt darunter nur 'degraded').
        'alarm_after_failures' => (int) env('CONTENT_SOURCES_ALARM_AFTER_FAILURES', 3),

        // Beispiel-Connector fuer Staging (Abnahme #7): erzeugt ohne externen
        // Aufruf ein source_item und beweist Scheduler, Queue und Speicherung.
        'dummy_enabled' => (bool) env('CONTENT_SOURCES_DUMMY_ENABLED', false),

        /*
        |----------------------------------------------------------------------
        | Google Trending, RSS (#8)
        |----------------------------------------------------------------------
        |
        | Kostenloser Feed, stuendlich. Es entstehen nur source_items, die
        | mindestens ein Branchen-Keyword des Mandanten treffen.
        |
        */
        'google_trends_rss' => [
            'enabled' => (bool) env('CONTENT_SOURCES_GOOGLE_TRENDS_RSS_ENABLED', true),
            'feed_url' => env('CONTENT_GOOGLE_TRENDS_RSS_URL', 'https://trends.google.com/trending/rss'),
            'geo' => env('CONTENT_GOOGLE_TRENDS_GEO', 'DE'),

            // Hoechstens so viele Feed-Eintraege werden geprueft.
            'max_items' => (int) env('CONTENT_GOOGLE_TRENDS_MAX_ITEMS', 60),

            // 'ht:approx_traffic', ab dem signal_strength 1.0 erreicht;
            // darunter linear. Trending-Eintraege liegen ueblicherweise
            // zwischen 1.000 und 500.000 Suchanfragen.
            'traffic_full_scale' => (int) env('CONTENT_GOOGLE_TRENDS_TRAFFIC_SCALE', 500000),

            // Ab dieser Laenge darf ein Wortstamm auch innerhalb eines
            // Kompositums treffen ('heiz' in 'Heizungsgesetz'). Kuerzere
            // Staemme muessen ein ganzes Wort treffen, sonst trifft alles.
            'min_stem_length' => (int) env('CONTENT_GOOGLE_TRENDS_MIN_STEM', 4),
        ],

        /*
        |----------------------------------------------------------------------
        | Google Trends Explore ueber DataForSEO (#8)
        |----------------------------------------------------------------------
        |
        | Einmal taeglich je Mandant: Rising Related Queries und die
        | Interessekurve der Seed-Keywords, bundesweit und je Bundesland.
        | Kostenpflichtig, deshalb standardmaessig aus.
        |
        */
        'dataforseo_trends' => [
            'enabled' => (bool) env('CONTENT_SOURCES_DATAFORSEO_TRENDS_ENABLED', false),

            // Seed-Keywords je Lauf. Ein Batch = eine Anfrage je Region.
            'max_seed_keywords' => (int) env('CONTENT_DATAFORSEO_TRENDS_SEEDS', 5),

            // Bundeslaender je Lauf. Ohne gepflegte preferred_states des
            // Mandanten werden die ersten so vieler Laender abgefragt.
            'max_states_per_run' => (int) env('CONTENT_DATAFORSEO_TRENDS_STATES', 16),

            // Rising Queries je Seed-Keyword und Region.
            'max_queries_per_keyword' => 10,

            // Rising Queries unterhalb dieser normierten Wachstumsstaerke
            // werden verworfen.
            'min_growth' => 0.1,

            // Zusaetzlich zu den Bundeslaendern auch bundesweit abfragen.
            'include_national' => true,
        ],

        /*
        |----------------------------------------------------------------------
        | Search-Console-Luecken (#9)
        |----------------------------------------------------------------------
        |
        | Der Connector holt taeglich die Suchanfragen der letzten 28 Tage je
        | Tenant-Property, cacht die Rohzeilen in article_metrics_raw (Basis
        | fuer #23/#24) und legt drei Arten von Luecken als source_items ab:
        |
        |   ranking_chance – schon sichtbar, aber knapp hinter Seite 1
        |   snippet_issue  – viele Impressionen, kaum Klicks
        |   content_gap    – Nachfrage ohne eigenen Ratgeber
        |
        */
        'gsc_gap' => [
            'enabled' => (bool) env('CONTENT_SOURCES_GSC_ENABLED', false),

            // Praefix, an dem eine eigene Ratgeber-URL erkannt wird.
            'article_path_prefix' => env('CONTENT_RATGEBER_PREFIX', '/ratgeber/'),

            // (a) sichtbar, aber hinter Seite 1.
            'ranking_chance' => [
                'min_impressions' => 50,
                'min_position' => 8.0,
                'max_position' => 30.0,
            ],

            // (b) Impressionen ohne Klicks: Snippet oder Inhalt passt nicht.
            'snippet_issue' => [
                'min_impressions' => 100,
                'max_ctr' => 0.01,
            ],

            // (c) Nachfrage, zu der es keinen Ratgeber gibt.
            'content_gap' => [
                'min_impressions' => 30,
            ],

            // demand_score = log10(1+impressions) / log10(1+reference), auf
            // 0..1 begrenzt. 10.000 Impressionen im 28-Tage-Fenster gelten
            // als voller Ausschlag.
            'demand_reference_impressions' => (int) env('CONTENT_GSC_DEMAND_REFERENCE', 10000),

            // Hoechstzahl Gap-Items je Mandant und Lauf, nach demand_score
            // absteigend.
            'max_items' => (int) env('CONTENT_GSC_MAX_ITEMS', 300),

            // Marken- und Ortssuchen ohne Informationsabsicht aussortieren.
            'min_query_words' => 2,

            // Aufbewahrung der Rohzeilen in article_metrics_raw.
            'raw_retention_days' => (int) env('CONTENT_GSC_RAW_RETENTION_DAYS', 120),
        ],

        /*
        |----------------------------------------------------------------------
        | Wortstamm-Abgleich (#11)
        |----------------------------------------------------------------------
        |
        | Gilt fuer alle Connectoren, die freien Text gegen die
        | Branchen-Keywords des Mandanten pruefen (KeywordMatcher).
        |
        */
        'keyword_matcher' => [
            // Ab dieser Laenge darf ein Wortstamm auch im Kompositum treffen
            // ('heiz' in 'Heizungsgesetz'); kuerzere muessen ein ganzes Wort
            // treffen, sonst trifft alles.
            'min_stem_length' => (int) env('CONTENT_KEYWORD_MIN_STEM', 4),
        ],

        /*
        |----------------------------------------------------------------------
        | Feeds: Foerderung, Recht, Verbaende (#11)
        |----------------------------------------------------------------------
        |
        | Die Feed-Liste selbst steht in config/content_feeds.php und ist nach
        | Branche gruppiert; ein neuer Feed braucht keinen Code.
        |
        */
        'rss_feeds' => [
            'enabled' => (bool) env('CONTENT_SOURCES_RSS_FEEDS_ENABLED', true),

            // Meldungen, die aelter als das sind, zaehlen nur noch halb.
            'fresh_days' => (int) env('CONTENT_RSS_FRESH_DAYS', 14),
        ],

        /*
        |----------------------------------------------------------------------
        | Foerderdatenbank des Bundes (#11)
        |----------------------------------------------------------------------
        |
        | Gelesen wird die Ergebnisliste, nicht die Detailseiten. Zwischen zwei
        | Abrufen liegt mindestens request_interval_ms — das ist die
        | Hoeflichkeitsgrenze von einem Request pro Sekunde aus dem Ticket.
        |
        | Die Selektoren stehen hier und nicht im Code, weil das Markup einer
        | Behoerdenseite sich aendern kann, ohne dass jemand Bescheid sagt.
        |
        */
        'foerderdatenbank' => [
            'enabled' => (bool) env('CONTENT_SOURCES_FOERDERDATENBANK_ENABLED', true),

            'base_url' => 'https://www.foerderdatenbank.de',
            'list_url' => env(
                'CONTENT_FOERDERDATENBANK_URL',
                'https://www.foerderdatenbank.de/SiteGlobals/FDB/Forms/Suche/Foederprogrammsuche_Formular.html',
            ),

            // Feste Suchparameter der Programmliste.
            'query' => [
                'filterCategories' => 'FundingProgram',
                'submit' => 'Suchen',
                'resultsPerPage' => 50,
            ],

            // Parameter, ueber den nach Foerdergebiet gefiltert wird.
            'region_parameter' => 'filterFoerdergebiet',

            'result_selector' => '.card--fundingprogram',
            'title_selector' => '.card--title',
            'summary_selector' => '.card--content',
            'region_selector' => '.card--list',

            'request_interval_ms' => (int) env('CONTENT_FOERDERDATENBANK_INTERVAL_MS', 1000),
            'max_states_per_run' => (int) env('CONTENT_FOERDERDATENBANK_STATES', 4),
            'max_items_per_region' => (int) env('CONTENT_FOERDERDATENBANK_MAX_ITEMS', 40),
            'filter_by_keywords' => true,
            'signal_strength' => 0.7,
        ],

        /*
        |----------------------------------------------------------------------
        | Amtliche Statistik und DWD (#11)
        |----------------------------------------------------------------------
        |
        | Tabellen und Datensaetze stehen in config/content_statistics.php.
        | Der Connector schreibt fact_snippets; die Zahlen selbst landen nicht
        | in source_items.
        |
        */
        'statistics' => [
            'enabled' => (bool) env('CONTENT_SOURCES_STATISTICS_ENABLED', true),

            // Rohsignale aus Statistik sind Beleg, kein Themenanlass —
            // entsprechend niedrig gewichtet.
            'signal_strength' => 0.3,

            // Schutz gegen versehentlich abgerufene Riesentabellen.
            'max_rows_per_table' => (int) env('CONTENT_STATISTICS_MAX_ROWS', 5000),
        ],

        /*
        |----------------------------------------------------------------------
        | Regionale Nachrichten ueber Google News RSS (#11)
        |----------------------------------------------------------------------
        |
        | Gespeichert werden nur Titel, Medium, Link und Datum — kein
        | Anrisstext, kein Artikeltext.
        |
        */
        'google_news_rss' => [
            'enabled' => (bool) env('CONTENT_SOURCES_GOOGLE_NEWS_ENABLED', true),

            'search_url' => 'https://news.google.com/rss/search',
            'hl' => 'de',
            'gl' => 'DE',
            'ceid' => 'DE:de',

            // Zusatz der bundesweiten Suchanfrage neben dem Branchennamen.
            'national_suffix' => 'Deutschland',

            'max_states_per_run' => (int) env('CONTENT_GOOGLE_NEWS_STATES', 4),
            'max_items_per_query' => (int) env('CONTENT_GOOGLE_NEWS_MAX_ITEMS', 15),
            'max_age_days' => (int) env('CONTENT_GOOGLE_NEWS_MAX_AGE_DAYS', 21),
            'signal_strength' => 0.4,
        ],

        /*
        |----------------------------------------------------------------------
        | Saisonkalender und Schulferien (#11)
        |----------------------------------------------------------------------
        |
        | Quellen sind die Tenant-Tabelle seasonal_topics (#13) und
        | ferien-api.de. Der seasonal_score liegt in payload_json und in
        | signal_strength: 1.0 im Fenster, 0.5 in den 14 Tagen davor.
        |
        */
        'seasonal_calendar' => [
            'enabled' => (bool) env('CONTENT_SOURCES_SEASONAL_ENABLED', true),

            // ferien-api.de ist ein Freiwilligenprojekt; faellt es aus, laeuft
            // der Kalenderteil trotzdem.
            'holidays_enabled' => (bool) env('CONTENT_SOURCES_HOLIDAYS_ENABLED', true),
            'holidays_url' => env('CONTENT_HOLIDAYS_URL', 'https://ferien-api.de/api/v1/holidays'),
        ],

        /*
        |----------------------------------------------------------------------
        | Portal-Eigendaten (#11)
        |----------------------------------------------------------------------
        |
        | Aggregiert Betriebe, Bewertungen und Leistungen je Stadt und
        | Bundesland zu fact_snippets. Von Betrieben werden ausschliesslich
        | Name und Ort verwendet, nie Kontaktdaten.
        |
        */
        'portal_data' => [
            'enabled' => (bool) env('CONTENT_SOURCES_PORTAL_DATA_ENABLED', true),

            // Staedte je Lauf, nach Zahl der gelisteten Betriebe.
            'max_cities' => (int) env('CONTENT_PORTAL_DATA_MAX_CITIES', 25),
            'min_companies_per_city' => 3,
            'max_states' => 4,

            // Hoechstens drei Betriebe je Region, und nur mit genug
            // Bewertungen — sonst entscheidet eine einzelne Rezension.
            'top_rated_limit' => 3,
            'min_reviews_for_top' => 3,
            'services_limit' => 5,

            // Portalzahlen veralten schnell; nach einem Monat neu belegen.
            'valid_days' => (int) env('CONTENT_PORTAL_DATA_VALID_DAYS', 30),
            'signal_strength' => 0.5,
        ],

        /*
        |----------------------------------------------------------------------
        | Lead-Freitexte zu Fragethemen clustern (#11)
        |----------------------------------------------------------------------
        |
        | Woechentlich, angestossen vom Portal-Connector. Kostet einen
        | Modellaufruf je Mandant, deshalb standardmaessig aus.
        |
        | 'tables' sagt, wo Freitexte liegen. Fehlt eine Tabelle in der
        | Tenant-DB, wird sie stillschweigend uebersprungen.
        |
        */
        'lead_questions' => [
            'enabled' => (bool) env('CONTENT_SOURCES_LEAD_QUESTIONS_ENABLED', false),

            'tables' => [
                ['table' => 'tracking_events', 'column' => 'search_query', 'date_column' => 'created_at'],
                ['table' => 'reviews', 'column' => 'body', 'date_column' => 'created_at'],
                ['table' => 'company_edit_suggestions', 'column' => 'reason', 'date_column' => 'created_at'],
                ['table' => 'job_applications', 'column' => 'message', 'date_column' => 'created_at'],
            ],

            'window_days' => (int) env('CONTENT_LEAD_QUESTIONS_WINDOW_DAYS', 90),

            // Unter dieser Zahl bereinigter Texte lohnt kein Modellaufruf.
            'min_texts' => (int) env('CONTENT_LEAD_QUESTIONS_MIN_TEXTS', 20),
            'max_texts' => (int) env('CONTENT_LEAD_QUESTIONS_MAX_TEXTS', 400),

            // Obergrenze aus dem Abnahmekriterium: 10 Fragen je Branche.
            'max_questions' => 10,
        ],

        /*
        |----------------------------------------------------------------------
        | Katalog aller bekannten Connectoren (#55)
        |----------------------------------------------------------------------
        |
        | design/content-dashboard.md, §7a. Die Registry kennt nur die
        | eingeschalteten Connectoren — was in der Serverkonfiguration aus ist,
        | wird im ContentServiceProvider gar nicht erst registriert. Fuer den
        | Block "Nicht eingerichtet" unter dem Raster braucht die Seite
        | trotzdem die uebrigen.
        |
        | Je Eintrag: Klartextname, der Schalter in dieser Datei, der Name der
        | Umgebungsvariablen und die Zugangsschluessel, die vorhanden sein
        | muessen. 'credentials' leer heisst: frei abrufbar, kein Zugang noetig.
        | Geprueft wird ausschliesslich das Vorhandensein der Werte, nie ihre
        | Gueltigkeit — das sagt die Zustandspille der Karte.
        |
        */
        'catalog' => [
            'google_trends_rss' => [
                'label' => 'Google Trends (RSS)',
                'config_key' => 'content.sources.google_trends_rss.enabled',
                'env' => 'CONTENT_SOURCES_GOOGLE_TRENDS_RSS_ENABLED',
                'credentials' => [],
            ],
            'dataforseo_trends' => [
                'label' => 'Google Trends (DataForSEO)',
                'config_key' => 'content.sources.dataforseo_trends.enabled',
                'env' => 'CONTENT_SOURCES_DATAFORSEO_TRENDS_ENABLED',
                'credentials' => [
                    'DATAFORSEO_LOGIN' => 'content.providers.dataforseo.login',
                    'DATAFORSEO_PASSWORD' => 'content.providers.dataforseo.password',
                ],
            ],
            'gsc_gap' => [
                'label' => 'Search Console — Content-Lücken',
                'config_key' => 'content.sources.gsc_gap.enabled',
                'env' => 'CONTENT_SOURCES_GSC_ENABLED',
                'credentials' => [
                    'GOOGLE_SERVICE_ACCOUNT_JSON' => 'content.providers.search_console.credentials_path',
                ],
            ],
            'rss_feeds' => [
                'label' => 'Förderung und Recht (RSS)',
                'config_key' => 'content.sources.rss_feeds.enabled',
                'env' => 'CONTENT_SOURCES_RSS_FEEDS_ENABLED',
                'credentials' => [],
            ],
            'foerderdatenbank' => [
                'label' => 'Förderdatenbank',
                'config_key' => 'content.sources.foerderdatenbank.enabled',
                'env' => 'CONTENT_SOURCES_FOERDERDATENBANK_ENABLED',
                'credentials' => [],
            ],
            'statistics' => [
                'label' => 'Statistik',
                'config_key' => 'content.sources.statistics.enabled',
                'env' => 'CONTENT_SOURCES_STATISTICS_ENABLED',
                'credentials' => [],
            ],
            'google_news_rss' => [
                'label' => 'Regionale News',
                'config_key' => 'content.sources.google_news_rss.enabled',
                'env' => 'CONTENT_SOURCES_GOOGLE_NEWS_ENABLED',
                'credentials' => [],
            ],
            'seasonal_calendar' => [
                'label' => 'Saisonkalender',
                'config_key' => 'content.sources.seasonal_calendar.enabled',
                'env' => 'CONTENT_SOURCES_SEASONAL_ENABLED',
                'credentials' => [],
            ],
            'portal_data' => [
                'label' => 'Portal-Eigendaten',
                'config_key' => 'content.sources.portal_data.enabled',
                'env' => 'CONTENT_SOURCES_PORTAL_DATA_ENABLED',
                'credentials' => [],
            ],
            'dummy_ping' => [
                'label' => 'Beispiel-Connector (Staging)',
                'config_key' => 'content.sources.dummy_enabled',
                'env' => 'CONTENT_SOURCES_DUMMY_ENABLED',
                'credentials' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SERP-Analyse (#10)
    |--------------------------------------------------------------------------
    |
    | Der SerpInsightService laeuft auf Zuruf, nicht im Scheduler. Ein Aufruf
    | kostet drei DataForSEO-Anfragen plus den eigenen Abruf der ersten
    | outline_top_n Wettbewerberseiten. Das Ergebnis liegt cache_days Tage in
    | keyword_clusters.serp_json; innerhalb dieser Frist entsteht fuer dasselbe
    | Keyword und dieselbe Region kein zweiter API-Aufruf.
    |
    */

    'serp_insight' => [
        'cache_days' => (int) env('CONTENT_SERP_CACHE_DAYS', 14),

        // Organische Treffer, die ins DTO wandern.
        'top_results' => (int) env('CONTENT_SERP_TOP_RESULTS', 10),

        // Von so vielen Treffern wird die H2-Gliederung selbst geholt. Jeder
        // Abruf ist ein fremder Seitenaufruf, deshalb bewusst klein.
        'outline_top_n' => (int) env('CONTENT_SERP_OUTLINE_TOP_N', 3),

        // Obergrenzen der Listen. Zwoelf PAA-Fragen sind mehr, als ein
        // Ratgeber je als FAQ braucht (#14).
        'max_paa' => 12,
        'max_related' => 12,
        'max_autocomplete' => 15,

        // Eigener Abruf der Wettbewerberseiten.
        'fetch' => [
            'timeout' => (int) env('CONTENT_SERP_FETCH_TIMEOUT', 10),
            'connect_timeout' => 5,

            // Groesser gelieferte Seiten werden nicht geparst; gespeichert
            // werden ohnehin nur Ueberschriften und Wortzahl.
            'max_bytes' => 2000000,

            // Ueberschriften je Seite.
            'max_headings' => 25,
        ],

        // Zusaetzliche Domains, die als eigenes Netz gelten (Landingpages,
        // Altdomains). Die Domains der tenants-Tabelle kommen automatisch dazu.
        'own_network_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CONTENT_OWN_NETWORK_DOMAINS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Themenfindung, Scoring und Tagesauswahl (#12)
    |--------------------------------------------------------------------------
    |
    | Alle Teilscores liegen auf einer Skala von 0 bis 100; der Gesamtscore ist
    | die gewichtete Summe und damit ebenfalls 0 bis 100. Die Gewichte hier
    | sind der Fallback — fuehrend ist
    | tenant_content_settings.scoring_weights_json, sobald der Mandant eigene
    | Gewichte gepflegt hat.
    |
    */

    'topics' => [

        'discover' => [
            // Fenster der ausgewerteten Rohsignale.
            'lookback_days' => (int) env('CONTENT_TOPICS_LOOKBACK_DAYS', 7),

            // Obergrenze aus dem Ticket: 30 Kandidaten je Mandant und Lauf.
            'max_candidates' => (int) env('CONTENT_TOPICS_MAX_CANDIDATES', 30),

            // So viele Rohsignale gehen hoechstens in den Prompt. Mehr kostet
            // Eingabetokens, ohne die Themenqualitaet zu verbessern.
            'max_source_items' => (int) env('CONTENT_TOPICS_MAX_SOURCE_ITEMS', 120),

            // Kandidaten, die keinem einzigen Rohsignal zugeordnet sind,
            // werden verworfen — sonst erfindet das Modell Themen ohne Anlass.
            'require_source_items' => true,

            // So viele Titel bereits veroeffentlichter Ratgeber gehen als
            // 'internal_link_targets' in den Prompt, damit das Modell keine
            // vorhandenen Themen wiederholt.
            'max_link_targets' => (int) env('CONTENT_TOPICS_MAX_LINK_TARGETS', 40),
        ],

        // Gewichte des Gesamtscores (Summe 1.0).
        'weights' => [
            'trend' => 0.30,
            'demand' => 0.25,
            'gap' => 0.20,
            'seasonal' => 0.15,
            'uniqueness' => 0.10,

            // Lernschleife (#23): wie gut haben vergleichbare Artikel des
            // Mandanten bisher abgeschnitten? Bewusst klein gehalten — die
            // Vergangenheit korrigiert die Rangfolge, sie bestimmt sie nicht.
            'performance' => 0.10,
        ],

        'demand' => [
            // Suchvolumen, ab dem demand_score den vollen Ausschlag erreicht
            // (logarithmisch normiert).
            'reference_volume' => (int) env('CONTENT_TOPICS_DEMAND_VOLUME', 2000),

            // Dieselbe Normierung fuer GSC-Impressionen im 28-Tage-Fenster.
            'reference_impressions' => (int) env('CONTENT_TOPICS_DEMAND_IMPRESSIONS', 5000),

            // Kein eigener Volumen-Abruf im Scoring: Suchvolumen kostet Geld
            // (DataForSEO) und wird nur verwendet, wenn ein Rohsignal es
            // mitgebracht hat. Das vollstaendige Suchergebnisbild holt der
            // Generator (#14) fuer die ausgewaehlten Themen.
        ],

        'similarity' => [
            // Cosine-Schwellen der Duplikatspruefung (Ticket #12).
            'network_duplicate' => 0.88,   // Artikel eines anderen Portals
            'tenant_duplicate' => 0.80,    // eigener Artikel
            'cluster_merge' => 0.92,       // zwei Kandidaten sind dasselbe Thema

            // Vorfilter vor der Cosine-Rechnung: nur Fingerprints innerhalb
            // dieser Hamming-Distanz werden ueberhaupt geladen.
            'hamming_prefilter' => 12,
        ],

        'cannibalization' => [
            // Rankt der Mandant laut Search Console bereits so gut, waere ein
            // zweiter Artikel Konkurrenz zum eigenen Bestand.
            'max_position' => (float) env('CONTENT_TOPICS_CANNIBAL_POSITION', 7.0),

            // Unterhalb so weniger Impressionen ist die Position Rauschen.
            'min_impressions' => (int) env('CONTENT_TOPICS_CANNIBAL_IMPRESSIONS', 10),
        ],

        /*
        |----------------------------------------------------------------------
        | Regional-Logik
        |----------------------------------------------------------------------
        |
        | Ein regionaler Zuschnitt entsteht nur mit Beleg. Als Beleg zaehlen
        | Rohsignale dieser Quellen sowie Portal-Eigendaten mit mindestens
        | `min_companies` gelisteten Betrieben in der Region.
        |
        */
        'region' => [
            'evidence_source_keys' => [
                'rss_feeds' => 'Foerderung oder Landesrecht',
                'foerderdatenbank' => 'Landesfoerderprogramm',
                'google_news_rss' => 'regionale Berichterstattung',
                'portal_data' => 'Portal-Eigendaten',
                'statistics' => 'amtliche Regionalstatistik',
                'dataforseo_trends' => 'regionale Suchnachfrage',
                'gsc_gap' => 'regionale Suchanfragen der Search Console',
            ],

            // Portal-Eigendaten zaehlen erst ab so vielen Betrieben als Beleg.
            'min_companies' => (int) env('CONTENT_TOPICS_REGION_MIN_COMPANIES', 5),

            // Aeltere Belege zaehlen nicht mehr.
            'evidence_max_age_days' => (int) env('CONTENT_TOPICS_REGION_EVIDENCE_DAYS', 45),
        ],

        /*
        |----------------------------------------------------------------------
        | Tagesauswahl
        |----------------------------------------------------------------------
        */
        'selection' => [
            // Reserve = reserve_factor x articles_per_day.
            'reserve_factor' => (int) env('CONTENT_TOPICS_RESERVE_FACTOR', 2),

            // Strafterme der Diversitaetsregel: ein bereits gewaehltes Cluster
            // bzw. eine bereits gewaehlte Region senkt den Auswahlwert.
            'cluster_penalty' => 0.5,
            'region_penalty' => 0.25,

            // Kandidaten unter diesem Gesamtscore werden nicht eingeplant.
            'min_total_score' => (float) env('CONTENT_TOPICS_MIN_SCORE', 20.0),

            // Ein Thema, das schon einmal ausgewaehlt oder veroeffentlicht
            // wurde, kommt so lange nicht wieder.
            'cooldown_days' => (int) env('CONTENT_TOPICS_COOLDOWN_DAYS', 90),
        ],

        /*
        |----------------------------------------------------------------------
        | YMYL-Schranken
        |----------------------------------------------------------------------
        |
        | Gilt nur fuer Mandanten mit tenant_content_settings.is_ymyl. Themen
        | mit einem Begriff aus 'informational_only' duerfen ausschliesslich
        | informierend behandelt werden (intent wird auf informational
        | gesetzt, informational_only = true). Ein Begriff aus 'blocked'
        | fuehrt zur Ablehnung — eine Handlungsempfehlung zu Medikamenten oder
        | Dosierungen gehoert nicht in einen automatisch erzeugten Ratgeber.
        |
        */
        'ymyl' => [
            'informational_only' => [
                'diagnose', 'symptom', 'krankheit', 'therapie', 'behandlung',
                'heilung', 'befund', 'erkrankung', 'beschwerden', 'schmerzen',
                'rechtsberatung', 'anwalt', 'klage', 'abmahnung', 'kuendigung',
                'vertragsrecht', 'schadensersatz', 'gericht', 'bussgeld',
                'steuererklaerung', 'steuerberatung',
            ],
            'blocked' => [
                'dosierung', 'dosis', 'einnahme', 'medikament', 'arznei',
                'tablette', 'rezeptpflichtig', 'wirkstoff', 'nebenwirkung',
                'selbstmedikation', 'absetzen', 'ueberdosis', 'milligramm',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Zurueckziehen (Depublizieren/Verwerfen)
    |--------------------------------------------------------------------------
    |
    | Zurueckgezogene Artikel verschwinden aus Sitemap, Feed und Frontend-
    | Listen. Der Fingerprint bleibt registriert, damit das Thema nicht sofort
    | neu erzeugt wird.
    |
    */

    'withdrawal' => [
        // Pflichtgrund im Klartext, Mindestlaenge fuer die Dashboard-Aktion.
        'reason_min_length' => 10,

        // 'noindex'  = Seite bleibt erreichbar, aber mit noindex-Regel
        // 'redirect' = Weiterleitung auf die Cluster-/Uebersichtsseite
        // 'gone'     = HTTP 410
        'strategy' => env('CONTENT_WITHDRAWAL_STRATEGY', 'noindex'),
        'redirect_status' => 301,

        // Fingerprint bleibt registriert (Duplikatspruefung), das Thema wird
        // fuer diese Zeit nicht erneut ausgewaehlt.
        'keep_fingerprint' => true,
        'topic_cooldown_days' => (int) env('CONTENT_WITHDRAWAL_COOLDOWN_DAYS', 180),
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

        // Veroeffentlichungsfenster (Europe/Berlin), wenn der Mandant keines
        // gepflegt hat (tenant_content_settings.publish_window_start/-end).
        // Dieselben Zeiten stehen in pipeline.schedule.publish_window; der
        // PublishScheduler liest ausschliesslich hier.
        'window' => [
            'start' => env('CONTENT_PUBLISH_WINDOW_START', '07:00'),
            'end' => env('CONTENT_PUBLISH_WINDOW_END', '19:00'),
        ],

        // Mindestabstand zweier Veroeffentlichungen desselben Mandanten.
        // Passt er nicht ins Fenster, wird er auf die groesstmoegliche
        // gleichmaessige Verteilung gestaucht.
        'min_gap_minutes' => (int) env('CONTENT_PUBLISH_MIN_GAP_MINUTES', 240),

        // Abstand des letzten Slots zum Fensterende. Dieselbe Spanne ist die
        // Frist, bis zu der ein unbelegter Slot einen Reserve-Kandidaten
        // ausloest (content:publish:due).
        'reserve_lead_minutes' => (int) env('CONTENT_PUBLISH_RESERVE_LEAD_MINUTES', 30),

        // Cache-Schluessel des Portals, die eine Veroeffentlichung veralten
        // laesst. Sie stehen hier ohne Mandantenanteil; Publisher::flushCaches
        // verwirft sie ueber App\Support\TenantCache::forget und trifft damit
        // nur den laufenden Mandanten (#79).
        'flush_cache_keys' => [
            'portal.blog.sidebar',
            'portal.latest_posts',
        ],

        /*
        |----------------------------------------------------------------------
        | IndexNow
        |----------------------------------------------------------------------
        |
        | Ein Ping an api.indexnow.org erreicht Bing, Yandex und Seznam
        | gleichzeitig. Der Schluessel wird nicht gespeichert, sondern je
        | Mandant deterministisch aus Tenant-Schluessel und APP_KEY abgeleitet
        | (IndexNowClient::key) und unter /<key>.txt ausgeliefert.
        |
        | Google nimmt IndexNow nicht an; die Google Indexing API ist auf
        | JobPosting und BroadcastEvent beschraenkt und deshalb bewusst nicht
        | angebunden — dort wirkt allein lastmod in der Sitemap.
        |
        */
        'indexnow' => [
            'enabled' => (bool) env('CONTENT_INDEXNOW_ENABLED', true),
            'endpoint' => env('CONTENT_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
            'timeout' => (int) env('CONTENT_INDEXNOW_TIMEOUT', 15),

            // Laenge des abgeleiteten Schluessels (IndexNow erlaubt 8-128
            // Zeichen aus [a-zA-Z0-9-]).
            'key_length' => 32,

            // Hoechstzahl URLs je Ping.
            'max_urls' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Artikel-Generator (#14)
    |--------------------------------------------------------------------------
    |
    | Der Generator arbeitet schrittweise: Gliederung, Abschnitte einzeln,
    | optionaler Regionalblock, FAQ, Kurzantwort, Meta. Jeder Aufruf bleibt
    | damit klein, und ein Schema-Retry kostet nur den betroffenen Schritt.
    |
    | Die Ziellaenge haengt an der Suchintention: informational braucht Tiefe,
    | transactional will schnell zur Handlung. Die Werte sind Wortzahlen des
    | fertigen Fliesstexts ohne Key-Facts, FAQ und Kurzantwort.
    |
    */

    'generation' => [

        // Ziellaenge je Suchintention [min, max]. Der Generator verteilt das
        // Budget auf die H2-Abschnitte und meldet Abweichungen.
        'target_words' => [
            'informational' => [1300, 1800],
            'commercial' => [1100, 1600],
            'transactional' => [900, 1300],
            'navigational' => [900, 1200],
        ],

        // Wortkorridor eines einzelnen Abschnitts.
        'section_words' => ['min' => 80, 'max' => 220],

        // Zeichengrenzen der Meta-Angaben.
        'title_max_chars' => 60,
        'meta_description_max_chars' => 155,

        // Erlaubte Tags im Artikel-HTML. Alles andere entfernt der
        // HtmlAssembler; der Inhalt des Tags bleibt erhalten.
        'allowed_tags' => [
            'h2', 'h3', 'p', 'ul', 'ol', 'li', 'table', 'thead', 'tbody',
            'tr', 'th', 'td', 'strong', 'em', 'a', 'blockquote',
        ],

        // Interne Verlinkung: Pflichtmenge auf eigene Kategorie-/Stadtseiten
        // und Obergrenze fuer Verweise auf bestehende Ratgeber.
        'internal_links' => [
            'min_portal' => (int) env('CONTENT_GENERATION_MIN_PORTAL_LINKS', 2),
            'max_articles' => (int) env('CONTENT_GENERATION_MAX_ARTICLE_LINKS', 3),

            // Cosine-Aehnlichkeit, ab der ein bestehender Ratgeber als
            // thematisch verwandt gilt. Ueber content.topics.similarity
            // .tenant_duplicate waere es ein Duplikat, kein Linkziel.
            'min_similarity' => (float) env('CONTENT_GENERATION_LINK_SIMILARITY', 0.55),

            // Wie viele Fingerprints der Vorfilter hoechstens laedt, bevor die
            // Cosine-Aehnlichkeit in PHP gerechnet wird.
            'candidate_pool' => (int) env('CONTENT_GENERATION_LINK_POOL', 300),
        ],

        // Hoechstens so viele Faktenschnipsel gehen in den Prompt; mehr ist
        // Eingabekosten ohne besseren Text.
        'max_fact_snippets' => 14,

        // Zeilen der Key-Facts-Tabelle. Sie entsteht ohne Modellaufruf
        // ausschliesslich aus fact_snippets.
        'key_facts_rows' => 6,

        // Versuche je Tag und Slot, bevor der Slot aufgegeben wird. Jeder
        // Fehlversuch zieht den naechsten Reserve-Kandidaten.
        'max_attempts_per_slot' => (int) env('CONTENT_GENERATION_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets: Titelbild und Infografik (#16)
    |--------------------------------------------------------------------------
    |
    | Der GenerateAssetsJob laeuft nach der Freigabe und hat drei Stufen mit
    | absteigender Verlaesslichkeit: fal.ai (Flux), Unsplash-Suche, zuletzt das
    | Branchen-Standardbild aus resources/content/fallback. Keine dieser Stufen
    | darf einen freigegebenen Artikel zurueckwerfen — ein Entwurf ohne Bild
    | wird trotzdem veroeffentlicht.
    |
    */

    'assets' => [

        // Platte und Verzeichnis. Die Platte 'public' ist mandantengetrennt
        // (FilesystemTenancyBootstrapper), der Tenant-Ordner steht trotzdem im
        // Pfad: so bleibt eine Datei auch ausserhalb ihres Kontexts zuordenbar.
        'disk' => env('CONTENT_ASSETS_DISK', 'public'),
        'base_path' => 'ratgeber',

        // Breiten der WebP-Varianten. Die erste ist die Hauptdatei, die in
        // article_drafts.hero_image_path landet.
        'widths' => [1200, 800, 400],
        'webp_quality' => (int) env('CONTENT_ASSETS_WEBP_QUALITY', 82),

        // Obergrenze der groessten Hero-Variante. Wird sie bei der
        // Wunschqualitaet gerissen, senkt der ImageOptimizer die Qualitaet in
        // Schritten bis 'min_webp_quality'.
        'hero_max_bytes' => (int) env('CONTENT_ASSETS_HERO_MAX_BYTES', 153600), // 150 KB
        'min_webp_quality' => (int) env('CONTENT_ASSETS_MIN_WEBP_QUALITY', 50),
        'quality_step' => 6,

        // Seitenverhaeltnis des Titelbilds (16:9).
        'hero_aspect' => 16 / 9,

        'hero' => [
            // Reihenfolge der Quellen. Ein Eintrag ohne Zugangsdaten wird
            // uebersprungen, nicht als Fehler gewertet.
            'sources' => ['fal', 'unsplash'],

            // Negativ-Vorgaben des Bild-Prompts. Sie stehen hier und nicht im
            // Code, weil sie redaktionell sind: kein Text im Bild, keine
            // Logos, keine erkennbaren Gesichter, keine Marken.
            'negatives' => [
                'kein Text, keine Schrift, keine Buchstaben, keine Zahlen im Bild',
                'keine Logos, keine Markenzeichen, keine Firmennamen',
                'keine erkennbaren Gesichter, keine Portraets, keine Prominenten',
                'keine Wasserzeichen, keine Collagen, keine Rahmen',
                'keine verzerrten Haende, keine unnatuerlichen Koerperhaltungen',
            ],

            // Stilvorgabe des Prompts.
            'style' => 'fotorealistisch, natuerliches Tageslicht, ruhige Bildsprache, '
                .'dokumentarisch, scharf, ohne Bildbearbeitungseffekte',

            // Mindestbreite eines Unsplash-Treffers.
            'min_source_width' => 1200,
        ],

        'infographic' => [
            // Zeichenflaeche des SVG. Die Grafik skaliert ueber viewBox mit.
            'width' => 1200,
            'height' => 675,

            // Hoechstens so viele Zeilen der Key-Facts-Tabelle. Mehr passt bei
            // dieser Hoehe nicht lesbar aufs Blatt.
            'max_rows' => (int) env('CONTENT_ASSETS_INFOGRAPHIC_ROWS', 6),

            // Farben ohne gepflegte brand_colors_json und ohne Branding des
            // Mandanten.
            'default_colors' => [
                'primary' => '#0d857b',
                'secondary' => '#114643',
                'accent' => '#3cc2b3',
                'surface' => '#ffffff',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
        ],

        // Branchen-Standardbilder. <branch> ist der Schluessel aus
        // BranchResolver; 'default' greift fuer Mandanten ohne Branche.
        'fallback' => [
            'path' => resource_path('content/fallback'),
            'default' => 'default',
        ],

        // Alt-Text. Er entsteht mit demselben Modell wie der Artikel; ohne
        // Modellantwort traegt ein deterministischer Ersatztext aus Titel und
        // Region.
        'alt_text' => [
            'max_chars' => (int) env('CONTENT_ASSETS_ALT_MAX_CHARS', 125),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Qualitaetsgate
    |--------------------------------------------------------------------------
    */

    'quality' => [
        // Gesamtscore 0-100 aus der Rubrik.
        'auto_approve_score' => (int) env('CONTENT_AUTO_APPROVE_SCORE', 85),

        // Vorgabe fuer Mandanten mit tenant_content_settings.is_ymyl. Sie
        // gilt nur, solange der Mandant keine eigene Schwelle gepflegt hat
        // (tenant_content_settings.auto_publish_threshold, #15).
        'ymyl_auto_approve_score' => (int) env('CONTENT_YMYL_AUTO_APPROVE_SCORE', 90),

        /*
        | Gewichte der vier Teilnoten des Qualitaetsgates (#15). Die Rubrik
        | wiegt am schwersten, weil sie als einzige inhaltlich urteilt; die
        | deterministischen Stufen sind Mindestanforderungen, die ein Artikel
        | ohnehin erfuellen sollte. Faellt eine Teilnote aus (etwa die Rubrik
        | bei erschoepftem Budget), werden die uebrigen Gewichte normiert.
        |
        | Achtung: Die Gewichte verschieben nur die Note. Ob freigegeben wird,
        | entscheidet zusaetzlich die Liste der blockierenden Beanstandungen —
        | eine unbelegte Zahl verhindert die Freigabe bei jeder Gewichtung.
        */
        'weights' => [
            'rubric' => (float) env('CONTENT_QUALITY_WEIGHT_RUBRIC', 0.55),
            'seo' => (float) env('CONTENT_QUALITY_WEIGHT_SEO', 0.25),
            'fact' => (float) env('CONTENT_QUALITY_WEIGHT_FACT', 0.15),
            'readability' => (float) env('CONTENT_QUALITY_WEIGHT_READABILITY', 0.05),
        ],
        'retry_score' => (int) env('CONTENT_RETRY_SCORE', 70),
        // Darunter: DraftStatus::REVIEW, also manuelle Pruefung.

        /*
        | Laenge des Artikels (Ticket #62, §4.2). Einzige Zahlenquelle ist der
        | Zielkorridor der Suchintention aus content.generation.target_words.
        | Die Toleranz ist bewusst unsymmetrisch: das Modell schreibt
        | systematisch laenger als beauftragt, und ein zu langer Text ist fuer
        | den Leser kein Mangel, ein zu kurzer fast immer duenn.
        */
        'word_tolerance' => [
            'under' => 0.10,
            'over' => 0.25,
        ],

        /*
        | Aeussere Reissleine, unabhaengig von der Intention. Darunter ist der
        | Generierungslauf abgebrochen, nicht der Artikel kurz — nur hier
        | vergibt der SeoLinter 'fail' und blockiert damit die Freigabe.
        */
        'hard_word_limits' => [
            'min' => 600,
            'max' => 3000,
        ],
        // Cosine-Aehnlichkeit ueber Voyage-Embeddings, ab der ein Thema als
        // Duplikat bzw. Kannibalisierung gilt.
        'duplicate_similarity' => 0.92,
        'cannibalization_similarity' => 0.85,
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

        // DisplayStatus::IDEA — Themenvorschlag
        'status-idea' => [
            50 => '#f4f5f7', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1',
            400 => '#94a3b8', 500 => '#7b8794', 600 => '#475569', 700 => '#334155',
            800 => '#1e293b', 900 => '#0f172a', 950 => '#020617',
        ],

        // DisplayStatus::SCHEDULED — Eingeplant
        'status-scheduled' => [
            50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd',
            400 => '#60a5fa', 500 => '#3b82f6', 600 => '#1d4ed8', 700 => '#1e40af',
            800 => '#1e3a8a', 900 => '#172554', 950 => '#0d1233',
        ],

        // DisplayStatus::GENERATING — In Erstellung. Violett, bewusst NICHT
        // die Teal-Markenfarbe des Panels.
        'status-generating' => [
            50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd',
            400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#6d28d9', 700 => '#5b21b6',
            800 => '#4c1d95', 900 => '#3b1673', 950 => '#2e1065',
        ],

        // DisplayStatus::REVIEW — Zur Pruefung
        'status-review' => [
            50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d',
            400 => '#fbbf24', 500 => '#d97706', 600 => '#b45309', 700 => '#92400e',
            800 => '#78350f', 900 => '#5c2a0c', 950 => '#451a03',
        ],

        // DisplayStatus::PUBLISHED — Veroeffentlicht
        'status-published' => [
            50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7',
            400 => '#34d399', 500 => '#059669', 600 => '#047857', 700 => '#065f46',
            800 => '#064e3b', 900 => '#04412f', 950 => '#022c22',
        ],

        // DisplayStatus::FAILED — Fehlgeschlagen
        'status-failed' => [
            50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5',
            400 => '#f87171', 500 => '#ef4444', 600 => '#b91c1c', 700 => '#991b1b',
            800 => '#7f1d1d', 900 => '#641818', 950 => '#450a0a',
        ],

        // DisplayStatus::ARCHIVED — Zurueckgezogen. Warmes Grau, damit es sich
        // von "Themenvorschlag" (kuehles Grau) unterscheiden laesst.
        'status-archived' => [
            50 => '#f8fafc', 100 => '#f1f5f9', 200 => '#e7e7e2', 300 => '#cbd5e1',
            400 => '#94a3b8', 500 => '#7c8a88', 600 => '#64716f', 700 => '#55605e',
            800 => '#3d4a48', 900 => '#2a3533', 950 => '#16211f',
        ],
    ],

];
