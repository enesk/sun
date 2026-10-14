<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Metallbauer (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer metallbauer.io (Zuordnung in
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
    'brand_icon' => 'settings',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Metallbauer in deiner Nähe',
        'text' => 'Über :companies Betriebe für Metall- und Stahlbau mit echten Bewertungen, Referenzen und direkter Telefonnummer – von der Schlosserei bis zum Treppengeländer.',
        'search_placeholder' => 'Metallbaubetrieb oder Leistung, z. B. Schlosserei',
        'search_button' => 'Metallbaubetrieb finden',
        'popular' => ['Schlosserei', 'Stahlbau', 'Tore & Zäune', 'Treppen'],
    ],

    'services' => [
        ['label' => 'Metallbau', 'query' => 'Metallbau', 'icon' => 'settings', 'intro' => ':anzahl übernehmen klassischen Metallbau'], // 6.522 Treffer
        ['label' => 'Schlosserei', 'query' => 'Schlosserei', 'icon' => 'lock'], // 1.550 Treffer
        ['label' => 'Stahlbau', 'query' => 'Stahlbau', 'icon' => 'grid'], // 605 Treffer
        ['label' => 'Edelstahl', 'query' => 'Edelstahl', 'icon' => 'sparkles'], // 155 Treffer
        ['label' => 'Schmiedearbeiten', 'query' => 'Schmiede', 'icon' => 'flame'], // 170 Treffer
        ['label' => 'Treppen', 'query' => 'Treppen', 'icon' => 'list-ordered'], // 51 Treffer
        ['label' => 'Tore & Zäune', 'query' => 'Tore', 'icon' => 'grid'], // 26 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Metallbauer finden – so geht\'s',
        'paragraphs' => [
            'Ob ein neues Treppengeländer, ein Stahlträger im Rohbau oder eine Schlüsseldienst-Reparatur am Schloss: Metallbaubetriebe decken ein breites Feld ab, und nicht jeder Betrieb macht alles. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir bei individuellen Anfertigungen – Geländer, Tore, Treppen – ein Aufmaß vor Ort machen, bevor du ein Angebot annimmst. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Metallbauer',
        'branch_plural' => 'Metallbauer',
        'placeholder' => 'Metallbaubetrieb oder Leistung',
        'quick_term' => 'Metallbau',
        'seo' => [
            'headline' => 'Metallbaubetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Preis, sondern auch, aus welchem Material gefertigt wird und ob eine Oberflächenbehandlung gegen Rost im Angebot steht. Achte auf Bewertungen mit konkretem Inhalt, gerade bei individuellen Anfertigungen.',
                'Bei größeren Arbeiten lohnen sich zwei bis drei Angebote. Lass dir Maße und Material vorab schriftlich bestätigen, damit es beim Einbau keine Überraschung gibt. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Metallbauer in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann für Aufmaß und Einbau kurzfristig vor Ort sein – gerade bei Toren, Geländern und Schlossarbeiten zählt das. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei individuellen Anfertigungen lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest, damit Maße und Wünsche von Anfang an passen.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Metallbaubetriebe in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Metallbaubetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Metallbaubetriebe fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Metallbau',
        'text' => ':posts Artikel zu Kosten, Material und Ablauf – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Treppen',
        'category_icons' => [
            'kosten' => 'euro',
            'schlosserei' => 'lock',
            'stahlbau' => 'grid',
            'edelstahl' => 'sparkles',
            'treppen' => 'list-ordered',
            'tore-zaeune' => 'grid',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Metallbaubetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Metallbaubetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Metallbauer', 'label' => 'Metallbauer'],
            ['query' => 'Schlosser', 'label' => 'Schlosser'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Schweißer', 'label' => 'Schweißer'],
        ],
    ],

];
