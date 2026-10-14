<?php

declare(strict_types=1);

/*
 * Konfiguration fuer `php artisan sun:db:pull` (#1).
 *
 * Der Befehl holt die zentrale Datenbank und alle Tenant-Datenbanken lesend
 * vom Produktionsserver auf den Laptop. Hintergrund, Sicherheitsmodell und
 * der Server-Befund aus #2 stehen in docs/db-pull.md.
 *
 * Verbindlich sind die SYNC_*-Namen aus #2. Geheimnisse stehen ausschliesslich
 * in der lokalen .env und werden hier ohne Vorgabewert gelesen (Projektregel,
 * siehe CLAUDE.md). Nicht-geheime Werte mit `?:` statt `env(..., 'vorgabe')`:
 * die Schluessel stehen in .env.example leer, ein zweiter Parameter wuerde von
 * einer kopierten .env auf '' ueberschrieben.
 */

return [

    /*
     * SSH-Zugang (#2). 'sun-sync' ist ein eigener Eintrag in ~/.ssh/config mit
     * dem Schluessel ~/.ssh/sun_sync und dem Benutzer sanitaerfinden — nicht
     * der Serverzugang 'sun' (root). Port, Benutzer und Schluessel sind nur
     * noetig, wenn der Aufruf ohne ssh_config-Eintrag laufen soll.
     */
    'ssh_host' => env('SYNC_SSH_HOST') ?: 'sun-sync',
    'ssh_port' => env('SYNC_SSH_PORT') ?: null,
    'ssh_user' => env('SYNC_SSH_USER') ?: null,
    'ssh_key_path' => env('SYNC_SSH_KEY_PATH') ?: null,

    /*
     * Read-only-MySQL-Benutzer: sun_sync@localhost, Rechte nur
     * SELECT, SHOW VIEW auf `sun`.* und `tenant\_%`.* (Abnahme in
     * docs/db-pull.md). Der Anwendungsbenutzer der Live-.env wird nicht
     * verwendet.
     */
    'db_user' => env('SYNC_DB_USER'),
    'db_password' => env('SYNC_DB_PASSWORD'),

    /*
     * Live-storage/ als Basis fuer den Storage-Sync (--storage). Der
     * Anwendungspfad wird daraus abgeleitet (ein Verzeichnis darueber), weil
     * die zentrale Live-.env dort liegt.
     */
    'remote_storage_path' => env('SYNC_REMOTE_STORAGE_PATH') ?: '/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage',

    /*
     * Lokaler MySQL-Client fuer den Import (Homebrew-MySQL ist lokal ueblich).
     */
    'local_mysql_cmd' => env('SYNC_LOCAL_MYSQL_CMD') ?: 'mysql',
    'local_mysqldump_cmd' => env('SYNC_LOCAL_MYSQLDUMP_CMD') ?: 'mysqldump',

    /*
     * Sicherung des LOKALEN Standes vor dem `drop database` der Zentrale
     * (#8). Nur lokal — Live ist daran nicht beteiligt.
     *
     * Hintergrund: in der lokalen `sun` haengen die Test-Tenants mit
     * `.test`-Domains, ein voller Pull ersetzt sie durch die Live-Portale.
     * Deshalb geht vorher ein Dump nach `storage/app/db-pull/`.
     *
     * `free_factor`: geforderter freier Plattenplatz als Anteil der
     * gemessenen Datenbankgroesse. Die lokale `sun` ist ca. 7,5 GB gross,
     * der Dump davon 6,8 GB — gzip drueckt SQL-Text aber auf 15–25 %, die
     * Haelfte der Rohgroesse ist also reichlich Luft und schlaegt trotzdem
     * zu, bevor die Platte vollaeuft.
     *
     * `timeout`: der Dump laeuft einige Minuten (7,5 GB ueber den lokalen
     * Socket); zwei Stunden sind die Reissleine gegen einen haengenden
     * Client, keine Erwartung.
     */
    'local_backup' => [
        'directory' => 'app/db-pull',
        'keep' => 2,
        'free_factor' => 0.5,
        'timeout' => 7200,
    ],

    /*
     * Gleichzeitig geholte Tenant-Datenbanken. Mehr als drei bringt am
     * Hetzner-Uplink nichts und belastet nur Live-MySQL, deshalb eine harte
     * Obergrenze (--parallel wird darauf gekappt).
     */
    'parallel' => 2,
    'parallel_max' => 4,

    /*
     * Auf dem Server laeuft MariaDB 11.4. In Aufrufen und Protokollen die
     * mariadb-Namen verwenden: die mysql*-Aliasnamen schreiben bei jedem
     * Aufruf eine Deprecation-Warnung nach stderr und sehen im Protokoll wie
     * ein Fehler aus.
     */
    'remote_dump_bin' => 'mariadb-dump',
    'remote_client_bin' => 'mariadb',

    /*
     * Nicht konfigurierbar, weil kein Umgebungswert: Anonymisierung ist
     * Vorgabe (Schalter ist --no-anonymize), und unter 20 gefundenen
     * Tenant-Datenbanken bricht der Lauf ab — lieber kein Pull als ein halber
     * (Lehre aus #39; am 14.10.2026 waren es 23).
     */
    'anonymize' => true,
    'min_tenant_databases' => 20,

    /*
     * Lokales Einheitspasswort nach der Anonymisierung (#5). Steht hier und
     * nicht als Hash in den Regeln, damit kein Hash im Repo liegt — der Hash
     * wird je Lauf erzeugt und als Bindung ':password_hash' eingesetzt.
     * Gilt fuer jeden anonymisierten Zugang (zentral und je Portal).
     */
    'anonymize_password' => env('SYNC_LOCAL_PASSWORD') ?: 'passwort',

    /*
     * Zeilen je UPDATE. Die Anonymisierung laeuft in ID-Fenstern, damit auf
     * `reviews` (~1,7 Mio. Zeilen) keine Transaktion ueber die ganze Tabelle
     * entsteht und der Lauf abbrechbar bleibt.
     */
    'anonymize_batch_size' => 20000,

    /*
     * Fester Administrator, der NACH der Anonymisierung angelegt bzw.
     * zurueckgesetzt wird (#5) — sonst gibt es nach dem Pull lokal keinen
     * Zugang mehr zu Filament, weil alle echten Konten ueberschrieben sind.
     * Geschrieben wird per SQL direkt in die zentrale Datenbank: is_admin,
     * Rolle `admin` (Spatie) und ein bekanntes Passwort.
     */
    'anonymize_admin' => [
        'name' => env('SYNC_LOCAL_ADMIN_NAME') ?: 'Enes',
        'email' => env('SYNC_LOCAL_ADMIN_EMAIL') ?: 'enes@sun.test',
        'password' => env('SYNC_LOCAL_ADMIN_PASSWORD') ?: null,
        'role' => 'admin',
    ],

    /*
     * Namen aus config/tenancy.php: Datenbanken heissen `tenant_<uuid>`
     * (database.prefix), die Storage-Verzeichnisse `tenant<uuid>`
     * (filesystem.suffix_base) — ohne Unterstrich. Beide Werte hier getrennt,
     * damit keiner aus dem anderen abgeleitet wird.
     */
    'tenant_database_prefix' => 'tenant_',
    'tenant_storage_prefix' => 'tenant',

    /*
     * Sekunden je Datenbank. Gemessen (#2): die groesste Tenant-Datenbank
     * (1 673 MB) ist in 7 s uebertragen, der lokale Import ist der langsamere
     * Teil. Eine Stunde ist reichlich Luft.
     */
    'timeout' => 3600,

    /*
     * Ausschluesse fuer den Storage-Sync (--storage, #6). rsync bekommt sie
     * als --exclude-from. Gespiegelt werden Logos, Firmenbilder und Uploads;
     * Sicherungen, Protokolle, Cache und angefangene Uploads gehoeren nicht
     * auf den Laptop und sind der groesste Teil des Volumens.
     *
     * Muster mit fuehrendem / gelten nur direkt unter app/public, Muster
     * ohne / an jeder Stelle des Baums.
     */
    'storage_exclude' => [
        '/backups/',
        '/backup/',
        '/exports/',
        'livewire-tmp/',
        'tmp/',
        'temp/',
        'cache/',
        'framework/',
        'logs/',
        '*.log',
        '*.sql',
        '*.sql.gz',
        '*.zip',
        '*.tar',
        '*.tar.gz',
        '.DS_Store',
        '*.part',
        '*.partial',
    ],

    /*
     * Begrenzungen fuer --storage (#9). Befund vom 14.10.2026: der
     * Tenant-Storage ist viel groesser als angenommen — ein Portal haelt
     * 127 GB echte Firmenbilder (photos 59 GB, media 68 GB), ein zweites
     * 33 GB. Ein voller Spiegel aller Portale passt auf keinen Laptop und
     * zieht die Produktion (rotierende HDDs) stundenlang runter; die
     * Ausschluesse oben greifen dort nicht, weil es echte Inhalte sind.
     *
     * 'storage_path': Vorgabe-Unterbaum von app/public (leer = alles), zur
     * Laufzeit mit --storage-path=media/logos. Quelle UND Ziel wandern in
     * den Unterbaum, --delete wirkt also nur dort.
     *
     * 'storage_max_size': rsync --max-size, laesst grosse Originale
     * draussen (z.B. '2M'). Achtung: zu grosse Dateien gelten fuer rsync
     * als Ausschluss und werden mit --delete LOKAL NICHT geloescht (es wird
     * bewusst kein --delete-excluded gesetzt) — ein Lauf mit Begrenzung
     * raeumt einen frueheren Vollspiegel also nicht ab.
     *
     * 'storage_warn_gigabytes': ab diesem zu uebertragenden Volumen je
     * Portal wird vor dem Lauf ein Probelauf gemacht und rueckgefragt,
     * statt stumpf loszulaufen (0 schaltet die Vorwarnung ab, --force
     * ueberspringt die Rueckfrage).
     */
    'storage_path' => env('SYNC_STORAGE_PATH') ?: '',
    'storage_max_size' => env('SYNC_STORAGE_MAX_SIZE') ?: null,
    'storage_warn_gigabytes' => 5,

    /*
     * rsync-Drossel fuer --storage. /home der Produktion liegt auf zwei
     * rotierenden HDDs; ungebremst zieht der Sync die Portale runter.
     */
    'bwlimit' => env('SYNC_BWLIMIT') ?: '20m',

    /*
     * Ein Lock aelter als diese Zeitspanne gilt als verwaist (abgebrochener
     * Lauf) und wird mit Hinweis uebernommen.
     */
    'lock_stale_after_minutes' => 120,

    /*
     * Tabellen, von denen nur die STRUKTUR geholt wird (#4): Protokoll-,
     * Cache-, Warteschlangen- und Token-Tabellen. Die Struktur kommt immer
     * mit, damit lokal keine Tabelle fehlt und kein Code auf eine fehlende
     * Tabelle laeuft — uebersprungen werden nur die Daten.
     *
     * `tracking_events` (~9,4 Mio. Zeilen, nur IP und User-Agent) ist der
     * groesste Posten des Gesamtvolumens und lokal wertlos (#2-Befund).
     *
     * Platzhalter `*` und `?` sind erlaubt (`telescope_*`). Aufgeloest wird
     * gegen die Tabellen der jeweiligen Live-Datenbank
     * (App\Services\DbPull\TableFilter), nicht an `mariadb-dump`
     * weitergegeben: `--ignore-table` kennt keine Platzhalter und bricht bei
     * einer Tabelle ab, die es dort nicht gibt. Namen, die auf Live fehlen,
     * sind deshalb unschaedlich — die Liste darf vorsorglich wachsen.
     *
     * Zur Laufzeit ergaenzt `--skip-data=a,b` diese Liste;
     * `--only-tables=a,b` setzt sie ausser Kraft.
     */
    'skip_data' => [
        'central' => [
            'telescope_*',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
            'cache',
            'cache_locks',
            'csp_reports',
            'llm_usage_logs',
            'activity_log',
            'notifications',
            'password_reset_tokens',
            'password_resets',
            'content_password_reset_tokens',
            'one_time_passwords',
            'two_factor_authentications',
            'personal_access_tokens',
            'metric_data',
        ],
        'tenant' => [
            'tracking_events',
            'article_metrics_raw',
            'turnstile_verifications',
            'company_description_backups',
            'telescope_*',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
            'cache',
            'cache_locks',
            'activity_log',
            'notifications',
            'password_reset_tokens',
            'personal_access_tokens',
        ],
    ],

    /*
     * Anonymisierung, ausgefuehrt LOKAL nach dem Import (nie auf Live).
     *
     * Je Tabelle ein Satz Spalten mit einem SQL-Ausdruck. Werte in der Form
     * ':name' sind Platzhalter, die der Befehl bindet (derzeit
     * ':password_hash' — ein zur Laufzeit erzeugter Hash, damit kein Hash im
     * Repo liegt). Tabellen und Spalten, die es lokal nicht gibt, werden
     * uebersprungen; die Listen duerfen also grosszuegig sein.
     *
     * Die Ausdruecke sind deterministisch ueber die Zeilen-ID: ein zweiter
     * Pull liefert dieselben Werte, lokale Lesezeichen passen weiter.
     *
     * Grundlage ist der Personendaten-Befund in docs/db-pull.md (#2).
     */
    'anonymize_rules' => [
        'central' => [
            'users' => [
                'name' => "CONCAT('Nutzer ', id)",
                'public_name' => "CONCAT('Nutzer ', id)",
                'email' => "CONCAT('nutzer', id, '@example.test')",
                'password' => ':password_hash',
                'remember_token' => 'NULL',
                'phone_number' => "'+49 000 0000000'",
                'registration_ip' => 'NULL',
                'registration_ip_hash' => 'NULL',
                'registration_user_agent' => 'NULL',
                // Freitext aus dem Admin-Panel, kann alles enthalten.
                'notes' => 'NULL',
                // Tokens und 2FA-Geheimnisse: lokal wertlos, aber echte
                // Zugangsmittel. Spalten, die es nicht gibt, fallen weg.
                'api_token' => 'NULL',
                'two_factor_secret' => 'NULL',
                'two_factor_recovery_codes' => 'NULL',
            ],
            'addresses' => [
                'first_name' => "'Vorname'",
                'last_name' => "CONCAT('Nachname ', id)",
                'line1' => "'Teststrasse 1'",
                'line2' => 'NULL',
                'phone' => "'+49 000 0000000'",
                'tax_number' => 'NULL',
            ],
            'invitations' => [
                'email' => "CONCAT('einladung', id, '@example.test')",
                'token' => "CONCAT('einladung-', id)",
            ],
            'user_stripe_data' => [
                'stripe_customer_id' => "CONCAT('cus_pull', id)",
                'stripe_payment_method_id' => "CONCAT('pm_pull', id)",
            ],
            'content_users' => [
                'name' => "CONCAT('Redaktion ', id)",
                'email' => "CONCAT('redaktion', id, '@example.test')",
                'password' => ':password_hash',
                // Angemeldete Sitzung eines Redaktionskontos: lokal wertlos,
                // live ein Zugangsmittel (vom Abdeckungstest gefunden, #10).
                'remember_token' => 'NULL',
            ],

            // Die IP hinter einer Stimme ist ein Personenbezug und lokal
            // ohne jeden Nutzen (vom Abdeckungstest gefunden, #10).
            'roadmap_item_user_upvotes' => [
                'ip_address' => 'NULL',
            ],
            /*
             * Keine Personendaten, aber echte Live-Geheimnisse: die
             * Turnstile-Schluessel der Portale duerfen nicht auf dem Laptop
             * liegen (#2-Befund). Lokal gilt stattdessen der Testschluessel
             * aus config/turnstile.php.
             */
            'tenant_turnstile_settings' => [
                'secret_key' => 'NULL',
                'site_key' => 'NULL',
            ],
        ],
        'tenant' => [
            'companies' => [
                'email' => "CONCAT('firma', id, '@example.test')",
                'tel' => "'+49 000 0000000'",
                'contact_email' => "CONCAT('kontakt', id, '@example.test')",
                'contact_phone' => "'+49 000 0000000'",
                'contact_name' => "CONCAT('Kontakt ', id)",
                'created_ip' => 'NULL',
                'created_ip_hash' => 'NULL',
                'created_user_agent' => 'NULL',
            ],
            'company_inquiries' => [
                'contact_name' => "CONCAT('Anfrage ', id)",
                'contact_email' => "CONCAT('anfrage', id, '@example.test')",
                'contact_phone' => "'+49 000 0000000'",
            ],
            'company_leads' => [
                'contact_name' => "CONCAT('Lead ', id)",
                'contact_email' => "CONCAT('lead', id, '@example.test')",
                'contact_phone' => "'+49 000 0000000'",
            ],
            'company_edit_suggestions' => [
                'reporter_name' => "CONCAT('Melder ', id)",
                'reporter_email' => "CONCAT('melder', id, '@example.test')",
                'reason' => "CONCAT('Anonymisierte Begruendung ', id, '.')",
                'ip_address' => 'NULL',
            ],
            'job_applications' => [
                'applicant_name' => "CONCAT('Bewerber ', id)",
                'applicant_email' => "CONCAT('bewerber', id, '@example.test')",
                'applicant_phone' => "'+49 000 0000000'",
                'message' => "CONCAT('Anonymisierte Bewerbung ', id, '.')",
                'ip_address' => 'NULL',
            ],
            'newsletter_subscribers' => [
                'email' => "CONCAT('newsletter', id, '@example.test')",
                'ip_address' => 'NULL',
            ],
            'reviews' => [
                'author_name' => "CONCAT('Verfasser ', id)",
                'author_email' => "CONCAT('verfasser', id, '@example.test')",
                'moderated_by_name' => 'NULL',
                'ip_address' => 'NULL',
            ],
            'claim_requests' => [
                'contact_name' => "CONCAT('Antragsteller ', id)",
                'contact_email' => "CONCAT('antrag', id, '@example.test')",
                'contact_phone' => "'+49 000 0000000'",
                'comment' => "CONCAT('Anonymisierter Kommentar ', id, '.')",
            ],
        ],
    ],

    /*
     * Geheimnisse, die in einer JSON-Spalte stecken (die data-Spalte der
     * Tenants haelt u.a. lead_funnel_token und lead_webhook_secret, siehe
     * App\Models\Tenant). Lokal werden die Schluessel mit JSON_REMOVE
     * entfernt, statt einen Ersatzwert hineinzuschreiben.
     */
    /*
     * Tabellen, die lokal LEER sein sollen. Reine Zugangsmittel und
     * Sitzungsdaten lassen sich nicht sinnvoll anonymisieren — ein
     * zurueckgesetzter Token ist ein wertloser Token, also weg damit.
     * Die Tabellen kommen bereits ohne Daten (`skip_data`); das TRUNCATE ist
     * die zweite Sicherung fuer einen Teil-Pull mit `--tables`.
     */
    'anonymize_truncate' => [
        'central' => [
            'personal_access_tokens',
            'password_reset_tokens',
            'password_resets',
            'content_password_reset_tokens',
            'one_time_passwords',
            'two_factor_authentications',
            'sessions',
        ],
        'tenant' => [
            'personal_access_tokens',
            'password_reset_tokens',
            'sessions',
            'turnstile_verifications',
            'tracking_events',
        ],
    ],

    'anonymize_json' => [
        'central' => [
            'tenants' => [
                'data' => ['lead_funnel_token', 'lead_webhook_secret'],
            ],
        ],
        'tenant' => [],
    ],

];
