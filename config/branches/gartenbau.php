<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Garten- und Landschaftsbau (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer mjet.net (Zuordnung in
| config/themes/sun-v2-branches.php). Nur abweichende Schluessel stehen hier;
| Listen ersetzen die Vorgabe vollstaendig.
|
| Die Suchbegriffe der Leistungskacheln sind an den echten Betriebsdaten
| geprueft (Volltextsuche ueber name + description, Stand 14.10.2026).
| Achtung: Woerter unter vier Zeichen findet die Volltextsuche nicht.
|
*/

return [

    // Seitentitel, Meta-Beschreibung, der Eintragen-Aufruf und die
    // Staedte-Ueberschrift stehen nicht hier: sie kommen aus lang/de/portal.php
    // und setzen die Begriffe des Portals (tenant.terms) selbst ein.
    'brand_icon' => 'sun',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Garten- und Landschaftsbau',
        'text' => 'Über :companies Betriebe für Gartengestaltung und -pflege mit echten Bewertungen, Referenzen und direkter Telefonnummer – von der Rasenpflege bis zur Terrasse.',
        'search_placeholder' => 'Gartenbaubetrieb oder Leistung, z. B. Pflasterarbeiten',
        'search_button' => 'Gartenbaubetrieb finden',
        'popular' => ['Gartengestaltung', 'Gartenpflege', 'Baumpflege', 'Pflasterarbeiten'],
    ],

    'services' => [
        ['label' => 'Garten- und Landschaftsbau', 'query' => 'Landschaftsbau', 'icon' => 'sun', 'intro' => ':anzahl übernehmen Garten- und Landschaftsbau'], // 6.572 Treffer
        ['label' => 'Gartengestaltung', 'query' => 'Gartengestaltung', 'icon' => 'sparkles'], // 1.432 Treffer
        ['label' => 'Gartenpflege', 'query' => 'Gartenpflege', 'icon' => 'droplet'], // 874 Treffer
        ['label' => 'Baumpflege', 'query' => 'Baumpflege', 'icon' => 'wind'], // 607 Treffer
        ['label' => 'Pflasterarbeiten', 'query' => 'Pflasterarbeiten', 'icon' => 'grid'], // 111 Treffer
        ['label' => 'Baumfällung', 'query' => 'Baumfällung', 'icon' => 'wrench'], // 102 Treffer
        ['label' => 'Zaunbau', 'query' => 'Zaunbau', 'icon' => 'grid'], // 59 Treffer
        ['label' => 'Winterdienst', 'query' => 'Winterdienst', 'icon' => 'snowflake'], // 62 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Garten- und Landschaftsbau finden – so geht\'s',
        'paragraphs' => [
            'Ob Neuanlage, regelmäßige Pflege oder eine neue Terrasse: Garten- und Landschaftsbau ist Vertrauenssache, weil ein Großteil der Arbeit erst nach Monaten sichtbar wird – etwa, ob eine Pflanzung anwächst oder ein Pflasterbett den Winter übersteht. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir vor Auftragsbeginn einen Plan und ein schriftliches Angebot geben, besonders bei Pflasterarbeiten und Bepflanzung. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Garten- und Landschaftsbau',
        'branch_plural' => 'Garten- und Landschaftsbaubetriebe',
        'placeholder' => 'Gartenbaubetrieb oder Leistung',
        'quick_term' => 'Landschaftsbau',
        'seo' => [
            'headline' => 'Gartenbaubetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Preis, sondern auch, ob eine Pflanz- oder Anwuchsgarantie im Angebot steht. Frag nach Referenzen aus der Nachbarschaft oder der Region – so siehst du das Ergebnis im echten Zustand, nicht nur auf Fotos.',
                'Bei größeren Vorhaben lohnen sich zwei bis drei Angebote. Kläre vorab, wer das Material liefert und wer für Entsorgung von Erdaushub und Grünschnitt zuständig ist. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Garten- und Landschaftsbau in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kennt die Böden und das Klima vor Ort und kann bei Pflegeverträgen regelmäßig und kurzfristig kommen. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei größeren Projekten – Terrasse, Einfahrt, komplette Neugestaltung – lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Garten- und Landschaftsbaubetriebe in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Betrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Gartenbaubetriebe fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Garten und Grün',
        'text' => ':posts Artikel zu Kosten, Pflege und Gestaltung – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Gartenpflege',
        'category_icons' => [
            'kosten' => 'euro',
            'gestaltung' => 'sparkles',
            'pflege' => 'droplet',
            'baumpflege' => 'wind',
            'pflaster' => 'grid',
            'winterdienst' => 'snowflake',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Garten- und Landschaftsbaubetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Garten- und Landschaftsbaubetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Landschaftsgärtner', 'label' => 'Landschaftsgärtner'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Baumpfleger', 'label' => 'Baumpfleger'],
        ],
    ],

];
