<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Gerüstbauer (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Ueberlagert config/themes/sun-v2.php fuer geruestbauer.gmbh (Zuordnung in
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
        'headline' => 'Finde deinen Gerüstbauer in deiner Nähe',
        'text' => 'Über :companies Betriebe für Gerüstbau und -verleih mit echten Bewertungen und direkter Telefonnummer – für Fassade, Dach und Baustelle.',
        'search_placeholder' => 'Gerüstbaubetrieb oder Leistung, z. B. Gerüstverleih',
        'search_button' => 'Gerüstbaubetrieb finden',
        'popular' => ['Gerüstbau', 'Gerüstverleih', 'Baugerüst', 'Fassade'],
    ],

    'services' => [
        ['label' => 'Gerüstbau', 'query' => 'Gerüstbau', 'icon' => 'grid', 'intro' => ':anzahl übernehmen klassischen Gerüstbau'], // 3.308 Treffer
        ['label' => 'Gerüste mieten', 'query' => 'Gerüstverleih', 'icon' => 'briefcase'], // 110 Treffer
        ['label' => 'Baugerüst', 'query' => 'Gerüste', 'icon' => 'home'], // 131 Treffer
    ],

    'seo' => [
        'headline' => 'Den richtigen Gerüstbauer finden – so geht\'s',
        'paragraphs' => [
            'Ob für die Fassadensanierung, Dacharbeiten oder einen Neubau: Ein Gerüst muss sicher stehen und termingerecht da sein, sonst verzögert sich jedes folgende Gewerk. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungsschwerpunkten und Bewertungen.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der anstehenden Leistung. Lass dir Auf- und Abbautermin sowie die Standzeit schriftlich bestätigen, damit sich Folgegewerke daran orientieren können. Über den Anruf-Button erreichst du den Betrieb direkt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Gerüstbauer',
        'branch_plural' => 'Gerüstbauer',
        'placeholder' => 'Gerüstbaubetrieb oder Leistung',
        'quick_term' => 'Gerüstbau',
        'seo' => [
            'headline' => 'Gerüstbaubetrieb auswählen: Worauf du achten solltest',
            'paragraphs' => [
                'Vergleiche nicht nur den Mietpreis, sondern auch, ob Auf- und Abbau sowie die Prüfung des Gerüsts im Angebot enthalten sind. Achte auf Bewertungen zu Termintreue, gerade bei kurzen Standzeiten.',
                'Kläre vorab die genaue Standzeit und was bei Verlängerung berechnet wird. Über den Anruf-Button erreichst du jeden Betrieb direkt und kannst den Termin mit anderen Gewerken abstimmen.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Gerüstbauer in :city finden',
            'paragraphs' => [
                'Ein Betrieb in der Nähe kann Auf- und Abbau kurzfristig einplanen und spart bei Anfahrt und Transport. Achte in der Liste auf die Adresse und ruf direkt an, um Verfügbarkeit zu klären.',
                'Bei größeren Fassaden lohnt sich ein Vor-Ort-Termin, bevor du dich entscheidest, damit Höhe und Zugänglichkeit vorab klar sind.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Gerüstbauer in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Gerüstbaubetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen und direkter Telefonnummer.',
                'In kleineren Orten ohne eigenen Betrieb lohnt sich der Blick in die nächstgrößere Stadt: Viele Gerüstbaubetriebe fahren ein größeres Umland an.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Gerüstbau',
        'text' => ':posts Artikel zu Kosten, Ablauf und Sicherheit – geschrieben, damit du ein Angebot einordnen und mit dem Betrieb auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Standzeit',
        'category_icons' => [
            'kosten' => 'euro',
            'gerüstbau' => 'grid',
            'gerüstverleih' => 'briefcase',
            'sicherheit' => 'shield-check',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Gerüstbaubetriebe sind hier eingetragen – mit Bewertungen und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    'login' => [
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Gerüstbaubetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem Betrieb sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Gerüstbauer', 'label' => 'Gerüstbauer'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Kolonnenführer', 'label' => 'Kolonnenführer'],
            ['query' => 'Meister', 'label' => 'Meister'],
        ],
    ],

];
