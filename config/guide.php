<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Themengetriebenes Ratgebersystem (app/Guide)
|--------------------------------------------------------------------------
|
| Konfiguration des Nachfolgers der Content-Pipeline: feste Themenliste,
| taegliche Aktualitaetspruefung, abschnittsweises Schreiben und
| Aktualisieren. Architektur, Job-Kette und Kostenrechnung stehen in
| docs/guide-system.md.
|
| WICHTIG: Zugangsdaten kommen ausschliesslich aus .env und werden nur hier
| gelesen. Keine Keys in der Datenbank, im Panel oder im Frontend.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Modell
    |--------------------------------------------------------------------------
    |
    | claude-sonnet-5 lehnt temperature/top_p mit HTTP 400 ab, deshalb gibt es
    | hier keine Sampling-Parameter. Die Recherche (Probe, Tiefenrecherche)
    | laeuft ausschliesslich ueber die Messages API, weil nur dort das
    | serverseitige Web-Search-Werkzeug samt Abrechnung je Suche verfuegbar
    | ist; der CLI-Treiber der alten Pipeline ist hier nicht vorgesehen.
    |
    */

    'model' => env('GUIDE_LLM_MODEL', 'claude-sonnet-5'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('GUIDE_LLM_MAX_TOKENS', 16000),
        'effort' => env('GUIDE_LLM_EFFORT', 'medium'),
        'timeout' => (int) env('GUIDE_LLM_TIMEOUT', 300),
        'prompt_cache' => (bool) env('GUIDE_LLM_PROMPT_CACHE', true),

        // Erzwungener Tool-Use ('emit') vertraegt sich nicht mit erweitertem
        // Denken; deshalb aus. Leer = Feld wird nicht gesendet.
        'thinking' => env('GUIDE_LLM_THINKING', 'disabled'),

        // Werkzeug fuer die strukturierte Ausgabe und Wiederholungen bei
        // schemawidriger Antwort (insgesamt 1 + schema_retries Versuche).
        'tool_name' => 'emit',
        'schema_retries' => 2,

        // Hoechstzahl der Fortsetzungen nach stop_reason 'pause_turn', bevor
        // research() aufgibt und 'emit' erzwingt.
        'max_continuations' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web-Search
    |--------------------------------------------------------------------------
    |
    | Serverseitiges Werkzeug der Messages API. max_uses begrenzt die Zahl der
    | Suchen je Anfrage und ist damit der wichtigste Kostenhebel:
    | 10 USD je 1.000 Suchen plus Suchergebnisse als Input-Tokens.
    |
    */

    'web_search' => [
        'tool_type' => env('GUIDE_WEB_SEARCH_TOOL', 'web_search_20260209'),
        'tool_name' => 'web_search',
        'max_uses_probe' => (int) env('GUIDE_WEB_SEARCH_MAX_USES_PROBE', 2),
        'max_uses_deep' => (int) env('GUIDE_WEB_SEARCH_MAX_USES_DEEP', 6),
        'user_location' => [
            'type' => 'approximate',
            'country' => 'DE',
            'timezone' => 'Europe/Berlin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aktualitaetsrecherche (#8)
    |--------------------------------------------------------------------------
    |
    | probe_confidence_min: Eine Probe ohne Befund beendet den Lauf nur ab
    |   dieser Sicherheit als unchanged; darunter folgt die Tiefenrecherche.
    | stale_after_months: Aeltere Quellen zaehlen nur, wenn es fuer den Fakt
    |   keine neuere gibt; der Fakt traegt dann stale_source.
    | allowed_domains_min: allowed_domains nur ab so vielen Whitelist-Domains,
    |   sonst ausschliesslich blocked_domains (zu enge Listen liefern nichts).
    | trust_patterns: Einstufung von Domains ausserhalb der Whitelist
    |   (Regex auf den Hostnamen ohne www.). Alles ohne Treffer ist 'other'
    |   und belegt keinen Fakt. Die Selbstauskunft des Modells zaehlt nicht.
    |
    */

    'research' => [
        'probe_confidence_min' => 0.7,
        'stale_after_months' => 24,
        'allowed_domains_min' => 10,
        'trust_patterns' => [
            'official' => [
                '/(^|\.)bund\.de$/',
                '/(^|\.)(bundesregierung|bundestag|bundesrat|gesetze-im-internet|bundesanzeiger|destatis)\.de$/',
                '/(^|\.)(bafa|kfw|bmwk|bmas|bmg|bmuv|bmdv|bmj|bzga|rki|bfarm|bundesnetzagentur|umweltbundesamt|bsi|dguv)\.de$/',
                '/(^|\.)(bayern|nrw|niedersachsen|hessen|sachsen|sachsen-anhalt|thueringen|brandenburg|berlin|hamburg|bremen|saarland|schleswig-holstein|rlp|mv-regierung|baden-wuerttemberg|service-bw)\.de$/',
                '/(^|\.)(europa\.eu|eur-lex\.europa\.eu)$/',
                '/(^|\.)din\.de$/',
            ],
            'trade' => [
                '/(^|\.)(ihk|dihk|zdh|hwk)[a-z0-9-]*\.de$/',
                '/(^|\.)handwerkskammer[a-z0-9-]*\.de$/',
                '/(^|\.)[a-z0-9-]*innung[a-z0-9-]*\.de$/',
                '/(^|\.)(verbraucherzentrale|vzbv|test)\.de$/',
                '/(^|\.)[a-z0-9-]*kammer[a-z0-9-]*\.de$/',
            ],
            'press' => [
                '/(^|\.)(tagesschau|zdf|br|ndr|wdr|swr|mdr|hr|rbb24|deutschlandfunk|dw)\.de$/',
                '/(^|\.)(spiegel|zeit|sueddeutsche|welt|handelsblatt|haufe|heise|finanztip)\.de$/',
                '/(^|\.)(faz|dasinvestment)\.net$/',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget
    |--------------------------------------------------------------------------
    |
    | Herleitung in docs/guide-system.md, Abschnitt Kostenmodell. Gezaehlt
    | werden nur Laeufe des Ratgebersystems (llm_usage_logs.operation mit
    | Praefix 'guide.'). Ein Wert von 0 schaltet die jeweilige Pruefung ab,
    | wie in der bestehenden Pipeline.
    |
    */

    'budget' => [
        'daily_usd_total' => (float) env('GUIDE_BUDGET_DAILY_USD_TOTAL', 60.0),
        'daily_usd_per_tenant' => (float) env('GUIDE_BUDGET_DAILY_USD_PER_TENANT', 5.0),

        // Reissleine je Lauf. Erwartet: Neuanlage ~0,78 USD, Aktualisierung ~0,61 USD.
        'max_usd_per_run' => (float) env('GUIDE_BUDGET_MAX_USD_PER_RUN', 1.5),

        'warn_threshold' => 0.80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kostenschaetzung im Dashboard
    |--------------------------------------------------------------------------
    |
    | Erwartungswerte je Lauf aus dem Kostenmodell (docs/guide-system.md,
    | "Kosten je Lauf und je Thema", inkl. 15 % Aufschlag). Nur fuer die
    | Kostenzeilen in Bestaetigungsdialogen (Jetzt ausfuehren, Entsperren);
    | gebucht wird weiter ueber llm_usage_logs.
    |
    */

    'estimates' => [
        'create_usd' => 0.78,
        'update_usd' => 0.61,
        'check_usd' => 0.14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Preise (USD)
    |--------------------------------------------------------------------------
    |
    | Anthropic-Preisliste, abgerufen am 21.09.2026 von
    | https://platform.claude.com/docs/en/about-claude/pricing
    | Grundlage fuer cost_usd in llm_usage_logs. Bei Preisaenderung hier und
    | in docs/guide-system.md nachziehen.
    |
    */

    'pricing' => [
        'claude-sonnet-5' => [
            'input_per_mtok' => 2.00,
            'output_per_mtok' => 10.00,
            'cache_write_5m_per_mtok' => 2.50,
            'cache_write_1h_per_mtok' => 4.00,
            'cache_read_per_mtok' => 0.20,
        ],
        'web_search_per_1000' => 10.00,
        'web_fetch_per_1000' => 0.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tageslauf
    |--------------------------------------------------------------------------
    |
    | Innerhalb dieses Fensters stoesst DispatchDueTopicsJob faellige Themen
    | an. Laeufe, die bei Fensterende noch in der Kette stecken, laufen zu
    | Ende; neue werden erst im naechsten Fenster angestossen.
    |
    */

    'timezone' => env('GUIDE_TIMEZONE', 'Europe/Berlin'),
    'run_window_start' => env('GUIDE_RUN_WINDOW_START', '02:00'),
    'run_window_end' => env('GUIDE_RUN_WINDOW_END', '07:00'),

    'schedule' => [
        // Regelabstand der Aktualitaetspruefung je Thema; #9 darf je Kategorie
        // bis auf min_probe_interval_days verkuerzen.
        'probe_interval_days' => (int) env('GUIDE_PROBE_INTERVAL_DAYS', 7),
        'min_probe_interval_days' => (int) env('GUIDE_MIN_PROBE_INTERVAL_DAYS', 1),

        // Hoechstzahl neu anzulegender Artikel je Tenant und Tag (Aufbauphase).
        'max_creates_per_tenant_per_day' => (int) env('GUIDE_MAX_CREATES_PER_TENANT_PER_DAY', 5),

        // Ab so vielen Fehlschlaegen in Folge wird ein Thema pausiert und ein
        // Alarm erzeugt (#9); bis dahin naechster Versuch am Folgetag.
        'max_consecutive_failures' => 5,

        // Bei knappem Tagesbudget laufen Themen ohne Artikel und Themen mit
        // priority 1 bis zu diesem Wert zuerst (1 = hoechste, 0 = ohne Angabe).
        'preferred_priority_max' => 2,

        // Laeufe haengen, wenn sie laenger als diese Zeit in einem In-Flight-Status
        // stehen (#13). Liegt ueber dem Job-Timeout (15 Minuten), damit der
        // Watchdog nie einen noch arbeitenden Job doppelt anstoesst.
        'stuck_after_minutes' => (int) env('GUIDE_STUCK_AFTER_MINUTES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tages-Orchestrator (#13)
    |--------------------------------------------------------------------------
    |
    | watchdog.every_minutes: Takt von `guide:daily --stage=watchdog`.
    | watchdog.max_restarts: So oft setzt der Watchdog einen haengenden Lauf
    |   neu an; beim naechsten Haengen endet er mit failed.
    | report_at: Tagesbericht an die content_users mit Rolle owner.
    | failure_alert_ratio: Alarm, wenn mehr als dieser Anteil der Laeufe eines
    |   Tenants an einem Tag fehlgeschlagen ist.
    | tenant_offset_max_seconds: Obergrenze des Zufallsversatzes je Tenant,
    |   damit nicht alle Portale ihre Themen zur selben Sekunde starten.
    |
    */

    'orchestrator' => [
        'watchdog' => [
            'every_minutes' => (int) env('GUIDE_WATCHDOG_EVERY_MINUTES', 10),
            'max_restarts' => (int) env('GUIDE_WATCHDOG_MAX_RESTARTS', 2),
        ],
        'report_at' => env('GUIDE_REPORT_AT', '20:00'),
        'failure_alert_ratio' => 0.10,
        'tenant_offset_max_seconds' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallelitaet (#13)
    |--------------------------------------------------------------------------
    |
    | Redis-Funnel um jeden Job, der das Modell aufruft (Probe, Recherche,
    | Schreiben, Qualitaetsgate): hoechstens per_tenant gleichzeitig je
    | Tenant und total ueber alle Tenants, damit die Anthropic-Rate-Limits
    | halten. Ein Job ohne freien Platz wird mit release() zurueckgestellt.
    | rate_limit_backoff: release() nach HTTP 429 bzw. Provider-Ausfall,
    |   exponentiell je Versuch (base * 2^(Versuch-1)) bis max, plus Jitter.
    |
    */

    'concurrency' => [
        'enabled' => (bool) env('GUIDE_CONCURRENCY_ENABLED', true),
        'per_tenant' => (int) env('GUIDE_CONCURRENCY_PER_TENANT', 3),
        'total' => (int) env('GUIDE_CONCURRENCY_TOTAL', 12),
        'redis_connection' => env('GUIDE_CONCURRENCY_REDIS', 'default'),
        'wait_seconds' => 30,
        'rate_limit_backoff' => [
            'base_seconds' => 60,
            'max_seconds' => 1800,
            'jitter_seconds' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schreibverhalten
    |--------------------------------------------------------------------------
    |
    | force_rewrite: Die Probe entfaellt, jeder faellige Lauf eines Themas mit
    |   Artikel wird ein update ueber alle Abschnitte der gesperrten
    |   Gliederung, unabhaengig vom facts_hash (#9, ChangeDetector).
    | auto_lock_outline: Die vorgeschlagene Gliederung wird ohne Pruefung im
    |   Dashboard gesperrt; sonst wartet das Thema in outline_pending.
    |
    */

    'force_rewrite' => (bool) env('GUIDE_FORCE_REWRITE', false),
    'auto_lock_outline' => (bool) env('GUIDE_AUTO_LOCK_OUTLINE', false),

    /*
    |--------------------------------------------------------------------------
    | Artikel-Writer (#10)
    |--------------------------------------------------------------------------
    |
    | allowed_tags: Whitelist fuer body_html; die H2/H3 setzt ausschliesslich
    |   der HtmlAssembler aus der gesperrten Gliederung.
    | outline: Grenzen des Gliederungsvorschlags (ProposeOutlineJob).
    | links: Pflichtmenge interner Links auf Portalseiten (CTA-Ziel aus
    |   tenant_guide_settings.category_cta_mapping_json zuerst) und
    |   Hoechstzahl verwandter Themen derselben Kategorie.
    | title_max/meta_description_max: harte Grenzen, der gespeicherte Titel
    |   traegt das laufende Jahr als {{year}} (Ersetzung zur Renderzeit).
    |
    */

    'writing' => [
        'allowed_tags' => [
            'h2', 'h3', 'p', 'ul', 'ol', 'li', 'table', 'thead', 'tbody',
            'tr', 'th', 'td', 'strong', 'em', 'a', 'blockquote',
        ],
        'outline' => [
            'min_h2' => 4,
            'max_h2' => 7,
            'max_h3_per_h2' => 4,
        ],
        'links' => [
            'min_portal' => 2,
            'max_related' => 3,
        ],
        'key_facts_rows' => 8,
        'title_max' => 60,
        'meta_description_min' => 100,
        'meta_description_max' => 155,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    'queues' => [
        'dispatch' => 'guide-dispatch',
        'research' => 'guide-research',
        'write' => 'guide-write',
        'publish' => 'guide-publish',
        'assets' => 'guide-assets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Titelbild je Thema (#20)
    |--------------------------------------------------------------------------
    |
    | GenerateTopicImageJob erzeugt das Bild genau einmal, nach der ersten
    | Veroeffentlichung eines Themas. Quelle ist fal.ai (Flux); scheitert der
    | Aufruf oder fehlt FAL_API_KEY, greift das Branchen-Standardbild aus
    | fallback.path (<branch>.webp, sonst default.webp). Der Optimizer schneidet
    | auf 16:9, kodiert WebP in widths und senkt bei der groessten Variante die
    | Qualitaet, bis hero_max_bytes eingehalten ist. Abgelegt wird unter
    | guide/<tenant-uuid>/<topic-slug>/ auf der (mandantengetrennten) Platte.
    | Kosten: per_image je Aufruf in llm_usage_logs (provider 'images',
    | operation 'guide.hero_image'), zaehlt gegen config('guide.budget').
    |
    */

    'images' => [
        'disk' => 'public',
        'base_path' => 'guide',
        'widths' => [1200, 800, 400],
        'aspect' => 16 / 9,
        'webp_quality' => 82,
        'min_webp_quality' => 50,
        'quality_step' => 6,
        'hero_max_bytes' => 153600, // 150 KB
        'max_upload_kb' => 10240,

        'fal' => [
            'api_key' => env('FAL_API_KEY'),
            'base_url' => env('FAL_BASE_URL', 'https://fal.run'),
            'model' => env('GUIDE_IMAGE_MODEL', 'fal-ai/flux/schnell'),
            'image_size' => 'landscape_16_9',
            'num_inference_steps' => 4,
            'timeout' => 120,
            'per_image_usd' => (float) env('GUIDE_IMAGE_PRICE_USD', 0.003),
        ],

        // Redaktionelle Vorgaben des Bild-Prompts. Die Negativ-Vorgaben sind
        // Pflicht: kein Text, keine Logos, keine erkennbaren Gesichter, keine
        // Marken.
        'style' => 'fotorealistisch, natürliches Tageslicht, ruhige Bildsprache, dokumentarisch, scharf, ohne Bildbearbeitungseffekte',
        'negatives' => [
            'kein Text, keine Schrift, keine Buchstaben, keine Zahlen im Bild',
            'keine Logos, keine Markenzeichen, keine Firmennamen',
            'keine erkennbaren Gesichter, keine Porträts, keine Prominenten',
            'keine Marken, keine Produktnamen, keine Wasserzeichen',
        ],

        'alt_max_chars' => 125,

        'fallback' => [
            'path' => resource_path('guide/fallback'),
            'default' => 'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry und Circuit Breaker je externem Provider
    |--------------------------------------------------------------------------
    |
    | 3 Versuche, exponentiell mit Jitter; wiederholt werden nur 429, 5xx und
    | Verbindungsfehler. Nach failure_threshold Fehlern in Folge oeffnet der
    | Breaker (provider_states.status = open) fuer open_minutes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Veroeffentlichung (#12)
    |--------------------------------------------------------------------------
    |
    | keep_versions: Hoechstzahl der Fassungen je Artikel; aeltere entfernt
    |   guide:versions:prune (die erste und die veroeffentlichte bleiben immer).
    | indexnow: Ping an api.indexnow.org nach create, update und Rollback.
    |   Der Schluessel wird je Mandant aus APP_KEY abgeleitet (derselbe wie in
    |   der alten Pipeline) und unter /<key>.txt ausgeliefert. Fehler werden
    |   protokolliert und blockieren nie.
    |
    */

    'publishing' => [
        'keep_versions' => 30,
        'indexnow' => [
            'enabled' => (bool) env('GUIDE_INDEXNOW_ENABLED', true),
            'endpoint' => 'https://api.indexnow.org/indexnow',
            'timeout' => 15,
            'key_length' => 32,
            'max_urls' => 100,
        ],
    ],

    'resilience' => [
        'retry' => [
            'attempts' => 3,
            'base_delay_ms' => 2000,
            'multiplier' => 3,
            'jitter_ms' => 500,
        ],
        'circuit_breaker' => [
            'failure_threshold' => 5,
            'open_minutes' => 15,
        ],
    ],

];
