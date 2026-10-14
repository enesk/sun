<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Spedition (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'briefcase',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Transporte', 'query' => 'Transporte', 'icon' => 'briefcase'], // 2.310 Treffer
        ['label' => 'Spedition', 'query' => 'Spedition', 'icon' => 'map-lines'], // 1.201 Treffer
        ['label' => 'Logistik', 'query' => 'Logistik', 'icon' => 'grid'], // 1.032 Treffer
        ['label' => 'Umzüge', 'query' => 'Umzüge', 'icon' => 'home'], // 492 Treffer
        ['label' => 'Kurierdienst', 'query' => 'Kurier', 'icon' => 'bolt'], // 73 Treffer
        ['label' => 'Lagerung', 'query' => 'Lagerung', 'icon' => 'inbox'], // 37 Treffer
        ['label' => 'Fuhrunternehmen', 'query' => 'Fuhrunternehmen', 'icon' => 'car'], // 124 Treffer
    ],

    'search' => [
        'branch_singular' => 'Spedition',
        'branch_plural' => 'Spedition',
        'placeholder' => 'Speditionsbetrieb oder Leistung',
        'quick_term' => 'Transporte',
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
