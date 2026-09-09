<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Statistiktabellen der Content-Pipeline (#11)
|--------------------------------------------------------------------------
|
| Der StatisticsConnector holt woechentlich genau die hier genannten
| Datensaetze und legt daraus fact_snippets an — Kennzahl, Einheit, Region,
| Quelle, Abrufdatum, Haltbarkeit. Ein neuer Datensatz ist ein Eintrag in
| dieser Datei, kein Code.
|
| Zwei Quellen:
|
|   genesis  GENESIS-Online (Destatis) bzw. die Regionalstatistik. Beide
|            sprechen dieselbe REST-Schnittstelle 2020 und liefern mit
|            format=ffcsv eine flache CSV-Datei, die sich ohne Kenntnis der
|            Tabellenstruktur auswerten laesst.
|
|   dwd      Regionalmittel des Deutschen Wetterdienstes (Open Data, ohne
|            Zugangsdaten). Nur Monats- und Saisonwerte, ausdruecklich kein
|            Wetterbericht — die Zahlen begruenden Saisonaussagen ("im
|            Februar liegt das Mittel in Bayern bei ...").
|
| Felder je GENESIS-Tabelle:
|   key           Praefix des fact_key, z. B. 'baupreisindex'.
|   name          Klartextname, wird zur Quellenangabe.
|   code          Tabellencode bei GENESIS (z. B. '61261-0002').
|   endpoint      'destatis' oder 'regionalstatistik' (siehe 'endpoints').
|   unit          Einheit, falls die Datei keine mitliefert.
|   valid_days    Haltbarkeit des Fakts in Tagen.
|   area          'national' oder 'state' — bestimmt, ob die Zeilen je
|                 Bundesland aufgeteilt werden.
|   statement     Satzvorlage mit :value, :unit, :period, :region.
|
| Felder je DWD-Datensatz:
|   key, name, unit, valid_days, statement wie oben.
|   url           Vorlage mit :month (zweistellig).
|
*/

return [

    'endpoints' => [
        'destatis' => [
            'base_url' => env('GENESIS_BASE_URL', 'https://www-genesis.destatis.de/genesisWS/rest/2020'),
            'username' => env('GENESIS_USERNAME'),
            'password' => env('GENESIS_PASSWORD'),
            'source_name' => 'Statistisches Bundesamt (Destatis)',
        ],
        'regionalstatistik' => [
            'base_url' => env('REGIONALSTATISTIK_BASE_URL', 'https://www.regionalstatistik.de/genesisws/rest/2020'),
            'username' => env('REGIONALSTATISTIK_USERNAME', env('GENESIS_USERNAME')),
            'password' => env('REGIONALSTATISTIK_PASSWORD', env('GENESIS_PASSWORD')),
            'source_name' => 'Regionalstatistik (Statistische Aemter des Bundes und der Laender)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabellen fuer alle Branchen
    |--------------------------------------------------------------------------
    */

    'genesis' => [
        [
            'key' => 'baupreisindex',
            'name' => 'Baupreisindex Wohngebaeude',
            'code' => '61261-0002',
            'endpoint' => 'destatis',
            'unit' => 'Index (2021 = 100)',
            'area' => 'national',
            'valid_days' => 120,
            'statement' => 'Der Baupreisindex fuer Wohngebaeude liegt bei :value :unit (:period, Statistisches Bundesamt).',
        ],
        [
            'key' => 'handwerkszaehlung-betriebe',
            'name' => 'Handwerkszaehlung: Betriebe',
            'code' => '53111-0001',
            'endpoint' => 'destatis',
            'unit' => 'Betriebe',
            'area' => 'national',
            'valid_days' => 365,
            'statement' => 'Im deutschen Handwerk gibt es :value :unit (:period, Handwerkszaehlung).',
        ],
        [
            'key' => 'verbraucherpreisindex',
            'name' => 'Verbraucherpreisindex',
            'code' => '61111-0001',
            'endpoint' => 'destatis',
            'unit' => 'Index (2020 = 100)',
            'area' => 'national',
            'valid_days' => 60,
            'statement' => 'Der Verbraucherpreisindex liegt bei :value :unit (:period, Statistisches Bundesamt).',
        ],
        [
            'key' => 'handwerk-betriebe-land',
            'name' => 'Handwerksbetriebe je Bundesland',
            'code' => '53111-01-01-4',
            'endpoint' => 'regionalstatistik',
            'unit' => 'Betriebe',
            'area' => 'state',
            'valid_days' => 365,
            'statement' => 'In :region sind :value :unit im Handwerk gemeldet (:period, Regionalstatistik).',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Branchenspezifische Tabellen
    |--------------------------------------------------------------------------
    |
    | Schluessel = Branchen-Schluessel des BranchResolver. Diese Tabellen
    | laufen zusaetzlich zu den obigen, aber nur fuer Portale der Branche.
    |
    */

    'genesis_branches' => [
        'sanitaer' => [
            [
                'key' => 'heizungsstruktur-neubau',
                'name' => 'Baugenehmigungen nach vorwiegender Heizenergie',
                'code' => '31111-0009',
                'endpoint' => 'destatis',
                'unit' => 'Wohnungen',
                'area' => 'national',
                'valid_days' => 180,
                'statement' => 'In Neubauten wurden :value :unit mit dieser Heizenergie genehmigt (:period, Statistisches Bundesamt).',
            ],
        ],
        'solar-pv' => [
            [
                'key' => 'stromerzeugung-solar',
                'name' => 'Stromerzeugung aus Photovoltaik',
                'code' => '43312-0001',
                'endpoint' => 'destatis',
                'unit' => 'GWh',
                'area' => 'national',
                'valid_days' => 120,
                'statement' => 'Die Stromerzeugung aus Photovoltaik betraegt :value :unit (:period, Statistisches Bundesamt).',
            ],
        ],
        'kfz' => [
            [
                'key' => 'kfz-bestand',
                'name' => 'Kraftfahrzeugbestand',
                'code' => '46251-0001',
                'endpoint' => 'destatis',
                'unit' => 'Fahrzeuge',
                'area' => 'national',
                'valid_days' => 180,
                'statement' => 'Der Bestand liegt bei :value :unit (:period, Kraftfahrt-Bundesamt/Destatis).',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DWD-Regionalmittel
    |--------------------------------------------------------------------------
    |
    | Die Dateien liegen je Monat vor und enthalten eine Spalte je Bundesland
    | plus eine Spalte 'Deutschland'. Abgerufen wird das langjaehrige Mittel
    | des laufenden und des naechsten Monats — mehr braucht der Saisonbezug
    | eines Ratgebers nicht.
    |
    */

    'dwd' => [
        'enabled' => (bool) env('CONTENT_STATISTICS_DWD_ENABLED', true),

        // Wie viele Monate ab dem laufenden abgerufen werden.
        'months_ahead' => 2,

        // Ueber so viele Jahre wird das Mittel gebildet.
        'reference_years' => 30,

        'datasets' => [
            [
                'key' => 'dwd-temperatur',
                'name' => 'DWD Regionalmittel Lufttemperatur',
                'url' => 'https://opendata.dwd.de/climate_environment/CDC/regional_averages_DE/monthly/air_temperature_mean/regional_averages_tm_:month.txt',
                'unit' => '°C',
                'valid_days' => 365,
                'statement' => 'Im :month_name liegt die mittlere Lufttemperatur in :region bei :value :unit (DWD, Mittel der letzten :years Jahre).',
            ],
            [
                'key' => 'dwd-niederschlag',
                'name' => 'DWD Regionalmittel Niederschlag',
                'url' => 'https://opendata.dwd.de/climate_environment/CDC/regional_averages_DE/monthly/precipitation/regional_averages_rr_:month.txt',
                'unit' => 'mm',
                'valid_days' => 365,
                'statement' => 'Im :month_name fallen in :region im Mittel :value :unit Niederschlag (DWD, Mittel der letzten :years Jahre).',
            ],
        ],
    ],
];
