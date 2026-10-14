<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Maler (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'sparkles',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Malerarbeiten', 'query' => 'Malerbetrieb', 'icon' => 'sparkles'], // 3.801 Treffer
        ['label' => 'Lackierarbeiten', 'query' => 'Lackierer', 'icon' => 'droplet'], // 279 Treffer
        ['label' => 'Stuckateur', 'query' => 'Stuckateur', 'icon' => 'home'], // 238 Treffer
        ['label' => 'Trockenbau', 'query' => 'Trockenbau', 'icon' => 'grid'], // 162 Treffer
        ['label' => 'Raumausstattung', 'query' => 'Raumausstattung', 'icon' => 'home'], // 86 Treffer
        ['label' => 'Verputzarbeiten', 'query' => 'Verputz', 'icon' => 'wrench'], // 44 Treffer
        ['label' => 'Fassade', 'query' => 'Fassade', 'icon' => 'home'], // 35 Treffer
    ],

    'search' => [
        'branch_singular' => 'Maler',
        'branch_plural' => 'Maler',
        'placeholder' => 'Malerbetrieb oder Leistung',
        'quick_term' => 'Malerbetrieb',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Maler', 'label' => 'Maler'],
            ['query' => 'Lackierer', 'label' => 'Lackierer'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
        ],
    ],

];
