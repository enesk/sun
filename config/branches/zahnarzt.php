<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Zahnarzt (Theme sun-v2, #44/#45)
|--------------------------------------------------------------------------
|
| Texte freigegeben von Enes, 14.10.2026 (#13).
|
| Ueberlagert config/themes/sun-v2.php fuer zahnarzt.firmenfreund.de
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
        'headline' => 'Finde deine Zahnarztpraxis in deiner Nähe',
        'text' => 'Über :companies Praxen für Zahnmedizin, Kieferorthopädie und Implantologie mit Sprechzeiten und direkter Telefonnummer.',
        'search_placeholder' => 'Zahnarztpraxis oder Leistung, z. B. Kieferorthopädie',
        'search_button' => 'Zahnarztpraxis finden',
        'popular' => ['Kieferorthopädie', 'Implantologie', 'Zahnmedizin', 'Oralchirurgie'],
    ],

    'services' => [
        ['label' => 'Zahnarztpraxis', 'query' => 'Zahnarztpraxis', 'icon' => 'plus', 'intro' => ':anzahl bieten allgemeine Zahnmedizin'], // 7.564 Treffer
        ['label' => 'Kieferorthopädie', 'query' => 'Kieferorthopädie', 'icon' => 'grid'], // 569 Treffer
        ['label' => 'Implantologie', 'query' => 'Implantologie', 'icon' => 'settings'], // 295 Treffer
        ['label' => 'Oralchirurgie', 'query' => 'Oralchirurgie', 'icon' => 'wrench'], // 296 Treffer
        ['label' => 'Zahnmedizin', 'query' => 'Zahnmedizin', 'icon' => 'star'], // 652 Treffer
        ['label' => 'Dentallabor', 'query' => 'Dental', 'icon' => 'briefcase'], // 337 Treffer
    ],

    'seo' => [
        'headline' => 'Die richtige Zahnarztpraxis finden – so geht\'s',
        'paragraphs' => [
            'Ob die halbjährliche Kontrolle, eine Zahnspange für dein Kind oder ein Termin zur Implantatberatung: Auf :portal findest du über :companies Praxen in ganz Deutschland – mit Adresse, Leistungsschwerpunkt und direkter Telefonnummer.',
            'Gib deinen Ort oder deine Postleitzahl ein und filtere nach der Leistung, die du suchst. Ruf vorab an, um Sprechzeiten zu klären und ob die Praxis neue Patient:innen aufnimmt.',
        ],
    ],

    'search' => [
        'branch_singular' => 'Zahnarzt',
        'branch_plural' => 'Zahnarzt',
        'placeholder' => 'Zahnarztpraxis oder Leistung',
        'quick_term' => 'Zahnarztpraxis',
        'seo' => [
            'headline' => 'Zahnarztpraxis auswählen: Worauf du achten kannst',
            'paragraphs' => [
                'Achte auf die angegebenen Schwerpunkte – nicht jede Praxis bietet Kieferorthopädie oder Implantologie selbst an und überweist stattdessen. Bei größeren Behandlungen lohnt sich ein ausführliches Beratungsgespräch vor Behandlungsbeginn.',
                'Lass dir einen Heil- und Kostenplan schriftlich geben, bevor eine kostenpflichtige Behandlung beginnt – das gibt dir Klarheit über den Eigenanteil.',
            ],
        ],
    ],

    'city' => [
        'seo' => [
            'headline' => 'Zahnarztpraxis in :city finden',
            'paragraphs' => [
                'Eine Praxis in der Nähe erleichtert regelmäßige Kontrolltermine. Achte in der Liste auf Adresse und Leistungsschwerpunkt.',
                'Bei einer größeren Behandlung lohnt sich ein erstes Beratungsgespräch vor Ort, bevor du dich entscheidest.',
            ],
        ],
    ],

    'cities_index' => [
        'seo' => [
            'headline' => 'Zahnarztpraxen in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens eine Praxis eingetragen ist, mit der Zahl der Praxen. Auf der Stadtseite siehst du alle Praxen vor Ort mit Leistungsschwerpunkt und Telefonnummer.',
                'In kleineren Orten ohne eigene Praxis lohnt sich der Blick in die nächstgrößere Stadt, besonders bei Kieferorthopädie oder Implantologie.',
            ],
        ],
    ],

    'blog' => [
        'headline' => 'Ratgeber rund um Zahngesundheit',
        'text' => ':posts Artikel, geschrieben, damit du dich vor dem Praxisbesuch informieren kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Zahnspange',
        'category_icons' => [
            'kieferorthopaedie' => 'grid',
            'implantologie' => 'settings',
            'oralchirurgie' => 'wrench',
        ],
        'cta' => [
            'headline' => 'Lieber in der Praxis nachfragen?',
            'text' => ':companies Zahnarztpraxen sind hier eingetragen – mit Leistungsschwerpunkt und Telefonnummer. Gib deinen Ort ein und ruf direkt an.',
        ],
    ],

    'login' => [
        'headline' => 'Deine Praxis, dein Profil',
        'text' => ':companies Zahnarztpraxen sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Patient:innen von deiner Praxis sehen.',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Zahnmedizinische', 'label' => 'Zahnmedizinische'],
            ['query' => 'Zahnarzt', 'label' => 'Zahnarzt'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Praxismanagement', 'label' => 'Praxismanagement'],
        ],
    ],

];
