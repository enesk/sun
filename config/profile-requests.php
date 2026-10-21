<?php

/*
|--------------------------------------------------------------------------
| Zentrale Profil-Anfragen-Inbox (SUN-RQ, Epic #1)
|--------------------------------------------------------------------------
|
| Alle Stellschrauben der Anfragen-Inbox. Die Begruendung zu jedem Wert
| steht in docs/profile-requests.md; die Zugangsdaten des Sammelpostfachs
| stehen ausschliesslich in der .env (REQUESTS_IMAP_*).
|
| Die Tabellen profile_requests, request_changes, request_events und
| request_attachments liegen in der Central-DB (Verbindung `central`, #3).
|
*/

return [

    /*
     * Felder, die bei einer verifizierten Aenderungsanfrage ohne Freigabe
     * von Enes uebernommen werden (Auto-Regeln, #9). Schluessel sind
     * Spalten bzw. Relationen von App\Models\Portal\Company.
     *
     * Nicht auf der Liste und damit immer Handarbeit: name, street,
     * house_no, zipcode, city_id (Stadtseiten- und SEO-Relevanz),
     * categories (Kategoriezuordnung) und user_id (Inhaber).
     */
    'auto_apply_fields' => [
        'tel',
        'email',
        'website',
        'opening_hours',
        'description',
        'services',
        'logo',
        'social_facebook',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'video_url',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Regeln (#9)
    |--------------------------------------------------------------------------
    |
    | Das Regelwerk laeuft nach jeder erfolgreichen Verifikation
    | (App\ProfileRequests\AutoRules\RuleRunner, angestossen aus dem
    | RequestVerifier) und erledigt die Standardfaelle ohne Enes. Alles, was
    | eine Regel nicht vollstaendig abdeckt, bleibt auf `verified` liegen und
    | wartet im Panel (#7).
    |
    */

    /*
     * Notschalter. `true` haelt JEDE Auto-Aktion an: kein automatisches
     * Uebernehmen, kein Start einer Karenzfrist, und `requests:run-grace-
     * deletions` loescht nichts mehr — bestehende `grace_until` laufen damit
     * nicht ab, sie stehen nur still. Der Weg ueber das Panel bleibt offen.
     *
     * Als .env-Schalter, damit das Abschalten auf der Produktion keinen
     * Deploy braucht.
     */
    'auto_disabled' => (bool) env('REQUESTS_AUTO_DISABLED', false),

    'auto' => [

        /*
         * Die Regeln in Pruefreihenfolge. Die erste, deren appliesTo()
         * zutrifft, laeuft; danach ist Schluss. Eine neue Regel ist eine
         * neue Klasse in dieser Liste, nie ein weiteres `if` in einer
         * bestehenden.
         */
        'rules' => [
            App\ProfileRequests\AutoRules\AutoApplyWhitelistedChanges::class,
            App\ProfileRequests\AutoRules\ScheduleVerifiedDeletion::class,
        ],

        /*
         * Eine Aenderung aus einer Mail (Quelle `email`) laeuft nur ohne
         * Enes, wenn der KI-Vorschlag mindestens so zuversichtlich ist. Aus
         * dem Formular (Quelle `form`) kommt ein strukturierter Diff, dort
         * spielt die Zahl keine Rolle.
         */
        'min_ai_confidence' => 0.9,

        /*
         * Betriebe, die nie automatisch bearbeitet werden — „manuell
         * pruefen". Jeder Eintrag ist `tenant_id:company_id`; ein Eintrag
         * ohne Doppelpunkt sperrt ein ganzes Portal.
         *
         * Bewusst Konfiguration und keine Spalte in `companies`: die
         * Markierung ist eine Betriebsentscheidung fuer eine Handvoll
         * Faelle (Rechtsstreit, Presse, Grosskunde) und muesste sonst in 23
         * Tenant-DBs nachgezogen werden.
         */
        'manual_review' => [],

        /*
         * Tagesdigest 08:00 (`requests:daily-digest`). Komma-Liste;
         * leer => alle nicht gesperrten Administratoren, wie beim
         * Poller-Alarm.
         */
        'digest_recipients' => env('REQUESTS_DIGEST_RECIPIENTS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollout (#12)
    |--------------------------------------------------------------------------
    |
    | Der Pilot laeuft eine Woche auf drei Portalen, danach auf allen. Der
    | Umschaltpunkt ist eine .env-Variable, damit zwischen den Stufen kein
    | Deploy liegt. Ablauf und Messung: docs/profile-requests-golive.md.
    |
    */
    'pilot' => [

        /*
         * Portale, auf denen der Self-Service-Dialog am Profil (#4)
         * erscheint. Komma-Liste aus tenants.id oder Portal-Domains.
         * **Leer heisst: alle Portale** — der Endzustand nach dem Pilot.
         * Ausgewertet in App\ProfileRequests\Support\PilotGate.
         */
        'self_service_tenants' => env('REQUESTS_PILOT_SELF_SERVICE_TENANTS'),
    ],

    /*
     * Karenzfrist fuer verifizierte Loeschungen: so lange laeuft die
     * Loeschpipeline (#8) nicht, der Betrieb kann in dieser Zeit ueber
     * den Stornolink in der Bestaetigungsmail widersprechen.
     * DSGVO Art. 12 Abs. 3 laesst einen Monat — 48 Stunden sind deutlich
     * darunter und geben trotzdem Raum fuer einen Irrtum.
     */
    'deletion_grace_hours' => 48,

    /*
     * Gueltigkeit eines Magic-Links in Stunden. Danach geht die Anfrage
     * nach RequestStatus::EXPIRED und muss neu gestellt werden.
     */
    'verification_ttl_hours' => 72,

    /*
     * Modell fuer die Klassifikation und den Diff-Vorschlag (#6). Der
     * Vorschlag ist immer nur ein Vorschlag.
     *
     * Der Wert aus #3 (claude-sonnet-4-20250514) existiert fuer unseren
     * Schluessel nicht mehr und lieferte 404 — die Klassifikation lief damit
     * stumm in den Fallback `type=other`. Geprueft gegen GET /v1/models am
     * 21.10.2026.
     */
    'claude_model' => env('REQUESTS_CLAUDE_MODEL', 'claude-sonnet-5-5'),

    /*
    |--------------------------------------------------------------------------
    | KI-Klassifikation (#6)
    |--------------------------------------------------------------------------
    |
    | Laeuft nur fuer Quelle `email` (RequestSource::needsClassification()).
    | Der Aufruf geht ueber die bestehende Anthropic-Integration
    | (config/ai.php, ANTHROPIC_API_KEY) — Schluessel und Basis-URL stehen
    | dort, hier nur das, was die Klassifikation selbst betrifft.
    |
    */
    'classification' => [

        /*
         * Ab dieser Zuversicht uebernimmt der Job die vorgeschlagene
         * company_id bzw. tenant_id. Darunter bleibt das Feld leer und das
         * Panel (#7) zeigt die Top-Kandidaten zur Auswahl: eine falsch
         * zugeordnete Loeschung trifft den falschen Betrieb, und das ist
         * nicht rueckholbar.
         */
        'min_confidence' => 0.7,

        /*
         * Hoechstzahl Firmenkandidaten, die in den Prompt gehen. Die KI
         * waehlt nur aus dieser Liste — so kann sie keine ID erfinden.
         */
        'max_candidates' => 5,

        // Sekunden fuer den HTTP-Aufruf; eine Mail mit Verlauf braucht Zeit.
        'timeout' => 60,

        // Hoechstlaenge des Mailtexts im Prompt (Zeichen).
        'max_text_chars' => 8000,

        'max_tokens' => 2000,
    ],

    /*
     * Groessengrenze je Anhang in MB. Grosser Anhang wird verworfen und
     * in request_events protokolliert, die Anfrage bleibt bestehen.
     */
    'max_attachment_mb' => 10,

    /*
     * Aufbewahrung der Rohmails (#5) in Tagen. Danach wird nur die
     * Rohmail geloescht; Anfrage, Diff und Protokoll bleiben.
     */
    'retention_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrung geschlossener Vorgaenge (#15)
    |--------------------------------------------------------------------------
    |
    | Zweite, laengere Frist neben `retention_days`: nach ihr faellt der
    | Personenbezug des Vorgangs selbst (`requests:anonymize-closed`,
    | taeglich). Gemessen wird ab dem **Abschluss**, nicht ab dem Eingang.
    |
    | 24 Monate, weil der Vorgang bis dahin der Nachweis nach Art. 17 ist:
    | dass eine Loeschung oder Aenderung vom Betrieb bestaetigt war. Nach
    | zwei Jahren ist eine Beanstandung dazu praktisch ausgeschlossen, der
    | Nachweis bleibt aber in anonymisierter Form bestehen — Typ, Portal,
    | Zeitpunkte, Diff-Feldnamen und Protokoll ueberleben, nur die
    | Kontaktfelder gehen.
    |
    | Dasselbe Prinzip wie bei den Leads der Loeschpipeline
    | (App\Companies\Deletion\Steps\AnonymizeLeads): anonymisieren statt
    | loeschen, Beleg behalten.
    |
    */
    'anonymize_closed' => [

        'after_months' => 24,

        /*
         * `profile_requests.requester_email` ist NOT NULL, kann also nicht
         * geleert werden; dieser Wert ist zugleich das Merkmal, an dem der
         * Lauf einen schon anonymisierten Vorgang erkennt. `.invalid` ist
         * nach RFC 2606 reserviert — die Adresse kann niemandem gehoeren.
         */
        'placeholder_email' => 'anonymisiert@invalid',

        /*
         * Schluessel in `request_events.payload`, deren Wert durch
         * `[anonymisiert]` ersetzt wird. In ihnen stehen Adressen, Betreffe
         * und Mailtexte (#5, #10) — bliebe das stehen, waere die
         * Anonymisierung der Spalten wirkungslos.
         *
         * `recipient` steht bewusst NICHT auf der Liste: das ist die
         * Portaladresse, an die die Mail ging, und sie belegt die
         * Portal-Zuordnung.
         */
        'event_payload_keys' => [
            'sent_to',
            'verified_email',
            'requester_email',
            'from_email',
            'from',
            'to',
            'subject',
            'message',
        ],
    ],

    /*
     * Zentrales Sammelpostfach. Alle Portal-Adressen leiten hierhin, der
     * Poller (#5) liest nur dieses eine Postfach.
     */
    'mailbox' => [

        'address' => env('REQUESTS_MAILBOX_ADDRESS'),

        'imap' => [
            'host' => env('REQUESTS_IMAP_HOST'),
            'port' => (int) env('REQUESTS_IMAP_PORT', 993),
            'encryption' => env('REQUESTS_IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => (bool) env('REQUESTS_IMAP_VALIDATE_CERT', true),
            'username' => env('REQUESTS_IMAP_USERNAME'),
            'password' => env('REQUESTS_IMAP_PASSWORD'),
            'folder' => env('REQUESTS_IMAP_FOLDER', 'INBOX'),

            // Ordner, in den eine eingelesene Mail verschoben wird. Der
            // Poller loescht nie im Postfach.
            'processed_folder' => env('REQUESTS_IMAP_PROCESSED_FOLDER', 'Verarbeitet'),
        ],

        /*
         * Kopfzeilen, aus denen das Zielportal gelesen wird — in dieser
         * Reihenfolge. Greift keine, entscheidet die KI (#6); bleibt auch
         * das unklar, geht die Anfrage ohne Portal in die Inbox.
         */
        'portal_headers' => ['Delivered-To', 'X-Original-To', 'X-Forwarded-To', 'To'],

        // Hoechstzahl Mails je Pollerlauf.
        'poll_batch_size' => 50,

        /*
         * Zuordnung Empfaengeradresse -> Portal fuer die Faelle, in denen
         * die Weiterleitung die urspruengliche Adresse verliert (manche
         * Hoster schreiben nur noch das Sammelpostfach in alle Kopfzeilen).
         * Schluessel ist die vollstaendige Adresse in Kleinschreibung, Wert
         * die tenants.id oder die Portal-Domain.
         *
         * Leer lassen, solange die Kopfzeilen tragen — eine gepflegte Liste,
         * die niemand braucht, laeuft beim naechsten Portal auseinander.
         */
        'recipient_tenant_map' => [],
    ],

    /*
     * E-Mail-Ingestion (#5): der Poller requests:fetch-mail.
     */
    'ingestion' => [

        /*
         * Diese Anhaenge werden uebernommen — Bilder und PDF. Alles andere
         * (Archive, Office, ausfuehrbares) wird verworfen und in
         * request_events protokolliert; wir oeffnen keine fremden Dateien.
         */
        'allowed_attachment_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/heic',
            'application/pdf',
        ],

        /*
         * Kopfzeilen, die eine automatische Antwort kennzeichnen (RFC 3834
         * und die verbreiteten Hausmittel). Eine Mail mit einer davon wird
         * verworfen, nie als Anfrage angelegt.
         */
        'auto_reply_headers' => [
            'auto-submitted',          // alles ausser "no"
            'x-auto-response-suppress',
            'x-autoreply',
            'x-autorespond',
            'x-autoresponder',
        ],

        /*
         * Werte von Precedence, die Massenversand bedeuten.
         */
        'bulk_precedences' => ['bulk', 'junk', 'list', 'auto_reply'],

        /*
         * Newsletter erkennt man an den Listenkopfzeilen. Treffer -> verworfen.
         */
        'newsletter_headers' => ['list-id', 'list-unsubscribe', 'list-post'],

        /*
         * Absender, die nie eine Anfrage stellen. Volle Adresse oder — mit
         * fuehrendem @ — eine ganze Domain, jeweils Kleinschreibung.
         * Erweiterbar ohne Deployment ueber REQUESTS_BLOCKED_SENDERS
         * (Komma-Liste), die Liste hier bleibt die Grundausstattung.
         */
        'blocked_senders' => [
            '@mailer-daemon',
            'postmaster@',
            'mailer-daemon@',
        ],

        'blocked_senders_env' => env('REQUESTS_BLOCKED_SENDERS'),

        /*
         * Lokale Teile, die auf einen Automaten statt auf einen Menschen
         * zeigen. Treffer -> verworfen.
         */
        'blocked_local_parts' => ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'bounce', 'bounces'],

        /*
         * Betreffsmarke, mit der ausgehende Mails (#10) ihre Anfrage tragen.
         * Antworten darauf werden dem Vorgang zugeordnet statt neu angelegt.
         */
        'subject_tag_pattern' => '/\\[REQ-(\\d+)\\]/i',

        /*
         * Message-ID-Muster der ausgehenden Mails (#10). Kommt eine solche
         * ID in In-Reply-To oder References zurueck, ist der Vorgang
         * eindeutig — auch wenn der Betreff unterwegs verstuemmelt wurde.
         * Ausgehende Mails muessen ihre Message-ID deshalb als
         * <req-{id}-{zufall}@domain> setzen.
         */
        'outgoing_message_id_pattern' => '/<?req-(\\d+)-[^@>]+@/i',

        /*
         * Alarm bei Verbindungs- oder Anmeldefehlern des Pollers: nach so
         * vielen Fehlschlaegen in Folge geht eine Mail raus, danach erst
         * wieder nach einem erfolgreichen Lauf.
         */
        'alert' => [
            'failure_threshold' => 3,

            // Komma-Liste; leer => alle nicht gesperrten Administratoren.
            'recipients' => env('REQUESTS_ALERT_RECIPIENTS'),

            /*
             * Der Fehlerzaehler ueberlebt den Prozess nur im Cache. `file`,
             * weil der Poller auch im Tenant-Kontext laufen kann und der
             * Standardstore dort auf eine Tenant-DB ohne cache-Tabelle
             * zeigen wuerde.
             */
            'cache_store' => env('REQUESTS_ALERT_CACHE_STORE', 'file'),
        ],
    ],

    'verification' => [

        /*
         * Bei diesen Absenderdomains gilt Domain-Match nie — sie gehoeren
         * keinem Betrieb. Dann bleibt nur Magic-Link oder Handpruefung.
         */
        'freemail_domains' => [
            'gmail.com', 'googlemail.com', 'web.de', 'gmx.de', 'gmx.net',
            'gmx.at', 'gmx.ch', 't-online.de', 'hotmail.com', 'hotmail.de',
            'outlook.com', 'outlook.de', 'live.de', 'yahoo.com', 'yahoo.de',
            'icloud.com', 'me.com', 'aol.com', 'freenet.de', 'mail.de',
            'posteo.de', 'arcor.de', 'online.de', 'protonmail.com', 'proton.me',
        ],

        /*
         * Hoechstzahl Magic-Link-Mails je Profil und Tag — schuetzt den
         * Betrieb davor, ueber das Formular mit Mails geflutet zu werden.
         */
        'max_links_per_company_per_day' => 3,

        /*
         * Pflegeliste der Freemail-Domains (#6). Wird mit
         * `freemail_domains` oben vereinigt; eine Datei laesst sich auf der
         * Produktion ohne Deployment nachziehen.
         */
        'generic_domains_file' => 'profile-requests/generic-email-domains.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Loeschpipeline (#8)
    |--------------------------------------------------------------------------
    |
    | Die Schritte und ihre Reihenfolge. App\Companies\Deletion\DeleteCompanyAction
    | laeuft genau diese Liste von oben nach unten durch, protokolliert jeden
    | Schritt in `deletion_log` der Tenant-DB und laeuft weiter, wenn einer
    | fehlschlaegt. Eine neue Datenart ist ein neuer Schritt in dieser Liste,
    | nie ein Anhaengsel an einem bestehenden.
    |
    | Die Reihenfolge ist nicht beliebig:
    |  * `soft_delete_profile` zuerst — danach faellt das Profil aus
    |    Company::active() und alles Weitere arbeitet an einem Eintrag, der
    |    oeffentlich schon nicht mehr sichtbar ist.
    |  * `register_gone_url` vor `regenerate_sitemap` und `ping_indexnow`:
    |    erst wenn die URL 410 liefert, darf ein Crawler sie abholen.
    |  * `invalidate_caches` vor dem Sitemap-Lauf, damit der Generator die
    |    frischen Zahlen liest.
    |
    */
    'deletion' => [

        'steps' => [
            App\Companies\Deletion\Steps\SoftDeleteProfile::class,
            App\Companies\Deletion\Steps\RemoveMedia::class,
            App\Companies\Deletion\Steps\RemoveReviews::class,
            App\Companies\Deletion\Steps\PurgeRelatedRecords::class,
            App\Companies\Deletion\Steps\AnonymizeLeads::class,
            App\Companies\Deletion\Steps\RegisterGoneUrl::class,
            App\Companies\Deletion\Steps\InvalidateCaches::class,
            App\Companies\Deletion\Steps\RegenerateSitemap::class,
            App\Companies\Deletion\Steps\PingIndexNow::class,
        ],

        /*
         * Tage zwischen Soft Delete und hartem Loeschen
         * (`companies:hard-delete-due`, taeglich im Scheduler). Der Soft
         * Delete haelt eine Fehlbedienung im Panel rueckholbar; fuer Art. 17
         * ist er allein nicht genug, deshalb die Frist. Alles andere —
         * Bilder, Bewertungen, Statistiken — ist sofort weg.
         */
        'hard_delete_after_days' => 30,

        /*
         * Sitemap-Neuaufbau: 'queue' (Standard), 'sync' oder 'off'. Ein
         * Portal mit 19 600 Betrieben braucht dafuer Minuten — ein
         * Loeschklick im Panel soll darauf nicht warten. Fuer eine Abnahme
         * ist 'sync' richtig, weil nur dann direkt nach dem Lauf pruefbar
         * ist, dass die URL aus der Datei verschwunden ist.
         */
        'sitemap' => env('REQUESTS_DELETION_SITEMAP', 'queue'),
    ],

];
