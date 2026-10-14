<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Schlüsseldienst (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer schluesseldienstportal.com
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
    'brand_icon' => 'lock',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deinen Schlüsseldienst in deiner Nähe',
        'text' => 'Über :companies Betriebe für Türöffnung, Notdienst und Sicherheitstechnik mit echten Bewertungen und direkter Telefonnummer.',
        'search_placeholder' => 'Schlüsseldienst oder Leistung, z. B. Türöffnung',
        'search_button' => 'Schlüsseldienst finden',
        'popular' => ['Türöffnung', 'Notdienst', 'Schließanlagen', 'Sicherheitstechnik'],
    ],

    'services' => [
        ['label' => 'Schlüsseldienst', 'query' => 'Schlüsseldienst', 'icon' => 'lock', 'intro' => ':anzahl bieten allgemeinen Schlüsseldienst'], // 570 Treffer
        ['label' => 'Notdienst', 'query' => 'Schlüsselnotdienst', 'icon' => 'bolt'], // 36 Treffer
        ['label' => 'Türöffnung', 'query' => 'Türöffnung', 'icon' => 'wrench'], // 8 Treffer
        ['label' => 'Sicherheitstechnik', 'query' => 'Sicherheitstechnik', 'icon' => 'shield-check'], // 50 Treffer
        ['label' => 'Schließanlagen', 'query' => 'Schließanlagen', 'icon' => 'settings'], // 6 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Schlüsseldienst finden – so geht\'s',
        'paragraphs' => [
            'Ob zugefallene Tür, verlorener Schlüssel oder eine neue Schließanlage fürs Mehrfamilienhaus: Bei einem Schlüsseldienst zählt vor allem, dass der Preis vor Arbeitsbeginn feststeht. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und lass dir am Telefon einen Festpreis oder zumindest eine realistische Preisspanne nennen, bevor jemand losfährt. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Schlüsseldienst',
        'branch_plural' => 'Schlüsseldienste',
        'placeholder' => 'Schlüsseldienst oder Leistung',
        'quick_term' => 'Schlüsseldienst',
        'seo' => [
            'headline' => 'Schlüsseldienst auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Frag am Telefon nach dem Gesamtpreis inklusive Anfahrt, bevor du beauftragst – seriöse Betriebe nennen eine Hausnummer, keine vage Spanne. Sei besonders vorsichtig bei Anrufnummern, die nicht zum angezeigten Ort passen.',
                'Bei einer neuen Schließanlage lohnen sich zwei bis drei Angebote, da hier Material und Montage den Preis stärker bestimmen als bei einer einfachen Türöffnung. Über den Anruf-Button erreichst du jeden eingetragenen Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Schlüsseldienst in :city finden',
            'paragraphs' => [
                'Bei einer zugefallenen Tür zählt die Anfahrtszeit: Ein Betrieb mit Sitz in oder nahe :city ist meist schneller da als einer aus einem anderen Ort. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Frag auch am Telefon noch einmal nach dem Festpreis, bevor der Betrieb losfährt – unabhängig davon, wie dringend es ist.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Schlüsseldienste in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Schlüsseldienst eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Schlüsseldienste fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Schlüssel und Sicherheit',
        'text' => ':posts Artikel zu Kosten, Notdienst und Sicherheitstechnik – geschrieben, damit du einen Festpreis einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Notdienst',
        'category_icons' => [
            'kosten' => 'euro',
            'tueroeffnung' => 'wrench',
            'notdienst' => 'bolt',
            'sicherheitstechnik' => 'shield-check',
            'schliessanlagen' => 'settings',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Schlüsseldienste sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und ruf direkt an.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Schlüsseldienste sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Monteur', 'label' => 'Monteur'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Techniker', 'label' => 'Techniker'],
            ['query' => 'Aushilfe', 'label' => 'Aushilfe'],
        ],
    ],

];
