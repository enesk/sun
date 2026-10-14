<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Spedition (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer speditionportal.com (Zuordnung in
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
    'brand_icon' => 'briefcase',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deine Spedition in deiner Nähe',
        'text' => 'Über :companies Betriebe für Transport, Logistik und Umzüge mit echten Bewertungen und direkter Telefonnummer – vom Kurierauftrag bis zur Komplettladung.',
        'search_placeholder' => 'Spedition oder Leistung, z. B. Umzüge',
        'search_button' => 'Spedition finden',
        'popular' => ['Transporte', 'Umzüge', 'Logistik', 'Lagerung'],
    ],

    'services' => [
        ['label' => 'Transporte', 'query' => 'Transporte', 'icon' => 'briefcase', 'intro' => ':anzahl übernehmen allgemeine Transporte'], // 2.310 Treffer
        ['label' => 'Spedition', 'query' => 'Spedition', 'icon' => 'map-lines'], // 1.201 Treffer
        ['label' => 'Logistik', 'query' => 'Logistik', 'icon' => 'grid'], // 1.032 Treffer
        ['label' => 'Umzüge', 'query' => 'Umzüge', 'icon' => 'home'], // 492 Treffer
        ['label' => 'Kurierdienst', 'query' => 'Kurier', 'icon' => 'bolt'], // 73 Treffer
        ['label' => 'Lagerung', 'query' => 'Lagerung', 'icon' => 'inbox'], // 37 Treffer
        ['label' => 'Fuhrunternehmen', 'query' => 'Fuhrunternehmen', 'icon' => 'car'], // 124 Treffer
    ],

    'seo' => [
        'headline' => 'Die richtige Spedition finden – so geht\'s',
        'paragraphs' => [
            'Ob Umzug, Komplettladung oder regelmäßige Kurierfahrten: Der Betrieb, den du beauftragst, entscheidet über Termintreue und darüber, wie sorgsam mit deiner Ladung umgegangen wird. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir bei Umzügen und größeren Transporten ein schriftliches Angebot mit Leistungsumfang geben, inklusive Versicherung der Ladung. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Spedition',
        'branch_plural' => 'Speditionen',
        'placeholder' => 'Speditionsbetrieb oder Leistung',
        'quick_term' => 'Transporte',
        'seo' => [
            'headline' => 'Spedition auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Preis, sondern auch, ob eine Transportversicherung für die Ladung im Angebot enthalten ist. Achte bei Umzügen auf Bewertungen zu Pünktlichkeit und zum Umgang mit Möbeln.',
                'Bei größeren Aufträgen lohnen sich zwei bis drei Angebote. Kläre vorab Abhol- und Lieferadresse, Zeitfenster und ob Be- und Entladung im Preis enthalten sind. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Spedition in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann kurzfristig disponieren und spart bei regelmäßigen Fahrten Anfahrtszeit. Achte in der Liste auf die Adresse und ruf direkt an, um Verfügbarkeit und Preis zu klären.',
                'Bei einem Umzug lohnt sich eine Besichtigung vor Ort, bevor du dich entscheidest – so lässt sich der Aufwand realistisch einschätzen.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Speditionen in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Spedition eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Speditionen decken ein größeres Einzugsgebiet ab.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Transport und Umzug',
        'text' => ':posts Artikel zu Kosten, Ablauf und Versicherung – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Umzug',
        'category_icons' => [
            'kosten' => 'euro',
            'umzug' => 'home',
            'logistik' => 'grid',
            'kurier' => 'bolt',
            'lagerung' => 'inbox',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Speditionen sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Speditionen sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Berufskraftfahrer', 'label' => 'Berufskraftfahrer'],
            ['query' => 'Disponent', 'label' => 'Disponent'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Lagerlogistik', 'label' => 'Lagerlogistik'],
        ],
    ],

];
