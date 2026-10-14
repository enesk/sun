<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Maler (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer malerfinder.de (Zuordnung in
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
    'brand_icon' => 'sparkles',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Maler in deiner Nähe',
        'text' => 'Über :companies Betriebe für Maler- und Lackierarbeiten mit echten Bewertungen, Referenzen und direkter Telefonnummer – von der Raufasertapete bis zur Fassade.',
        'search_placeholder' => 'Malerbetrieb oder Leistung, z. B. Fassade',
        'search_button' => 'Malerbetrieb finden',
        'popular' => ['Malerbetrieb', 'Fassade', 'Trockenbau', 'Lackierer'],
    ],

    'services' => [
        ['label' => 'Malerarbeiten', 'query' => 'Malerbetrieb', 'icon' => 'sparkles', 'intro' => ':anzahl übernehmen klassische Malerarbeiten'], // 3.801 Treffer
        ['label' => 'Lackierarbeiten', 'query' => 'Lackierer', 'icon' => 'droplet'], // 279 Treffer
        ['label' => 'Stuckateur', 'query' => 'Stuckateur', 'icon' => 'home'], // 238 Treffer
        ['label' => 'Trockenbau', 'query' => 'Trockenbau', 'icon' => 'grid'], // 162 Treffer
        ['label' => 'Raumausstattung', 'query' => 'Raumausstattung', 'icon' => 'home'], // 86 Treffer
        ['label' => 'Verputzarbeiten', 'query' => 'Verputz', 'icon' => 'wrench'], // 44 Treffer
        ['label' => 'Fassade', 'query' => 'Fassade', 'icon' => 'home'], // 35 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Maler finden – so geht\'s',
        'paragraphs' => [
            'Ob neuer Anstrich, Tapezieren oder eine Fassadensanierung: Welchen Malerbetrieb du beauftragst, entscheidet über Haltbarkeit und sauberes Arbeiten – von abgeklebten Kanten bis zur Entsorgung alter Tapeten. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir bei größeren Flächen ein Angebot mit Materialangabe geben und frag, ob Abkleben und Abdecken von Möbeln enthalten sind. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Maler',
        'branch_plural' => 'Maler',
        'placeholder' => 'Malerbetrieb oder Leistung',
        'quick_term' => 'Malerbetrieb',
        'seo' => [
            'headline' => 'Malerbetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Quadratmeterpreis, sondern auch, welches Material verwendet wird und wie viele Anstriche im Preis enthalten sind. Achte auf Bewertungen zu Sauberkeit und Termintreue.',
                'Bei Fassadenarbeiten lohnen sich zwei bis drei Angebote, da Gerüststellung und Vorarbeiten den Preis stark beeinflussen. Lass dir Farbton und Material vorab schriftlich bestätigen. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Maler in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann für Aufmaß und Arbeiten kurzfristig vor Ort sein. Achte in der Liste auf die Adresse und ruf direkt an, um Termin und Preis zu klären.',
                'Bei größeren Vorhaben – Fassade, komplette Wohnung – lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Maler in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Malerbetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Malerbetriebe fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund ums Streichen und Renovieren',
        'text' => ':posts Artikel zu Kosten, Material und Ablauf – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Fassade',
        'category_icons' => [
            'kosten' => 'euro',
            'anstrich' => 'sparkles',
            'fassade' => 'home',
            'trockenbau' => 'grid',
            'lackieren' => 'droplet',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Malerbetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Malerbetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Maler', 'label' => 'Maler'],
            ['query' => 'Lackierer', 'label' => 'Lackierer'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
        ],
    ],

];
