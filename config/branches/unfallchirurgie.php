<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Unfallchirurg (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Texte freigegeben von Enes, 14.10.2026 (#13).
|
| Ueberlagert config/themes/sun-v2.php fuer unfallarzt.firmenfreund.de
| (Zuordnung in config/themes/sun-v2-branches.php). Nur abweichende
| Schluessel stehen hier; Listen ersetzen die Vorgabe vollstaendig.
|
| Die Suchbegriffe der Leistungskacheln sind an den echten Betriebsdaten
| geprueft (Volltextsuche ueber name + description, Stand 14.10.2026).
| Achtung: Woerter unter vier Zeichen findet die Volltextsuche nicht.
|
| Heilberuf: Werbeaussagen unterliegen engeren Regeln (HWG, Berufsrecht).
| Keine Heilversprechen, keine Wirkungsaussagen, keine Dringlichkeitsformeln.
|
*/

return [

    // Seitentitel, Meta-Beschreibung, der Eintragen-Aufruf und die
    // Staedte-Ueberschrift stehen nicht hier: sie kommen aus lang/de/portal.php
    // und setzen die Begriffe des Portals (tenant.terms) selbst ein.
    'brand_icon' => 'plus',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deine Praxis für Unfallchirurgie',
        'text' => 'Über :companies Praxen für Unfallchirurgie, Orthopädie und Sportmedizin mit Sprechzeiten und direkter Telefonnummer.',
        'search_placeholder' => 'Praxis oder Leistung, z. B. Orthopädie',
        'search_button' => 'Praxis finden',
        'popular' => ['Orthopädie', 'Sportmedizin', 'Handchirurgie', 'Durchgangsarzt'],
    ],

    'services' => [
        ['label' => 'Unfallchirurgie', 'query' => 'Unfallchirurgie', 'icon' => 'plus', 'intro' => ':anzahl sind auf Unfallchirurgie spezialisiert'], // 966 Treffer
        ['label' => 'Orthopädie', 'query' => 'Orthopädie', 'icon' => 'users'], // 2.135 Treffer
        ['label' => 'Chirurgie', 'query' => 'Chirurgie', 'icon' => 'wrench'], // 791 Treffer
        ['label' => 'Durchgangsarzt', 'query' => 'Durchgangsarzt', 'icon' => 'shield-check'], // 50 Treffer
        ['label' => 'Sportmedizin', 'query' => 'Sportmedizin', 'icon' => 'chart'], // 106 Treffer
        ['label' => 'Handchirurgie', 'query' => 'Handchirurgie', 'icon' => 'wrench'], // 99 Treffer
    ],

    'seo' => [
        'headline' => 'Die richtige Praxis für Unfallchirurgie finden – so geht\'s',
        'paragraphs' => [
            'Ob nach einem Sportunfall, für eine Zweitmeinung zu einer Operation oder die Nachsorge nach einem Bruch: Auf :portal findest du über :companies Praxen in ganz Deutschland – mit Adresse, Leistungsschwerpunkt und direkter Telefonnummer.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der Fachrichtung. Ruf vorab an, um zu klären, ob die Praxis als Durchgangsarzt für Arbeitsunfälle zugelassen ist, falls das für dich relevant ist.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Unfallchirurg',
        'branch_plural' => 'Unfallchirurg',
        'placeholder' => 'Praxis oder Leistung',
        'quick_term' => 'Unfallchirurgie',
        'seo' => [
            'headline' => 'Praxis auswählen: Worauf du achten kannst',
            'paragraphs' => [
                'Achte auf die angegebene Spezialisierung – Hand-, Sport- oder allgemeine Unfallchirurgie decken unterschiedliche Schwerpunkte ab. Bei Arbeitsunfällen ist die Zulassung als Durchgangsarzt relevant.',
                'Für die Nachsorge nach einer Operation lohnt sich eine Praxis, die eng mit dem behandelnden Krankenhaus zusammenarbeitet oder deine Unterlagen unkompliziert übernimmt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Praxis für Unfallchirurgie in :city finden',
            'paragraphs' => [
                'Eine Praxis in der Nähe erleichtert Kontrolltermine nach einer Verletzung. Achte in der Liste auf Adresse und Leistungsschwerpunkt.',
                'Ruf vor dem ersten Termin an, um zu klären, ob eine Überweisung nötig ist oder du direkt einen Termin bekommst.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Praxen für Unfallchirurgie in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Praxis eingetragen ist, mit der Zahl der Praxen. Auf der Stadtseite siehst du alle Praxen vor Ort mit Leistungsschwerpunkt und Telefonnummer.',
                'In kleineren Orten ohne eigene Praxis lohnt sich der Blick in die nächstgrößere Stadt, besonders bei spezialisierten Leistungen wie Handchirurgie.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Unfallchirurgie und Orthopädie',
        'text' => ':posts Artikel, geschrieben, damit du dich vor dem Praxisbesuch informieren kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Sportverletzung',
        'category_icons' => [
            'orthopaedie' => 'users',
            'sportmedizin' => 'chart',
            'handchirurgie' => 'wrench',
        ],
        'cta' => [
            'headline' => 'Lieber in der Praxis nachfragen?',
            'text' => ':companies Praxen sind hier eingetragen – mit Leistungsschwerpunkt und Telefonnummer. Gib deinen Ort ein und ruf direkt an.',
        ],
    ],

    'login' => [
        'headline' => 'Deine Praxis, dein Profil',
        'text' => ':companies Praxen sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Patient:innen von deiner Praxis sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Medizinische', 'label' => 'Medizinische'],
            ['query' => 'Facharzt', 'label' => 'Facharzt'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Praxismanagement', 'label' => 'Praxismanagement'],
        ],
    ],

];
