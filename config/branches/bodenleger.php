<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Bodenleger (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer bodenlegerfinden.com (Zuordnung in
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
    'brand_icon' => 'grid',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Bodenleger in deiner Nähe',
        'text' => 'Über :companies Betriebe für Parkett, Bodenbeläge und Estrich mit echten Bewertungen, Referenzen und direkter Telefonnummer.',
        'search_placeholder' => 'Bodenlegerbetrieb oder Leistung, z. B. Parkett',
        'search_button' => 'Bodenlegerbetrieb finden',
        'popular' => ['Parkett', 'Laminat', 'Estrich', 'Teppichboden'],
    ],

    'services' => [
        ['label' => 'Parkett', 'query' => 'Parkett', 'icon' => 'grid', 'intro' => ':anzahl verlegen Parkett'], // 1.646 Treffer
        ['label' => 'Bodenbeläge', 'query' => 'Bodenbeläge', 'icon' => 'grid'], // 1.044 Treffer
        ['label' => 'Estrich', 'query' => 'Estrich', 'icon' => 'settings'], // 464 Treffer
        ['label' => 'Fußboden verlegen', 'query' => 'Fußboden', 'icon' => 'wrench'], // 286 Treffer
        ['label' => 'Raumausstattung', 'query' => 'Raumausstatter', 'icon' => 'home'], // 259 Treffer
        ['label' => 'Teppichboden', 'query' => 'Teppich', 'icon' => 'grid'], // 88 Treffer
        ['label' => 'Laminat', 'query' => 'Laminat', 'icon' => 'grid'], // 67 Treffer
        ['label' => 'Schleifen & Aufarbeiten', 'query' => 'Schleifen', 'icon' => 'sparkles'], // 52 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Bodenleger finden – so geht\'s',
        'paragraphs' => [
            'Ob neues Parkett, Laminat in der Mietwohnung oder Estrich für den Neubau: Der Untergrund entscheidet über die Haltbarkeit, und genau der ist im fertigen Raum nicht mehr zu sehen. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir vor Arbeitsbeginn den Zustand des Untergrunds erklären und frag nach Referenzfotos ähnlicher Projekte. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Bodenleger',
        'branch_plural' => 'Bodenleger',
        'placeholder' => 'Bodenlegerbetrieb oder Leistung',
        'quick_term' => 'Parkett',
        'seo' => [
            'headline' => 'Bodenlegerbetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Quadratmeterpreis, sondern auch, ob Untergrundvorbereitung, Sockelleisten und Entsorgung des alten Bodens enthalten sind. Achte auf Bewertungen zu Sauberkeit und Termintreue.',
                'Bei größeren Flächen lohnen sich zwei bis drei Angebote. Lass dir Material und Verlegemuster vorab schriftlich bestätigen. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Bodenleger in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann für Aufmaß und Arbeiten kurzfristig vor Ort sein. Achte in der Liste auf die Adresse und ruf direkt an, um Termin und Preis zu klären.',
                'Bei größeren Flächen lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest, damit der Zustand des Untergrunds vorab klar ist.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Bodenleger in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Bodenlegerbetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Bodenleger fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Boden und Belag',
        'text' => ':posts Artikel zu Kosten, Material und Ablauf – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Estrich',
        'category_icons' => [
            'kosten' => 'euro',
            'parkett' => 'grid',
            'laminat' => 'grid',
            'estrich' => 'settings',
            'teppich' => 'grid',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Bodenlegerbetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Bodenlegerbetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Bodenleger', 'label' => 'Bodenleger'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Estrichleger', 'label' => 'Estrichleger'],
        ],
    ],

];
