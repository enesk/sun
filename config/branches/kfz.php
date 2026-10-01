<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Kfz-Werkstatt (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer kfzwerkstatt.io (Zuordnung in
| config/themes/sun-v2-branches.php). Nur abweichende Schluessel stehen hier;
| Listen ersetzen die Vorgabe vollstaendig.
|
| Die Suchbegriffe der Leistungskacheln sind an den echten Betriebsdaten
| geprueft (Volltextsuche ueber name + description, Stand 01.10.2026).
| Achtung: Woerter unter vier Zeichen findet die Volltextsuche nicht,
| deshalb "Hauptuntersuchung" statt "HU".
|
*/

return [

    // Seitentitel, Meta-Beschreibung, der Eintragen-Aufruf und die
    // Staedte-Ueberschrift stehen nicht hier: sie kommen aus lang/de/portal.php
    // und setzen die Begriffe des Portals (tenant.terms) selbst ein.
    'brand_icon' => 'wrench',

    // Ratgeber ist auf diesem Portal noch leer (Entscheidung Enes, 01.10.2026)
    'nav' => [
        'guide' => false,
    ],

    'hero' => [
        'headline' => 'Finde deine Kfz-Werkstatt in deiner Nähe',
        'text' => 'Über :companies Werkstätten mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer – vom Ölwechsel bis zur Unfallreparatur.',
        'search_placeholder' => 'Werkstatt oder Leistung, z. B. Inspektion',
        'search_button' => 'Werkstatt finden',
        'popular' => ['Inspektion', 'Hauptuntersuchung', 'Reifen', 'Klimaservice'],
    ],

    'services' => [
        ['label' => 'Inspektion & Wartung', 'query' => 'Inspektion', 'icon' => 'wrench', 'intro' => ':anzahl davon machen die Inspektion'],
        ['label' => 'HU & AU', 'query' => 'Hauptuntersuchung', 'icon' => 'shield-check', 'intro' => ':anzahl nehmen die Hauptuntersuchung ab'],
        ['label' => 'Reifen & Räder', 'query' => 'Reifen', 'icon' => 'tire'],
        ['label' => 'Ölwechsel', 'query' => 'Ölwechsel', 'icon' => 'droplet'],
        ['label' => 'Bremsen', 'query' => 'Bremsen', 'icon' => 'disc'],
        ['label' => 'Klimaservice', 'query' => 'Klimaservice', 'icon' => 'snowflake'],
        ['label' => 'Unfall & Karosserie', 'query' => 'Unfallinstandsetzung', 'icon' => 'car', 'intro' => ':anzahl übernehmen Unfallreparaturen'],
        ['label' => 'Autoelektrik', 'query' => 'Elektrik', 'icon' => 'battery'],
    ],

    'seo' => [
        'headline' => 'Die richtige Kfz-Werkstatt finden – so geht\'s',
        'paragraphs' => [
            'Ob fällige Inspektion, Termin zur Hauptuntersuchung, neue Bremsen oder ein Schaden nach dem Unfall: Welche Werkstatt du nimmst, entscheidet über Preis, Wartezeit und oft auch über die Garantie. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungen und Bewertungen von Kund:innen, die dort schon ihr Auto hatten.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach dem, was ansteht. Freie Werkstätten sind bei Wartung und Verschleißteilen meist günstiger, die Herstellergarantie bleibt trotzdem erhalten, solange nach Herstellervorgabe gearbeitet wird. Über den Anruf-Button erreichst du den Betrieb direkt – ohne Umweg über ein Kontaktformular.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Kfz-Werkstatt',
        'branch_plural' => 'Kfz-Werkstätten',
        'placeholder' => 'Werkstatt oder Leistung',
        'quick_term' => 'Hauptuntersuchung',
        'seo' => [
            'headline' => 'Werkstatt auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Stundensatz, sondern auch, was im Preis steckt: Eine Inspektion nach Herstellervorgabe umfasst mehr als ein Ölwechsel. Achte auf Bewertungen mit konkretem Inhalt, auf eine schriftliche Kostenschätzung vor der Reparatur und darauf, ob der Betrieb deine Marke regelmäßig betreut – bei Hybrid- und Elektrofahrzeugen ist das Pflicht, nicht Kür.',
                'Bei größeren Reparaturen lohnen sich zwei bis drei Angebote. Lass dir Altteile zeigen und den Auftrag vorab schriftlich geben. Über den Anruf-Button erreichst du jede Werkstatt direkt; wer lieber schreibt, nutzt die Kontaktdaten auf dem Profil.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Kfz-Werkstatt in :city finden',
            'paragraphs' => [
                'Für den Alltag zählt die Entfernung: Eine Werkstatt in der Nähe bedeutet kurze Wege beim Bringen und Abholen und oft auch einen schnelleren Termin als ein günstigerer Betrieb am anderen Ende von :city. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei planbaren Arbeiten – Inspektion, Hauptuntersuchung, Reifenwechsel – lohnt sich der Blick auf die Leistungsangaben und ein Anruf zwei bis drei Wochen vorher. Vor der Hauptuntersuchung prüfen viele Betriebe das Fahrzeug vorab durch, damit es nicht am Prüftag durchfällt.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Kfz-Werkstätten in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Werkstatt eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Werkstätten vor Ort mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
                'Wohnst du in einem kleinen Ort ohne eigene Werkstatt, lohnt sich der Blick in die nächstgrößere Stadt: Viele Betriebe holen das Fahrzeug auf Wunsch ab oder stellen einen Ersatzwagen, wenn die Reparatur länger dauert.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund ums Auto',
        'text' => ':posts Artikel zu Kosten, Wartung und Technik – geschrieben, damit du einen Kostenvoranschlag prüfen und mit der Werkstatt auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Inspektion',
        'category_icons' => [
            'kosten' => 'euro',
            'wartung' => 'wrench',
            'reifen' => 'tire',
            'bremsen' => 'disc',
            'hauptuntersuchung' => 'shield-check',
            'elektromobilitaet' => 'battery',
            'klima' => 'snowflake',
            'unfall' => 'car',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Kfz-Werkstätten sind hier eingetragen – mit Bewertungen, Öffnungszeiten und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Deine Werkstatt, dein Profil',
        'text' => ':companies Kfz-Werkstätten sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deiner sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Kfz-Mechatroniker', 'label' => 'Kfz-Mechatroniker'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Servicetechniker', 'label' => 'Servicetechniker'],
        ],
    ],

];
