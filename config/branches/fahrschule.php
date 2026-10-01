<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Fahrschule (Theme sun-v2, #48)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer fahrschulefinder.de (Zuordnung in
| config/themes/sun-v2-branches.php).
|
| Leistungskacheln gibt es hier bewusst nicht: keiner der 16.399 Betriebe hat
| eine Beschreibung, die Volltextsuche greift also nur auf den Firmennamen.
| Gemessen am 01.10.2026 kaeme keine Kachel ueber 37 Treffer (Motorrad 37,
| Fahrlehrer 22, Berufskraftfahrer 20, alles andere einstellig). Eine leere
| Liste laesst den Abschnitt entfallen; sobald Beschreibungen vorliegen,
| koennen Fuehrerscheinklassen und Kursarten als Kacheln nachgezogen werden.
|
*/

return [

    'brand_icon' => 'car',

    // Ratgeber ist auf diesem Portal noch leer (0 Themen, 0 Artikel)
    'nav' => [
        'guide' => false,
    ],

    'services' => [],

    'hero' => [
        'headline' => 'Finde deine Fahrschule in deiner Nähe',
        'text' => 'Über :companies Fahrschulen mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer – vom ersten Führerschein bis zur Aufbaustufe.',
        'search_placeholder' => 'Fahrschule oder Ort, z. B. Köln',
        'search_button' => 'Fahrschule finden',
        'popular' => ['Motorrad', 'Berufskraftfahrer', 'Verkehrsschule', 'Bootsschule'],
    ],

    'seo' => [
        'headline' => 'Die richtige Fahrschule finden – so geht\'s',
        'paragraphs' => [
            'Die Fahrschule entscheidet darüber, wie schnell, wie sicher und zu welchem Preis du den Führerschein bekommst. Auf :portal findest du über :companies Fahrschulen in ganz Deutschland – mit Adresse, Telefonnummer, Öffnungszeiten und Bewertungen von Fahrschüler:innen, die dort gelernt haben.',
            'Vergleiche nicht nur die Grundgebühr: Entscheidend sind der Preis je Fahrstunde, die Zahl der Sonderfahrten und die Gebühren für Vorstellung und Prüfung. Frag nach, wie viele Fahrstunden im Schnitt nötig sind und ob der Theorieunterricht auch online läuft. Viele Fahrschulen bieten ein kostenloses Kennenlerngespräch an – nutz das, bevor du den Vertrag unterschreibst.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Fahrschule',
        'branch_plural' => 'Fahrschulen',
        'placeholder' => 'Fahrschule oder Leistung',
        'quick_term' => 'Motorrad',
        'seo' => [
            'headline' => 'Fahrschule auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Die Entfernung zählt mehr, als man denkt: Zu einer Fahrschule in der Nähe kommst du auch nach Feierabend, und die Prüfungsstrecken liegen in deinem gewohnten Umfeld. Achte auf Bewertungen, die etwas über die Fahrlehrer:innen sagen, nicht nur über den Preis.',
                'Lass dir die Preisliste vollständig geben: Grundgebühr, Fahrstunde, Sonderfahrten für Überland, Autobahn und Nacht, dazu Vorstellungsentgelt und Lernmaterial. Erst die Summe ist vergleichbar. Frag auch, wie zügig Theorie- und Praxistermine vergeben werden – lange Wartezeiten sind teurer als ein paar Euro mehr pro Stunde.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Fahrschule in :city finden',
            'paragraphs' => [
                'Nimm eine Fahrschule, die in :city ausbildet: Die Prüfung legst du dort ab, wo du geübt hast, und das ist ein echter Vorteil. Achte in der Liste auf die Adresse und ruf direkt an – die meisten Fahrschulen vergeben Termine telefonisch.',
                'Frag beim ersten Gespräch nach dem Preis je Fahrstunde, nach den Sonderfahrten und danach, wie schnell du einen Theorie- und Prüfungstermin bekommst. Gute Fahrschulen in :city sind oft Wochen im Voraus ausgebucht.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Fahrschulen in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Fahrschule eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Fahrschulen vor Ort mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
                'In kleinen Orten lohnt der Blick in die nächstgrößere Stadt: Viele Fahrschulen holen ihre Schüler:innen zur Fahrstunde ab oder haben dort eine Zweigstelle mit eigenem Theorieunterricht.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um den Führerschein',
        'text' => ':posts Artikel zu Kosten, Prüfung und Ablauf – geschrieben, damit du weißt, was auf dich zukommt, bevor du einen Vertrag unterschreibst.',
        'search_placeholder' => 'Thema suchen, z. B. Kosten',
        'category_icons' => [
            'kosten' => 'euro',
            'theorie' => 'text',
            'praxis' => 'car',
            'pruefung' => 'shield-check',
            'motorrad' => 'car',
            'fuehrerschein' => 'car',
        ],
        'cta' => [
            'headline' => 'Lieber direkt nachfragen?',
            'text' => ':companies Fahrschulen sind hier eingetragen – mit Bewertungen, Öffnungszeiten und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Deine Fahrschule, dein Profil',
        'text' => ':companies Fahrschulen sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Fahrschüler:innen von deiner sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Fahrlehrer', 'label' => 'Fahrlehrer:in'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Büro', 'label' => 'Büro & Verwaltung'],
            ['query' => 'Aushilfe', 'label' => 'Aushilfe'],
        ],
    ],

];
