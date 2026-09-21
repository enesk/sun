<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Qualitaetsgate des Ratgebersystems (#11)
|--------------------------------------------------------------------------
|
| Regeln fuer App\Guide\Quality\* und die Freigabeentscheidung im
| App\Guide\Jobs\QualityCheckJob. Aufbau und Ablauf: docs/guide-system.md,
| "Umgesetzt in #11".
|
| rules: deterministischer Lint, eine Zeile je Regel. `blocking` verhindert
|   die Auto-Freigabe unabhaengig vom Score; `fixable` heisst, der eine
|   Fix-Durchlauf kann den Befund beheben (sonst geht der Lauf direkt in
|   review). Eine Regel mit `enabled => false` wird nicht geprueft.
|
*/

return [

    'rules' => [
        // Gerenderter Titel (mit ersetztem {{year}}) = posts.title und meta_title.
        'title_length' => ['enabled' => true, 'blocking' => true, 'fixable' => true, 'min' => 20, 'max' => 60],
        'meta_description_length' => ['enabled' => true, 'blocking' => true, 'fixable' => true, 'min' => 100, 'max' => 155],

        // Die Seite rendert den Titel als einzige H1; im body_html darf keine stehen.
        'single_h1' => ['enabled' => true, 'blocking' => true, 'fixable' => false],

        // H2/H3 im body_html exakt wie die gesperrte guide_topics.outline_json.
        'outline_match' => ['enabled' => true, 'blocking' => true, 'fixable' => false],

        // Jede H2 beginnt mit dem Fazit-Satz als eigenem Absatz.
        'section_lead' => ['enabled' => true, 'blocking' => true, 'fixable' => true],

        'faq_count' => ['enabled' => true, 'blocking' => true, 'fixable' => true, 'min' => 4, 'max' => 6],

        'short_answer' => ['enabled' => true, 'blocking' => true, 'fixable' => true, 'min' => 80, 'max' => 400],

        // Interne Links (wurzelrelativ) im body_html; Mindestzahl wie guide.writing.links.min_portal.
        'internal_links' => ['enabled' => true, 'blocking' => true, 'fixable' => false, 'min' => 2],

        // Tags und Attribute nur laut guide.writing.allowed_tags (HtmlAssembler::violations()).
        'allowed_tags' => ['enabled' => true, 'blocking' => true, 'fixable' => false],

        // Nur bei tenant_guide_settings.is_ymyl: Pflicht-Disclaimer im Artikel.
        'ymyl_disclaimer' => ['enabled' => true, 'blocking' => true, 'fixable' => true],
    ],

    /*
    | Erkennung des Pflicht-Disclaimers (docs/guide-prompts.md §7). Ein Treffer
    | eines Musters im Klartext von body_html oder Kurzantwort genuegt. Die
    | Muster decken die Formulierungen der YMYL-Styleguides ab ("ersetzt keine
    | aerztliche Diagnose", "ersetzen keine individuelle Beratung", ...).
    */

    'ymyl_disclaimer' => [
        'patterns' => [
            '/ersetz\w*\s+(?:[\p{L}\/\-]+\s+){0,4}?(?:kein\w*|nicht)\s+(?:[\p{L}\/\-]+\s+){0,5}?[\p{L}\-]*(?:beratung|diagnose|untersuchung|gutachten|behandlung)\b/iu',
            '/(?:kein\w*|nicht)\s+(?:[\p{L}\/\-]+\s+){0,3}?[\p{L}\-]*(?:beratung|diagnose|untersuchung|gutachten|behandlung)\s+(?:[\p{L}\/\-]+\s+){0,4}?ersetz\w*/iu',
        ],
    ],

    /*
    | Faktenabgleich (FactChecker). Zahlen in deutscher Schreibweise, Daten
    | (TT.MM.JJJJ, "1. Maerz 2026", MM/JJJJ) und Prozente muessen einem
    | is_current-Fakt entsprechen. tolerance: relative Abweichung fuer
    | gerundete Werte. ignore_bare_below: Zahlen ohne Einheit unterhalb dieses
    | Werts sind Zaehlungen ("3 Angebote"), keine Sachangaben.
    | reference_prefixes: Kennungen von Normen und Paragrafen ("DIN 18534",
    | "§ 35a") sind keine Werte.
    */

    'fact_check' => [
        'enabled' => true,
        'tolerance' => 0.02,
        'year_range' => [1900, 2100],
        'ignore_bare_below' => 13,
        'units' => [
            '€', 'Euro', 'EUR', 'Cent', 'ct', '%', 'Prozent', 'Prozentpunkte',
            'm²', 'm2', 'qm', 'm³', 'm3', 'cm', 'mm', 'km', 'm', 'kg', 't',
            'kWh', 'kWp', 'kW', 'MWh', 'W', 'V', 'A', 'l', 'Liter', 'Grad', '°C',
            'Stunden', 'Stunde', 'Std.', 'Tage', 'Tagen', 'Tag', 'Wochen', 'Woche',
            'Monate', 'Monaten', 'Monat', 'Jahre', 'Jahren', 'Jahr', 'Minuten', 'Min.',
            'Mio.', 'Millionen', 'Mrd.', 'Milliarden',
        ],
        'reference_prefixes' => ['DIN', 'EN', 'ISO', 'VDE', 'VDI', 'DGUV', '§', '§§', 'Art.', 'Artikel', 'Abs.', 'Absatz', 'Nr.', 'Paragraf', 'Paragraph', 'Anlage', 'Kapitel', 'Stufe', 'Klasse', 'Schritt', 'Tipp', 'Punkt'],
    ],

    /*
    | Link-Check der Quellen (LinkChecker): HEAD mit timeout Sekunden,
    | 4xx/5xx = broken und blocking, solange die Quelle einen aktuellen Fakt
    | belegt oder im Text verlinkt ist. Zeitueberschreitung = Warnung.
    */

    'links' => [
        'enabled' => true,
        'timeout' => 5,
        'connect_timeout' => 3,
        'retry_with_get_on' => [403, 405, 501],
        'cache_seconds' => 21600,
        'user_agent' => 'SUN-RatgeberBot/1.0 (+:bot_url)',
    ],

    /*
    | Lesbarkeit: Flesch nach Amstad, Ziel >= min_flesch, nie blocking.
    */

    'readability' => [
        'min_flesch' => 50,
    ],

    /*
    | final_score = gewichtete Summe der Teilscores (0 bis 100). Die Rubrik
    | ist die zweite Instanz, der deterministische Abgleich die erste
    | (docs/guide-prompts.md §6).
    */

    'weights' => [
        'rubric' => 0.60,
        'fact' => 0.20,
        'lint' => 0.15,
        'readability' => 0.05,
    ],

    // Hoechstzahl der Fix-Durchlaeufe je Lauf (checking -> writing, docs/guide-system.md §3).
    'max_fix_runs' => 1,

];
