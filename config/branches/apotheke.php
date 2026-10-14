<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Apotheke (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Texte freigegeben von Enes, 14.10.2026 (#13).
|
| Ueberlagert config/themes/sun-v2.php fuer apotheke.firmenfreund.de
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
        'headline' => 'Finde deine Apotheke in deiner Nähe',
        'text' => 'Über :companies Apotheken mit Öffnungszeiten, Notdienst-Hinweis und direkter Telefonnummer.',
        'search_placeholder' => 'Apotheke oder Leistung, z. B. Notdienst',
        'search_button' => 'Apotheke finden',
        'popular' => ['Notdienst', 'Versandapotheke', 'Sanitätshaus', 'Homöopathie'],
    ],

    'services' => [
        ['label' => 'Apotheke vor Ort', 'query' => 'Apotheke', 'icon' => 'plus', 'intro' => ':anzahl führen ein Sortiment vor Ort'], // 14.612 Treffer
        ['label' => 'Notdienst', 'query' => 'Notdienst', 'icon' => 'bolt'], // 6 Treffer
        ['label' => 'Versandapotheke', 'query' => 'Versandapotheke', 'icon' => 'inbox'], // 21 Treffer
        ['label' => 'Sanitätshaus', 'query' => 'Sanitätshaus', 'icon' => 'briefcase'], // 22 Treffer
        ['label' => 'Homöopathie', 'query' => 'Homöopathie', 'icon' => 'droplet'], // 5 Treffer
    ],

    'seo' => [
        'headline' => 'Die richtige Apotheke finden – so geht\'s',
        'paragraphs' => [
            'Ob ein Rezept, eine kurzfristige Frage zur Einnahme oder der nächste Nacht- und Notdienst in deiner Region: Auf :portal findest du über :companies Apotheken in ganz Deutschland – mit Adresse, Öffnungszeiten und direkter Telefonnummer.',
            'Gib deinen Ort oder deine Postleitzahl ein und ruf vorab an, wenn du ein bestimmtes Präparat brauchst – so sparst du dir den Weg, falls es gerade nicht vorrätig ist. Den aktuellen Nacht- und Notdienst in deiner Region zeigt dir die Apotheke vor Ort oder die amtliche Notdienstsuche.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Apotheke',
        'branch_plural' => 'Apotheke',
        'placeholder' => 'Apotheke oder Leistung',
        'quick_term' => 'Apotheke',
        'seo' => [
            'headline' => 'Apotheke auswählen: Worauf du achten kannst',
            'paragraphs' => [
                'Achte auf die Öffnungszeiten und darauf, ob die Apotheke eine Beratung zu Wechselwirkungen anbietet, falls du mehrere Medikamente gleichzeitig nimmst. Bei Versandapotheken lohnt sich der Blick auf die Lieferzeit.',
                'Für wiederkehrende Rezepte fragst du am besten direkt nach, ob eine Vorbestellung möglich ist – das spart Wartezeit vor Ort.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Apotheke in :city finden',
            'paragraphs' => [
                'Eine Apotheke in der Nähe spart Wege, gerade wenn ein Rezept kurzfristig eingelöst werden muss. Achte in der Liste auf die Adresse und die Öffnungszeiten.',
                'Den Nacht- und Notdienst in :city zeigt dir die jeweilige Apotheke oder die amtliche Notdienstsuche – die Zuteilung wechselt turnusmäßig.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Apotheken in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Apotheke eingetragen ist, mit der Zahl der Apotheken. Auf der Stadtseite siehst du alle Apotheken vor Ort mit Öffnungszeiten und Telefonnummer.',
                'In kleineren Orten ohne eigene Apotheke lohnt sich der Blick in die nächstgrößere Stadt oder eine Versandapotheke für nicht eilige Bestellungen.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Apotheke und Medikamente',
        'text' => ':posts Artikel, geschrieben, damit du dich vor dem Apothekenbesuch informieren kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Notdienst',
        'category_icons' => [
            'notdienst' => 'bolt',
            'versandapotheke' => 'inbox',
            'homoeopathie' => 'droplet',
        ],
        'cta' => [
            'headline' => 'Lieber in der Apotheke nachfragen?',
            'text' => ':companies Apotheken sind hier eingetragen – mit Öffnungszeiten und Telefonnummer. Gib deinen Ort ein und ruf direkt an.',
        ],
    ],

    'login' => [
        'headline' => 'Deine Apotheke, dein Profil',
        'text' => ':companies Apotheken sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deiner Apotheke sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Pharmazeutisch', 'label' => 'Pharmazeutisch'],
            ['query' => 'Apotheker', 'label' => 'Apotheker'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Pharmazie', 'label' => 'Pharmazie'],
        ],
    ],

];
