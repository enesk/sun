<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Energieberater (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer energieberaterportal.net
| (Zuordnung in config/themes/sun-v2-branches.php). Nur abweichende
| Schluessel stehen hier; Listen ersetzen die Vorgabe vollstaendig.
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
    'brand_icon' => 'chart',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Energieberater in deiner Nähe',
        'text' => 'Über :companies Büros für Energieberatung und Energieausweis mit echten Bewertungen und direkter Telefonnummer.',
        'search_placeholder' => 'Energieberater oder Leistung, z. B. Energieausweis',
        'search_button' => 'Energieberater finden',
        'popular' => ['Energieberatung', 'Energieausweis', 'Thermografie', 'Sachverständige'],
    ],

    'services' => [
        ['label' => 'Energieberatung', 'query' => 'Energieberatung', 'icon' => 'chart', 'intro' => ':anzahl bieten allgemeine Energieberatung'], // 2.765 Treffer
        ['label' => 'Energieausweis', 'query' => 'Energieausweis', 'icon' => 'text'], // 39 Treffer
        ['label' => 'Thermografie', 'query' => 'Thermografie', 'icon' => 'flame'], // 22 Treffer
        ['label' => 'Bauphysik', 'query' => 'Bauphysik', 'icon' => 'home'], // 82 Treffer
        ['label' => 'Sachverständige', 'query' => 'Sachverständiger', 'icon' => 'shield-check'], // 36 Treffer
        ['label' => 'Ingenieurbüro', 'query' => 'Ingenieur', 'icon' => 'settings'], // 95 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Energieberater finden – so geht\'s',
        'paragraphs' => [
            'Ob Energieausweis für den Verkauf, Förderantrag für eine Sanierung oder die Suche nach der größten Einsparung: Welches Büro du beauftragst, entscheidet über die Qualität der Empfehlung und darüber, ob eine Förderung überhaupt bewilligt wird. Auf :portal findest du über :companies Büros in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Frag vorab, ob das Büro zur Förderberatung berechtigt ist (Eintrag in der Energieeffizienz-Expertenliste), falls du einen Zuschuss beantragen willst. Über den Anruf-Button erreichst du das Büro direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Energieberater',
        'branch_plural' => 'Energieberater',
        'placeholder' => 'Energieberatungsbüro oder Leistung',
        'quick_term' => 'Energieberatung',
        'seo' => [
            'headline' => 'Energieberater auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur das Honorar, sondern auch, ob eine Vor-Ort-Begehung und ein schriftlicher Bericht mit Maßnahmenkatalog im Angebot enthalten sind. Achte auf Bewertungen zu Verständlichkeit der Beratung.',
                'Kläre vorab, ob das Büro für deine geplante Förderung zugelassen ist, und lass dir den Ablauf bis zum fertigen Ausweis oder Bericht schriftlich erklären. Über den Anruf-Button erreichst du jedes Büro direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Energieberater in :city finden',
            'paragraphs' => [
                'Ein Büro in der Nähe kennt oft die örtliche Bausubstanz und kann für die Begehung kurzfristig vorbeikommen. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei größeren Sanierungsvorhaben lohnt sich ein ausführliches Erstgespräch, bevor du dich entscheidest.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Energieberater in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Energieberatungsbüro eingetragen ist, mit der Zahl der Büros. Auf der Stadtseite siehst du alle Büros vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenes Büro lohnt sich der Blick in die nächstgrößere Stadt: Viele Energieberater decken ein größeres Umland ab.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Energieberatung',
        'text' => ':posts Artikel zu Förderung, Energieausweis und Sanierung – geschrieben, damit du ein Angebot einordnen und mit dem Büro auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Förderung',
        'category_icons' => [
            'kosten' => 'euro',
            'energieausweis' => 'text',
            'foerderung' => 'chart',
            'thermografie' => 'flame',
            'sanierung' => 'wrench',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Energieberater sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Büro, dein Profil',
        'text' => ':companies Energieberater sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Büro sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Energieberater', 'label' => 'Energieberater'],
            ['query' => 'Ingenieur', 'label' => 'Ingenieur'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Werkstudent', 'label' => 'Werkstudent'],
        ],
    ],

];
