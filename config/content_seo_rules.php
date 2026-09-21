<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Regelwerk des Qualitaetsgates (#15)
|--------------------------------------------------------------------------
|
| Stufe 1 des Gates ist deterministisch: App\Content\Quality\SeoLinter
| arbeitet ausschliesslich diese Liste ab, ohne Modellaufruf. Jede Regel ist
| einzeln abschaltbar, gewichtbar und kann blockierend gesetzt werden.
|
|   enabled   Regel wird geprueft.
|   blocking  Ein Verstoss verhindert die automatische Freigabe, unabhaengig
|             vom Gesamtscore.
|   weight    Anteil an der SEO-Teilnote. Eine Warnung zaehlt halb.
|
| Die uebrigen Bloecke konfigurieren die Stufen, die der Linter braucht:
| Faktencheck, Linkpruefung, Lesbarkeit und Doorway-Erkennung.
|
*/

return [

    'rules' => [

        /*
        | Meta-Angaben. Nicht blockierend: ein zu langer Titel wird im
        | Frontend gekuerzt, das ist kein Grund, einen fertigen Artikel in die
        | manuelle Pruefung zu schicken.
        */
        'title_length' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
            'min' => 30,
            // null = Grenze aus config('content.generation.title_max_chars')
            'max' => null,
        ],

        'meta_description_length' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
            'min' => 110,
            // null = Grenze aus config('content.generation.meta_description_max_chars')
            'max' => null,
        ],

        /*
        | Genau eine H1: die Seite setzt den Artikeltitel als H1 (#17). Steht
        | im Fliesstext eine weitere, hat die Seite zwei — deshalb blockierend.
        */
        'single_h1' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.0,
        ],

        'h2_count' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
            'min' => 3,
            'max' => 10,
        ],

        'keyword_in_title' => ['enabled' => true, 'blocking' => true, 'weight' => 1.5],
        'keyword_in_h1' => ['enabled' => true, 'blocking' => true, 'weight' => 1.0],
        'keyword_in_short_answer' => ['enabled' => true, 'blocking' => false, 'weight' => 1.0],
        'keyword_in_first_paragraph' => ['enabled' => true, 'blocking' => false, 'weight' => 1.0],

        /*
        | Laengenkorridor (Ticket #62, §4.2). Gemessen wird gegen den
        | Zielkorridor der Suchintention, den QualityCheckJob als
        | 'target_words' durchreicht, zuzueglich content.quality.word_tolerance.
        | Ausserhalb der Toleranz gibt es 'warn' (halbe Punktzahl), erst
        | ausserhalb content.quality.hard_word_limits 'fail' — und nur ein
        | 'fail' blockiert, darum darf die Regel blocking tragen.
        */
        'word_count' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.5,
            // null = Korridor aus der Eingabe. Gesetzte Werte schlagen sie
            // (Notgriff fuer den Betrieb).
            'min' => null,
            'max' => null,
        ],

        /*
        | Interne Verlinkung. Ein Ratgeber ohne Weg in das Verzeichnis ist
        | fuer den Mandanten wertlos, deshalb blockierend.
        */
        'internal_links' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.5,
            // null = Werte aus config('content.generation.internal_links')
            'min' => null,
            'max_articles' => null,
        ],

        'faq_count' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
            'min' => 3,
            'max' => 6,
        ],

        /*
        | Bild-Alt. Solange die Assets (#16) fehlen, hat der Entwurf keine
        | Bilder; die Regel meldet dann 'uebersprungen' und geht nicht in die
        | Note ein.
        */
        'image_alt' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
        ],

        /*
        | Keine Links auf 404. Externe Ziele werden per HEAD geprueft
        | (siehe 'links'), 4xx/5xx sind blockierend.
        */
        'external_links_reachable' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.5,
        ],

        'readability' => [
            'enabled' => true,
            'blocking' => false,
            'weight' => 1.0,
        ],

        /*
        | Umlaute als ae/oe/ue (#41). Im Titel, in den Meta-Angaben oder in der
        | Kurzantwort ist schon ein Wort blockierend, weil es in den
        | Suchergebnissen steht; im Fliesstext ab 'max_body_suspects'.
        */
        'umlaut_spelling' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.5,
            'max_body_suspects' => 2,
        ],

        /*
        | Kurzantwort, die ihr eigenes Entstehen beschreibt ("Kurzantwort
        | erstellt: Sie beantwortet ...") statt die Frage zu beantworten (#42).
        */
        'short_answer_meta' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 1.5,
        ],

        /*
        | Kostenartikel ohne Betrag (#42): Titel oder Hauptkeyword versprechen
        | Kosten oder Preise, im Text steht aber kein einziger Euro-Betrag.
        | Solche Artikel verfehlen die Suchintention, auch wenn die Rubrik sie
        | gut bewertet, weil sie nichts Unbelegtes behaupten.
        */
        'cost_figures' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 2.0,
            'pattern' => '/\\b(kosten|kostet|preis|preise|stundenlohn|stundensatz|gebuehr|gebühr|tarif)\\b/iu',
        ],

        /*
        | Doorway-Erkennung: ein Regionalartikel, der die Region nur in Titel
        | und Meta nennt, ist eine Doorway-Seite und wird nie automatisch
        | freigegeben.
        */
        'doorway' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 2.0,
        ],

        /*
        | Pflicht-Disclaimer. Greift nur bei Mandanten mit
        | tenant_content_settings.is_ymyl.
        */
        'ymyl_disclaimer' => [
            'enabled' => true,
            'blocking' => true,
            'weight' => 2.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Faktencheck (Stufe 2)
    |--------------------------------------------------------------------------
    |
    | Alle Zahlen des Fliesstexts werden extrahiert und gegen die
    | Faktenschnipsel und Quellenausschnitte des Entwurfs gehalten. Eine nicht
    | belegbare Zahl ist immer blockierend.
    |
    */

    'fact_check' => [
        'enabled' => true,

        // Toleranz fuer gerundete Werte: 2 % Abweichung gelten als belegt,
        // der Bericht weist sie als 'gerundet' aus.
        'tolerance' => 0.02,

        // Jahreszahlen sind keine Fakten, sondern Zeitangaben.
        'year_range' => [1900, 2100],

        // Nackte Zahlen ohne Einheit bis zu dieser Groesse sind Aufzaehlungen
        // ("in 3 Schritten") und werden nicht geprueft. Mit Einheit ("30
        // Tage") werden sie immer geprueft.
        'ignore_bare_below' => 20,

        // Einheiten, die eine Zahl pruefpflichtig machen. Die Liste ist
        // absichtlich lang: sie entscheidet, was als Sachangabe gilt.
        'units' => [
            '%', 'Prozent', '€', 'EUR', 'Euro', 'Cent',
            'Tag', 'Tage', 'Tagen', 'Woche', 'Wochen', 'Monat', 'Monate', 'Monaten',
            'Jahr', 'Jahre', 'Jahren', 'Stunde', 'Stunden', 'Minuten',
            'kWh', 'kW', 'MW', 'kg', 'Liter', 'm', 'm²', 'qm', 'cm', 'mm', 'km',
            'Grad', '°C', 'Betriebe', 'Bewertungen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Linkpruefung
    |--------------------------------------------------------------------------
    |
    | HEAD-Request je externem Ziel. Timeout 5 Sekunden — ein langsamer
    | Zielserver darf das Gate nicht aufhalten; eine Zeitueberschreitung gilt
    | nicht als 404 und ist damit nicht blockierend.
    |
    */

    'links' => [
        'enabled' => true,
        'timeout' => 5,
        'connect_timeout' => 3,

        // Ergebnis je URL wird so lange vorgehalten; mehrere Artikel zitieren
        // dieselben Quellen.
        'cache_seconds' => 86400,

        // Manche Server beantworten HEAD mit 405 oder 403. Dann wird einmal
        // mit GET nachgefasst, bevor der Link als kaputt gilt.
        'retry_with_get_on' => [403, 405, 501],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lesbarkeit
    |--------------------------------------------------------------------------
    |
    | Flesch-Reading-Ease in der deutschen Fassung nach Amstad:
    | 180 - ASL - (58,5 x ASW). Zielwert 50 ("mittelschwer").
    |
    */

    'readability' => [
        'min_flesch' => (int) env('CONTENT_QUALITY_MIN_FLESCH', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doorway-Erkennung
    |--------------------------------------------------------------------------
    */

    'doorway' => [
        // So oft muss der Regionalname im Fliesstext stehen (Titel und Meta
        // zaehlen nicht mit).
        'min_mentions' => 3,

        // Zusaetzlich muss mindestens ein regionaler Fakt im Artikel stehen.
        'require_regional_fact' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | YMYL-Pflichthinweis
    |--------------------------------------------------------------------------
    |
    | Einer dieser Textbausteine muss im Artikel stehen, wenn der Mandant als
    | YMYL gefuehrt ist. Der genaue Wortlaut kommt aus dem Branchen-Styleguide
    | (#13); geprueft wird auf die tragenden Formulierungen.
    |
    */

    'ymyl' => [
        'disclaimer_markers' => [
            'ersetzt keine',
            'ersetzt kein',
            'keine Rechtsberatung',
            'keine medizinische Beratung',
            'ohne Gewähr',
            'im Einzelfall',
        ],
    ],
];
