<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Fliesenleger (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer fliesenleger.io (Zuordnung in
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
        'headline' => 'Finde deinen Fliesenleger in deiner Nähe',
        'text' => 'Über :companies Betriebe für Fliesen, Naturstein und Badsanierung mit echten Bewertungen, Referenzen und direkter Telefonnummer.',
        'search_placeholder' => 'Fliesenlegerbetrieb oder Leistung, z. B. Badsanierung',
        'search_button' => 'Fliesenlegerbetrieb finden',
        'popular' => ['Fliesen', 'Badsanierung', 'Naturstein', 'Mosaik'],
    ],

    'services' => [
        ['label' => 'Fliesenarbeiten', 'query' => 'Fliesen', 'icon' => 'grid', 'intro' => ':anzahl übernehmen klassische Fliesenarbeiten'], // 5.037 Treffer
        ['label' => 'Naturstein', 'query' => 'Naturstein', 'icon' => 'sparkles'], // 290 Treffer
        ['label' => 'Mosaik', 'query' => 'Mosaik', 'icon' => 'grid'], // 100 Treffer
        ['label' => 'Marmor', 'query' => 'Marmor', 'icon' => 'sparkles'], // 57 Treffer
        ['label' => 'Badsanierung', 'query' => 'Badsanierung', 'icon' => 'shower'], // 68 Treffer
        ['label' => 'Estrich', 'query' => 'Estrich', 'icon' => 'settings'], // 56 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Fliesenleger finden – so geht\'s',
        'paragraphs' => [
            'Ob neue Fliesen im Bad, eine Terrasse mit Naturstein oder die komplette Badsanierung: Fliesenarbeiten sind nur so gut wie der Untergrund darunter, und Mängel zeigen sich oft erst Monate später – etwa, wenn Fugen reißen oder Fliesen hohl liegen. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir bei einer Badsanierung den Ablauf inklusive Abdichtung schriftlich bestätigen und frag nach Referenzfotos ähnlicher Projekte. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Fliesenleger',
        'branch_plural' => 'Fliesenleger',
        'placeholder' => 'Fliesenlegerbetrieb oder Leistung',
        'quick_term' => 'Fliesen',
        'seo' => [
            'headline' => 'Fliesenlegerbetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Quadratmeterpreis, sondern auch, ob Abdichtung, Untergrundvorbereitung und Entsorgung alter Fliesen im Angebot enthalten sind. Achte auf Bewertungen mit konkretem Inhalt zu Sauberkeit und Termintreue.',
                'Bei größeren Flächen oder einer Badsanierung lohnen sich zwei bis drei Angebote. Lass dir Material und Verlegemuster vorab schriftlich bestätigen. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Fliesenleger in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann für Aufmaß und Arbeiten kurzfristig vor Ort sein und kennt oft schon örtliche Zulieferer für Fliesen und Material. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei einer Badsanierung lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest, damit Maße, Material und Zeitplan von Anfang an passen.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Fliesenleger in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Fliesenlegerbetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Fliesenleger fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Fliesen und Bad',
        'text' => ':posts Artikel zu Kosten, Material und Ablauf – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Badsanierung',
        'category_icons' => [
            'kosten' => 'euro',
            'fliesen' => 'grid',
            'naturstein' => 'sparkles',
            'badsanierung' => 'shower',
            'estrich' => 'settings',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Fliesenlegerbetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Fliesenlegerbetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Fliesenleger', 'label' => 'Fliesenleger'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Bauhelfer', 'label' => 'Bauhelfer'],
        ],
    ],

];
