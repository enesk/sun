<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Solarteur (Theme sun-v2) — GERUEST, Texte fehlen (#12)
|--------------------------------------------------------------------------
|
| Enthalten ist nur, was an den echten Betriebsdaten gemessen oder fachlich
| eindeutig ist: Markensymbol, Leistungskacheln mit ihren Suchbegriffen,
| Branchenbegriffe der Suche und die Schnellfilter der Jobboerse.
|
| Die Trefferzahl hinter jeder Kachel ist am 14.10.2026 auf der Produktion
| gemessen (Volltextsuche ueber name + description). Kacheln ohne Treffer
| sind nicht aufgefuehrt; Woerter unter vier Zeichen findet die Suche nicht.
|
| FEHLT NOCH (#13, Texter): hero, seo, search.seo, city.seo,
| cities_index.seo, blog, login. Solange `_texte_fehlen` gesetzt ist, darf
| das Portal NICHT auf sun-v2 umgestellt werden — sonst stehen dort die
| Elektriker-Texte der Vorgabe. Pruefen mit `php artisan sun:branches:status`.
|
*/

return [

    '_texte_fehlen' => true,

    'brand_icon' => 'sun',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Photovoltaik', 'query' => 'Photovoltaik', 'icon' => 'sun'], // 1.185 Treffer
        ['label' => 'Solartechnik', 'query' => 'Solartechnik', 'icon' => 'bolt'], // 303 Treffer
        ['label' => 'Solaranlagen', 'query' => 'Solaranlagen', 'icon' => 'sun'], // 152 Treffer
        ['label' => 'Energietechnik', 'query' => 'Energietechnik', 'icon' => 'plug'], // 231 Treffer
        ['label' => 'Wärmepumpe', 'query' => 'Wärmepumpe', 'icon' => 'wind'], // 48 Treffer
        ['label' => 'Stromspeicher', 'query' => 'Speicher', 'icon' => 'battery'], // 18 Treffer
        ['label' => 'Montage', 'query' => 'Montage', 'icon' => 'wrench'], // 94 Treffer
    ],

    'search' => [
        'branch_singular' => 'Solarteur',
        'branch_plural' => 'Solarteur',
        'placeholder' => 'Solarbetrieb oder Leistung',
        'quick_term' => 'Photovoltaik',
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
