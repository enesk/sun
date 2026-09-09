<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feed-Liste der Content-Pipeline (#11)
|--------------------------------------------------------------------------
|
| Der RssFeedConnector liest ausschliesslich diese Datei. Ein neuer Feed
| braucht keinen Code: Eintrag unter 'global' (gilt fuer alle Mandanten) oder
| unter 'branches.<branchen-schluessel>' (nur fuer Portale dieser Branche).
| Die Branchen-Schluessel sind die von App\Content\Support\BranchResolver.
|
| Felder je Feed:
|   key              Pflicht, stabil, geht in source_items.external_id ein.
|   name             Anzeigename der Quelle (Quellenangabe im Artikel).
|   url              Feed-Adresse (RSS oder Atom).
|   type             source_items.source_type: 'rss', 'legal' oder 'funding'.
|   region_scope     'national' oder 'state'.
|   region_code      ISO-3166-2 bei region_scope 'state' (z. B. 'DE-BY').
|   signal_strength  0..1, Grundgewicht der Quelle (Standard: defaults).
|   max_items        Hoechstzahl Eintraege je Lauf (Standard: defaults).
|   filter_by_keywords  true = nur Eintraege mit Branchen-Keyword-Treffer.
|   enabled          false schaltet einen Feed ab, ohne ihn zu loeschen.
|
| Hinweis zu den Adressen: Behoerdenfeeds aendern ihre URL gelegentlich. Ein
| Feed, der dauerhaft 404 liefert, taucht als Fehler im Quellen-Monitor auf
| und wird hier korrigiert oder auf enabled = false gesetzt — der Connector
| selbst scheitert an einem einzelnen toten Feed nicht.
|
*/

return [

    'defaults' => [
        'type' => 'rss',
        'region_scope' => 'national',
        'signal_strength' => 0.35,
        'max_items' => 20,

        // Bundesweite Rechts- und Foerderfeeds sind thematisch breit; ohne
        // Keyword-Filter wuerde jeder Bundesanzeiger-Eintrag ein Rohsignal.
        'filter_by_keywords' => true,
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bundesweit, fuer alle Branchen
    |--------------------------------------------------------------------------
    */

    'global' => [
        [
            'key' => 'bgbl',
            'name' => 'Bundesgesetzblatt',
            'url' => 'https://www.recht.bund.de/rss/bgbl1.xml',
            'type' => 'legal',
            'signal_strength' => 0.6,
        ],
        [
            'key' => 'gesetze-im-internet',
            'name' => 'Gesetze im Internet (Neuigkeiten)',
            'url' => 'https://www.gesetze-im-internet.de/aktuell.rss',
            'type' => 'legal',
            'signal_strength' => 0.6,
        ],
        [
            'key' => 'bafa',
            'name' => 'BAFA',
            'url' => 'https://www.bafa.de/SiteGlobals/Functions/RSSFeed/DE/RSSNewsfeed/RSSNewsfeed.xml',
            'type' => 'funding',
            'signal_strength' => 0.7,
        ],
        [
            'key' => 'kfw',
            'name' => 'KfW',
            'url' => 'https://www.kfw.de/rss/pressemitteilungen.xml',
            'type' => 'funding',
            'signal_strength' => 0.6,
        ],
        [
            'key' => 'verbraucherzentrale',
            'name' => 'Verbraucherzentrale',
            'url' => 'https://www.verbraucherzentrale.de/rss/verbraucherzentrale-meldungen.rss',
            'type' => 'rss',
            'signal_strength' => 0.5,
        ],
        [
            'key' => 'zdh',
            'name' => 'Zentralverband des Deutschen Handwerks',
            'url' => 'https://www.zdh.de/rss.xml',
            'type' => 'rss',
            'signal_strength' => 0.5,
        ],
        [
            'key' => 'dihk',
            'name' => 'DIHK',
            'url' => 'https://www.dihk.de/de/rss',
            'type' => 'rss',
            'signal_strength' => 0.4,
        ],
        [
            'key' => 'bmwk',
            'name' => 'Bundesministerium fuer Wirtschaft und Klimaschutz',
            'url' => 'https://www.bmwk.de/SiteGlobals/BMWI/Forms/Listen/RSS/rss_pressemitteilungen.xml',
            'type' => 'rss',
            'signal_strength' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Landesministerien und Kammern, je Bundesland
    |--------------------------------------------------------------------------
    |
    | Laufen nur fuer Mandanten, die den passenden Regions-Zuschnitt zulassen
    | (tenant_content_settings.allowed_region_scopes_json) und das Land in
    | preferred_states_json fuehren — sonst produzieren 16 Laender-Feeds
    | Rohsignale, die kein Artikel je braucht.
    |
    */

    'states' => [
        [
            'key' => 'hwk-nrw',
            'name' => 'Handwerk NRW',
            'url' => 'https://www.handwerk-nrw.de/feed',
            'region_scope' => 'state',
            'region_code' => 'DE-NW',
            'signal_strength' => 0.45,
        ],
        [
            'key' => 'hwk-bayern',
            'name' => 'Handwerkskammer fuer Muenchen und Oberbayern',
            'url' => 'https://www.hwk-muenchen.de/rss/aktuelles.xml',
            'region_scope' => 'state',
            'region_code' => 'DE-BY',
            'signal_strength' => 0.45,
        ],
        [
            'key' => 'um-bw',
            'name' => 'Umweltministerium Baden-Wuerttemberg',
            'url' => 'https://um.baden-wuerttemberg.de/rss/pressemitteilungen',
            'region_scope' => 'state',
            'region_code' => 'DE-BW',
            'signal_strength' => 0.4,
        ],
        [
            'key' => 'ifb-hamburg',
            'name' => 'IFB Hamburg',
            'url' => 'https://www.ifbhh.de/rss/aktuelles',
            'type' => 'funding',
            'region_scope' => 'state',
            'region_code' => 'DE-HH',
            'signal_strength' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Branchenfeeds
    |--------------------------------------------------------------------------
    |
    | Schluessel = Branchen-Schluessel des BranchResolver. Diese Feeds sind
    | fachlich eng genug, dass der Keyword-Filter entfaellt.
    |
    */

    'branches' => [

        'sanitaer' => [
            [
                'key' => 'shk-zvshk',
                'name' => 'ZVSHK — Zentralverband Sanitaer Heizung Klima',
                'url' => 'https://www.zvshk.de/rss/aktuell.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
            [
                'key' => 'shk-bdh',
                'name' => 'BDH — Bundesverband der Deutschen Heizungsindustrie',
                'url' => 'https://www.bdh-industrie.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'elektro' => [
            [
                'key' => 'zveh',
                'name' => 'ZVEH — Zentralverband der Deutschen Elektrohandwerke',
                'url' => 'https://www.zveh.de/feed',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
        ],

        'solar-pv' => [
            [
                'key' => 'bsw-solar',
                'name' => 'Bundesverband Solarwirtschaft',
                'url' => 'https://www.solarwirtschaft.de/feed/',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
            [
                'key' => 'clearingstelle-eeg',
                'name' => 'Clearingstelle EEG|KWKG',
                'url' => 'https://www.clearingstelle-eeg-kwkg.de/rss.xml',
                'type' => 'legal',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
        ],

        'energieberatung' => [
            [
                'key' => 'dena',
                'name' => 'Deutsche Energie-Agentur',
                'url' => 'https://www.dena.de/newsroom/rss/',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'kfz' => [
            [
                'key' => 'zdk',
                'name' => 'ZDK — Zentralverband Deutsches Kraftfahrzeuggewerbe',
                'url' => 'https://www.kfzgewerbe.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
        ],

        'gartenbau' => [
            [
                'key' => 'bgl',
                'name' => 'Bundesverband Garten-, Landschafts- und Sportplatzbau',
                'url' => 'https://www.galabau.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'hoch-tiefbau' => [
            [
                'key' => 'zdb',
                'name' => 'Zentralverband des Deutschen Baugewerbes',
                'url' => 'https://www.zdb.de/rss',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'maler' => [
            [
                'key' => 'farbe-de',
                'name' => 'Bundesverband Farbe Gestaltung Bautenschutz',
                'url' => 'https://www.farbe.de/feed/',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'medizin' => [
            [
                'key' => 'gba',
                'name' => 'Gemeinsamer Bundesausschuss',
                'url' => 'https://www.g-ba.de/rss/pressemitteilungen/',
                'type' => 'legal',
                'filter_by_keywords' => false,
                'signal_strength' => 0.6,
            ],
            [
                'key' => 'bzaek',
                'name' => 'Bundeszahnaerztekammer',
                'url' => 'https://www.bzaek.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'tierarzt' => [
            [
                'key' => 'bundestieraerztekammer',
                'name' => 'Bundestieraerztekammer',
                'url' => 'https://www.bundestieraerztekammer.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'fahrschule' => [
            [
                'key' => 'mvf',
                'name' => 'Bundesvereinigung der Fahrlehrerverbaende',
                'url' => 'https://www.fahrlehrerverbaende.de/feed/',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],

        'spedition' => [
            [
                'key' => 'bgl-logistik',
                'name' => 'Bundesverband Gueterkraftverkehr Logistik und Entsorgung',
                'url' => 'https://www.bgl-ev.de/rss.xml',
                'filter_by_keywords' => false,
                'signal_strength' => 0.5,
            ],
        ],
    ],
];
