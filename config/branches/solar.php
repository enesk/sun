<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Solarteur (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| PORTAL NICHT AUSGELIEFERT (#16). Die Domain firmenfreund.net zeigt an der
| Cloudflare-Kante auf solar-finden.de; Entscheidung Enes 14.10.2026,
| Variante A. Dieses Paket bekommt KEINE Texte und wird nicht umgestellt.
| Es bleibt liegen, damit nichts nachzubauen ist, falls die Entscheidung
| je zurueckgedreht wird. Hintergrund: docs/portale.md
|
| Ueberlagert config/themes/sun-v2.php fuer firmenfreund.net (Zuordnung in
| config/themes/sun-v2-branches.php). Nur abweichende Schluessel stehen hier;
| Listen ersetzen die Vorgabe vollstaendig.
|
| Die Suchbegriffe der Leistungskacheln sind an den echten Betriebsdaten
| geprueft (Volltextsuche ueber name + description, Stand 14.10.2026).
| Achtung: Woerter unter vier Zeichen findet die Volltextsuche nicht.
|
| Hinweis #16: firmenfreund.net leitet aktuell per 301 auf solar-finden.de
| weiter und ist von außen nicht erreichbar. Vor Umstellung auf sun-v2 klären.
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
        'headline' => 'Finde deinen Solarteur in deiner Nähe',
        'text' => 'Über :companies Betriebe für Photovoltaik und Solartechnik mit echten Bewertungen und direkter Telefonnummer – von der Planung bis zur Montage.',
        'search_placeholder' => 'Solarbetrieb oder Leistung, z. B. Photovoltaik',
        'search_button' => 'Solarbetrieb finden',
        'popular' => ['Photovoltaik', 'Solaranlagen', 'Wärmepumpe', 'Stromspeicher'],
    ],

    'services' => [
        ['label' => 'Photovoltaik', 'query' => 'Photovoltaik', 'icon' => 'sun', 'intro' => ':anzahl planen Photovoltaikanlagen'], // 1.185 Treffer
        ['label' => 'Solartechnik', 'query' => 'Solartechnik', 'icon' => 'bolt'], // 303 Treffer
        ['label' => 'Solaranlagen', 'query' => 'Solaranlagen', 'icon' => 'sun'], // 152 Treffer
        ['label' => 'Energietechnik', 'query' => 'Energietechnik', 'icon' => 'plug'], // 231 Treffer
        ['label' => 'Wärmepumpe', 'query' => 'Wärmepumpe', 'icon' => 'wind'], // 48 Treffer
        ['label' => 'Stromspeicher', 'query' => 'Speicher', 'icon' => 'battery'], // 18 Treffer
        ['label' => 'Montage', 'query' => 'Montage', 'icon' => 'wrench'], // 94 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Solarteur finden – so geht\'s',
        'paragraphs' => [
            'Ob Photovoltaik aufs Dach, ein Stromspeicher dazu oder eine Wärmepumpe: Die Auslegung der Anlage entscheidet über Jahre über Ertrag und Betriebskosten. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir eine Ertragsprognose für dein Dach und ein Angebot mit allen Komponenten (Module, Wechselrichter, Speicher) schriftlich geben. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Solarteur',
        'branch_plural' => 'Solarteure',
        'placeholder' => 'Solarbetrieb oder Leistung',
        'quick_term' => 'Photovoltaik',
        'seo' => [
            'headline' => 'Solarteur auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Preis pro Kilowattpeak, sondern auch, welche Komponenten verbaut werden und wie lange die Garantien darauf laufen. Achte auf Bewertungen zur Dauer von Planung bis Inbetriebnahme.',
                'Bei größeren Anlagen lohnen sich zwei bis drei Angebote mit vergleichbarer Leistung. Frag nach dem Ablauf der Anmeldung beim Netzbetreiber. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Solarteur in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann für Begehung und Montage kurzfristig vor Ort sein und kennt oft die Anmeldewege beim örtlichen Netzbetreiber. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei einer größeren Anlage lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest, damit Dachfläche und Ausrichtung realistisch eingeschätzt werden.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Solarteure in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Solarbetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Solarteure fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Photovoltaik',
        'text' => ':posts Artikel zu Kosten, Förderung und Technik – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Speicher',
        'category_icons' => [
            'kosten' => 'euro',
            'photovoltaik' => 'sun',
            'speicher' => 'battery',
            'waermepumpe' => 'wind',
            'foerderung' => 'chart',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Solarbetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Solarbetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Elektroniker', 'label' => 'Elektroniker'],
            ['query' => 'Monteur', 'label' => 'Monteur'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Projektleiter', 'label' => 'Projektleiter'],
        ],
    ],

];
