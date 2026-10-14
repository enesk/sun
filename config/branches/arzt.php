<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Arzt (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Texte freigegeben von Enes, 14.10.2026 (#13).
|
| Ueberlagert config/themes/sun-v2.php fuer arztfinder.firmenfreund.de
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
        'headline' => 'Finde deine Arztpraxis in deiner Nähe',
        'text' => 'Über :companies Praxen mit Fachrichtung, Sprechzeiten und direkter Telefonnummer – von der Allgemeinmedizin bis zum Facharzt.',
        'search_placeholder' => 'Praxis oder Fachrichtung, z. B. Kinderarzt',
        'search_button' => 'Praxis finden',
        'popular' => ['Hausarzt', 'Kinderarzt', 'Internist', 'Facharzt'],
    ],

    'services' => [
        ['label' => 'Allgemeinmedizin', 'query' => 'Allgemeinmedizin', 'icon' => 'plus', 'intro' => ':anzahl bieten Allgemeinmedizin an'], // 983 Treffer
        ['label' => 'Hausarzt', 'query' => 'Hausarzt', 'icon' => 'home'], // 88 Treffer
        ['label' => 'Facharzt', 'query' => 'Facharzt', 'icon' => 'star'], // 884 Treffer
        ['label' => 'Gemeinschaftspraxis', 'query' => 'Gemeinschaftspraxis', 'icon' => 'users'], // 370 Treffer
        ['label' => 'Internist', 'query' => 'Internist', 'icon' => 'chart'], // 44 Treffer
        ['label' => 'Kinderarzt', 'query' => 'Kinderarzt', 'icon' => 'users'], // 15 Treffer
    ],

    'seo' => [
        'headline' => 'Die richtige Arztpraxis finden – so geht\'s',
        'paragraphs' => [
            'Ob die erste Anlaufstelle beim Hausarzt, eine Überweisung zum Facharzt oder eine neue Praxis nach einem Umzug: Auf :portal findest du über :companies Praxen in ganz Deutschland – mit Adresse, Fachrichtung, Sprechzeiten und direkter Telefonnummer.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der Fachrichtung, die du suchst. Ruf vorab an, um Sprechzeiten und ob die Praxis neue Patient:innen aufnimmt zu klären.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Arzt',
        'branch_plural' => 'Arzt',
        'placeholder' => 'Arztpraxis oder Leistung',
        'quick_term' => 'Allgemeinmedizin',
        'seo' => [
            'headline' => 'Arztpraxis auswählen: Worauf du achten kannst',
            'paragraphs' => [
                'Achte auf die angegebene Fachrichtung und auf die Sprechzeiten, die zu deinem Alltag passen. Bei Gemeinschaftspraxen lohnt sich die Nachfrage, welche Ärzt:innen dort behandeln.',
                'Für Vorsorgeuntersuchungen oder wiederkehrende Termine lohnt sich eine feste Praxis in der Nähe – das erleichtert die Terminvergabe über die Zeit.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Arztpraxis in :city finden',
            'paragraphs' => [
                'Eine Praxis in der Nähe bedeutet kurze Wege zu Terminen und Nachkontrollen. Achte in der Liste auf Adresse und Fachrichtung und ruf direkt an.',
                'Ziehst du neu nach :city, lohnt sich die frühzeitige Suche nach einer Hausarztpraxis, da die Aufnahme neuer Patient:innen von Praxis zu Praxis unterschiedlich gehandhabt wird.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Arztpraxen in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Praxis eingetragen ist, mit der Zahl der Praxen. Auf der Stadtseite siehst du alle Praxen vor Ort mit Fachrichtung und Telefonnummer.',
                'In kleineren Orten ohne eigene Praxis lohnt sich der Blick in die nächstgrößere Stadt.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Arztbesuch und Vorsorge',
        'text' => ':posts Artikel, geschrieben, damit du dich vor dem Praxisbesuch informieren kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Vorsorge',
        'category_icons' => [
            'hausarzt' => 'home',
            'facharzt' => 'star',
            'kinderarzt' => 'users',
        ],
        'cta' => [
            'headline' => 'Lieber in der Praxis nachfragen?',
            'text' => ':companies Praxen sind hier eingetragen – mit Fachrichtung und Telefonnummer. Gib deinen Ort ein und ruf direkt an.',
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
