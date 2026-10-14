<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Bauunternehmen (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer firmenfreund.de (Zuordnung in
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
    'brand_icon' => 'home',

    // Ratgeber ist auf diesem Portal noch leer (Befund #12, 14.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde dein Bauunternehmen in deiner Nähe',
        'text' => 'Über :companies Betriebe für Hoch-, Tief- und Rohbau mit echten Bewertungen, Referenzen und direkter Telefonnummer – vom Erdaushub bis zur Schlüsselübergabe.',
        'search_placeholder' => 'Bauunternehmen oder Leistung, z. B. Tiefbau',
        'search_button' => 'Bauunternehmen finden',
        'popular' => ['Tiefbau', 'Rohbau', 'Sanierung', 'Abbruch'],
    ],

    'services' => [
        ['label' => 'Hoch- und Tiefbau', 'query' => 'Tiefbau', 'icon' => 'home', 'intro' => ':anzahl übernehmen Hoch- und Tiefbau'], // 1.849 Treffer
        ['label' => 'Rohbau & Maurerarbeiten', 'query' => 'Bauunternehmen', 'icon' => 'grid'], // 2.861 Treffer
        ['label' => 'Straßen- und Pflasterbau', 'query' => 'Straßenbau', 'icon' => 'map-lines'], // 537 Treffer
        ['label' => 'Erdarbeiten', 'query' => 'Erdbau', 'icon' => 'settings'], // 438 Treffer
        ['label' => 'Abbruch & Rückbau', 'query' => 'Abbruch', 'icon' => 'trash'], // 303 Treffer
        ['label' => 'Sanierung', 'query' => 'Sanierung', 'icon' => 'wrench'], // 346 Treffer
        ['label' => 'Baustoffe', 'query' => 'Baustoffe', 'icon' => 'briefcase'], // 341 Treffer
        ['label' => 'Bauservice', 'query' => 'Bauservice', 'icon' => 'clock'], // 741 Treffer
    ],

    'seo' => [
        'headline' => 'Das richtige Bauunternehmen finden – so geht\'s',
        'paragraphs' => [
            'Ob Rohbau für den Neubau, Tiefbauarbeiten für Keller und Leitungen oder die Sanierung eines Bestandsgebäudes: Der Betrieb, den du beauftragst, entscheidet über Termintreue, Preis und am Ende über die Qualität des Ergebnisses. Auf :portal findest du über :companies Bauunternehmen in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen von Kund:innen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir vor Auftragsvergabe ein schriftliches Angebot mit Leistungsbeschreibung geben und frag nach Referenzen aus ähnlichen Projekten. Über den Anruf-Button erreichst du den Betrieb direkt – ohne Umweg über ein Kontaktformular.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Bauunternehmen',
        'branch_plural' => 'Bauunternehmen',
        'placeholder' => 'Bauunternehmen oder Leistung',
        'quick_term' => 'Tiefbau',
        'seo' => [
            'headline' => 'Bauunternehmen auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Angebotspreis, sondern auch, was darin enthalten ist: Entsorgung, Baustelleneinrichtung und Nachbesserungen gehören in jedes seriöse Angebot. Achte auf Bewertungen mit konkretem Inhalt und darauf, ob der Betrieb Erfahrung mit Projekten in deiner Größenordnung hat.',
                'Bei größeren Vorhaben lohnen sich zwei bis drei Angebote zum Vergleich. Vereinbare Zahlungen nach Baufortschritt statt vollständig im Voraus und lass dir Zwischenstände schriftlich bestätigen. Über den Anruf-Button erreichst du jeden Betrieb direkt.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Bauunternehmen in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kennt oft die örtlichen Gegebenheiten – Bodenverhältnisse, Genehmigungswege, Handwerkerkontakte – und kann kurzfristig auf der Baustelle sein. Achte in der Liste auf die Adresse und ruf direkt an, um Verfügbarkeit und Rahmenbedingungen zu klären.',
                'Bei größeren Vorhaben lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest. So lässt sich der Aufwand realistisch einschätzen, statt nur nach Telefonangaben zu planen.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Bauunternehmen in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Bauunternehmen eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Bauunternehmen decken ein größeres Umland ab, als ihr Firmensitz vermuten lässt.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund ums Bauen',
        'text' => ':posts Artikel zu Kosten, Ablauf und Gewerken – geschrieben, damit du ein Angebot einordnen und mit dem Bauunternehmen auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Rohbau',
        'category_icons' => [
            'kosten' => 'euro',
            'rohbau' => 'home',
            'tiefbau' => 'settings',
            'sanierung' => 'wrench',
            'abbruch' => 'trash',
            'baustoffe' => 'briefcase',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Bauunternehmen sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Bauunternehmen sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Bauleiter', 'label' => 'Bauleiter'],
            ['query' => 'Maurer', 'label' => 'Maurer'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Baugeräteführer', 'label' => 'Baugeräteführer'],
        ],
    ],

];
