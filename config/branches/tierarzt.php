<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Tierarzt (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
 * ACHTUNG Heilberuf: Werbeaussagen unterliegen engeren Regeln (HWG).
 * Die Texte aus #13 gibt Enes einzeln frei, bevor das Portal umgestellt wird.
 *
| FEHLT NOCH (#13, Texter): hero, seo, search.seo, city.seo,
| cities_index.seo, blog, login. Solange `_texte_fehlen` gesetzt ist, darf
| das Portal NICHT auf sun-v2 umgestellt werden — sonst stehen dort die
| Elektriker-Texte der Vorgabe. Pruefen mit `php artisan sun:branches:status`.
|
*/

return [

    '_texte_fehlen' => true,

    'brand_icon' => 'plus',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Tierarztpraxis', 'query' => 'Tierarzt', 'icon' => 'plus'], // 1.802 Treffer
        ['label' => 'Tierklinik', 'query' => 'Tierklinik', 'icon' => 'home'], // 87 Treffer
        ['label' => 'Kleintiere', 'query' => 'Kleintier', 'icon' => 'star'], // 16 Treffer
        ['label' => 'Pferde', 'query' => 'Pferde', 'icon' => 'users'], // 212 Treffer
        ['label' => 'Notdienst', 'query' => 'Notdienst', 'icon' => 'bolt'], // 26 Treffer
        ['label' => 'Tierheilpraktiker', 'query' => 'Tierheilpraktiker', 'icon' => 'droplet'], // 36 Treffer
    ],

    'search' => [
        'branch_singular' => 'Tierarzt',
        'branch_plural' => 'Tierarzt',
        'placeholder' => 'Tierarztpraxis oder Leistung',
        'quick_term' => 'Tierarzt',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Tiermedizinische', 'label' => 'Tiermedizinische'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Tierarzt', 'label' => 'Tierarzt'],
            ['query' => 'Praxismanagement', 'label' => 'Praxismanagement'],
        ],
    ],

];
