<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Sanitaer und Heizung (Theme sun-v2, #47)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer sanitaerfinden.com (Zuordnung in
| config/themes/sun-v2-branches.php). Nur abweichende Schluessel stehen hier;
| Listen ersetzen die Vorgabe vollstaendig.
|
| Achtung bei den Suchbegriffen der Kacheln: auf diesem Portal haben nur 31
| von 19.644 Betrieben eine Beschreibung, die Volltextsuche greift also fast
| nur auf den Firmennamen. Die Begriffe unten sind an den echten Daten
| gemessen (Stand 01.10.2026); Kacheln ohne Treffer blendet der
| HomeViewComposer aus. Sobald die Beschreibungen generiert sind, steigen die
| Zahlen und die Liste kann wachsen.
|
*/

return [

    'brand_icon' => 'droplet',

    // Ratgeber ist auf diesem Portal noch leer (0 Themen, 0 Artikel)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Sanitärbetrieb in deiner Nähe',
        'text' => 'Über :companies Betriebe für Bad, Heizung und Notdienst – mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
        'search_placeholder' => 'Betrieb oder Leistung, z. B. Heizung',
        'search_button' => 'Betrieb finden',
        'popular' => ['Heizung', 'Notdienst', 'Badsanierung', 'Rohrreinigung'],
    ],

    'services' => [
        ['label' => 'Heizung', 'query' => 'Heizung', 'icon' => 'flame', 'intro' => ':anzahl davon arbeiten an Heizungen'],
        ['label' => 'Lüftung', 'query' => 'Lüftung', 'icon' => 'wind'],
        ['label' => 'Bad & Sanierung', 'query' => 'Badsanierung', 'icon' => 'shower'],
        ['label' => 'Sanitärinstallation', 'query' => 'Sanitärinstallation', 'icon' => 'wrench'],
        ['label' => 'Rohrreinigung', 'query' => 'Rohrreinigung', 'icon' => 'pipe'],
        ['label' => 'Notdienst', 'query' => 'Notdienst', 'icon' => 'bolt', 'intro' => ':anzahl fahren Notdienst'],
        ['label' => 'Kundendienst', 'query' => 'Kundendienst', 'icon' => 'settings'],
        ['label' => 'Fliesen', 'query' => 'Fliesen', 'icon' => 'grid'],
    ],

    'seo' => [
        'headline' => 'Den richtigen Sanitärbetrieb finden – so geht\'s',
        'paragraphs' => [
            'Ob tropfender Hahn, verstopfter Abfluss, neue Heizung oder ein ganzes Bad: Arbeiten an Trinkwasser- und Heizungsanlagen gehören in die Hände eines eingetragenen Fachbetriebs – schon weil Versorger und Versicherung im Schadensfall danach fragen. Auf :portal findest du über :companies Betriebe in ganz Deutschland, mit Adresse, Telefonnummer und Bewertungen von Kund:innen, die dort schon Kunde waren.',
            'Gib deinen Ort oder deine Postleitzahl ein und ruf direkt an. Bei einem Wasserrohrbruch zählt jede Minute: Hauptabsperrhahn zu, dann einen Betrieb mit Notdienst aus der Nähe anrufen. Für planbare Vorhaben – Bad, Heizungstausch, Wärmepumpe – hol dir zwei bis drei Angebote und lass dir die Leistungen schriftlich aufschlüsseln.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Sanitärbetrieb',
        'branch_plural' => 'Sanitärbetriebe',
        'placeholder' => 'Betrieb oder Leistung',
        'quick_term' => 'Notdienst',
        'seo' => [
            'headline' => 'Sanitärbetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Achte auf den Eintrag in der Handwerksrolle und – beim Thema Trinkwasser und Gas – auf die Eintragung im Installateurverzeichnis des örtlichen Versorgers. Ohne die darf ein Betrieb an der Hausanschlussleitung gar nicht arbeiten. Bewertungen mit konkretem Inhalt sagen mehr als eine reine Sternezahl.',
                'Lass dir vor größeren Arbeiten ein schriftliches Angebot geben, in dem Material, Arbeitszeit und Anfahrt getrennt stehen. Beim Notdienst gilt: Nach dem Preis fragen, bevor jemand losfährt – Zuschläge für Nacht, Wochenende und Feiertag sind üblich, sollten aber vorher genannt werden.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Sanitärbetrieb in :city finden',
            'paragraphs' => [
                'Im Notfall zählt die Entfernung: Ein Betrieb aus der Nähe ist meist schneller da als ein günstigerer vom anderen Ende von :city. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei planbaren Vorhaben – Badsanierung, Heizungstausch, Wärmepumpe – lohnt sich der Blick auf die Leistungsangaben und ein früher Termin: Gute Betriebe sind oft Wochen im Voraus ausgebucht. Für den Heizungstausch gibt es Förderungen; viele Betriebe übernehmen den Antrag mit.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Sanitärbetriebe in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Betrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
                'Wohnst du in einem kleinen Ort ohne eigenen Betrieb, lohnt sich der Blick in die nächstgrößere Stadt: Viele Sanitärbetriebe fahren 20 bis 30 Kilometer zu ihren Kund:innen, im Notdienst oft auch weiter.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Bad und Heizung',
        'text' => ':posts Artikel zu Kosten, Technik und Förderung – geschrieben, damit du ein Angebot prüfen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Wärmepumpe',
        'category_icons' => [
            'kosten' => 'euro',
            'heizung' => 'flame',
            'bad' => 'shower',
            'badsanierung' => 'shower',
            'waermepumpe' => 'wind',
            'foerderung' => 'euro',
            'notfall' => 'bolt',
            'trinkwasser' => 'droplet',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Sanitärbetriebe sind hier eingetragen – mit Bewertungen, Öffnungszeiten und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Sanitärbetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Anlagenmechaniker', 'label' => 'Anlagenmechaniker SHK'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Kundendienst', 'label' => 'Kundendienst'],
        ],
    ],

];
