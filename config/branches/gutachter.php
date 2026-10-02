<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Gutachter und Sachverstaendige (Theme sun-v2, #49)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer findegutachter.de (Zuordnung in
| config/themes/sun-v2-branches.php).
|
| Nur 7 der 14.061 Betriebe haben eine Beschreibung, die Volltextsuche greift
| also fast nur auf den Firmennamen. Anders als bei Fahrschulen traegt das
| hier: Gutachterbueros fuehren ihr Fachgebiet im Namen. Gemessen am
| 02.10.2026: Kfz-Gutachter 6.042, Sachverstaendiger 2.160, Ingenieurbuero
| 991, Gutachten 604, Immobilien 392, Bausachverstaendiger 182, Schaden 130,
| Baugutachter 81, Wertermittlung 33, Schimmel 21, Oldtimer 18.
| Kacheln ohne Treffer blendet der HomeViewComposer aus.
|
*/

return [

    'brand_icon' => 'shield-check',

    // Ratgeber ist auf diesem Portal noch leer (0 Themen, 0 Artikel)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Gutachter in deiner Nähe',
        'text' => 'Über :companies Gutachterbüros und Sachverständige mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer – vom Unfallschaden bis zum Hauskauf.',
        'search_placeholder' => 'Fachgebiet oder Ort, z. B. Kfz-Gutachter',
        'search_button' => 'Gutachter finden',
        'popular' => ['Kfz-Gutachter', 'Immobilien', 'Bausachverständiger', 'Schimmel'],
    ],

    'services' => [
        ['label' => 'Kfz-Gutachten', 'query' => 'Kfz-Gutachter', 'icon' => 'car', 'intro' => ':anzahl davon begutachten Fahrzeuge'],
        ['label' => 'Immobilienbewertung', 'query' => 'Immobilien', 'icon' => 'home'],
        ['label' => 'Bausachverständige', 'query' => 'Bausachverständiger', 'icon' => 'shield-check'],
        ['label' => 'Schäden & Mängel', 'query' => 'Schaden', 'icon' => 'search'],
        ['label' => 'Baugutachten', 'query' => 'Baugutachter', 'icon' => 'text'],
        ['label' => 'Wertermittlung', 'query' => 'Wertermittlung', 'icon' => 'euro'],
        ['label' => 'Schimmel & Feuchte', 'query' => 'Schimmel', 'icon' => 'droplet'],
        ['label' => 'Oldtimer', 'query' => 'Oldtimer', 'icon' => 'tire'],
    ],

    'seo' => [
        'headline' => 'Den richtigen Gutachter finden – so geht\'s',
        'paragraphs' => [
            'Ob Unfallschaden, Hauskauf, Baumangel oder Streit mit der Versicherung: Ein Gutachten hält fest, was tatsächlich vorliegt und was die Behebung kostet – und ist damit die Grundlage für Verhandlungen und notfalls für das Gericht. Auf :portal findest du über :companies Gutachterbüros und Sachverständige in ganz Deutschland, mit Fachgebiet, Adresse, Telefonnummer und Bewertungen.',
            'Achte auf das passende Fachgebiet: Ein Kfz-Sachverständiger bewertet keinen Dachstuhl, ein Bausachverständiger kein Unfallfahrzeug. Frag vorab nach Qualifikation und Preis – übliche Nachweise sind eine öffentliche Bestellung und Vereidigung durch die Kammer oder eine Zertifizierung nach DIN EN ISO/IEC 17024. Beim unverschuldeten Unfall darfst du den Gutachter selbst wählen.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Gutachter',
        'branch_plural' => 'Gutachter',
        'placeholder' => 'Fachgebiet oder Name',
        'quick_term' => 'Kfz-Gutachter',
        'seo' => [
            'headline' => 'Gutachter auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Wichtiger als der Preis ist die Qualifikation im richtigen Fachgebiet. „Sachverständiger" ist keine geschützte Bezeichnung – aussagekräftig sind die öffentliche Bestellung und Vereidigung durch eine Kammer, eine Zertifizierung nach DIN EN ISO/IEC 17024 oder die Zulassung als Prüfingenieur. Frag danach, bevor du beauftragst.',
                'Klär vorab, was das Gutachten kosten soll und was es umfasst: Ortstermin, Fotodokumentation, Schadenshöhe, Wiederbeschaffungswert, Restwert. Bei einem Unfall, den die Gegenseite verschuldet hat, trägt in der Regel deren Versicherung die Kosten des Gutachtens – bei Bagatellschäden kann das anders aussehen, dann reicht oft ein Kostenvoranschlag der Werkstatt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Gutachter in :city finden',
            'paragraphs' => [
                'Ein Gutachter aus :city oder der Umgebung kommt schneller zum Ortstermin, und genau der entscheidet über die Qualität des Gutachtens. Achte in der Liste auf Fachgebiet und Adresse und ruf direkt an.',
                'Nach einem Unfall zählt Tempo: Je früher der Schaden dokumentiert ist, desto weniger lässt sich später bestreiten. Bei Bau- und Immobilienthemen ist dagegen Vorlauf nötig – gute Sachverständige sind oft Wochen im Voraus ausgebucht, bei einer Kaufentscheidung lohnt der frühe Anruf.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Gutachter in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Gutachterbüro eingetragen ist, mit der Zahl der Büros. Auf der Stadtseite siehst du alle vor Ort mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
                'In kleinen Orten lohnt der Blick in die nächstgrößere Stadt: Sachverständige arbeiten überregional und fahren für einen Ortstermin auch 50 Kilometer und mehr. Die Anfahrt wird dann meist gesondert berechnet.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Gutachten',
        'text' => ':posts Artikel zu Kosten, Ablauf und Fachgebieten – geschrieben, damit du weißt, wann ein Gutachten nötig ist und was darin stehen muss.',
        'search_placeholder' => 'Thema suchen, z. B. Unfallgutachten',
        'category_icons' => [
            'kosten' => 'euro',
            'kfz' => 'car',
            'immobilien' => 'home',
            'bau' => 'shield-check',
            'schaden' => 'search',
            'versicherung' => 'text',
            'schimmel' => 'droplet',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es beurteilt?',
            'text' => ':companies Gutachterbüros sind hier eingetragen – mit Fachgebiet, Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Büro, dein Profil',
        'text' => ':companies Gutachterbüros sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Auftraggeber von deinem sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Sachverständiger', 'label' => 'Sachverständige:r'],
            ['query' => 'Ingenieur', 'label' => 'Ingenieur:in'],
            ['query' => 'Büro', 'label' => 'Büro & Verwaltung'],
            ['query' => 'Werkstudent', 'label' => 'Werkstudent:in'],
        ],
    ],

];
