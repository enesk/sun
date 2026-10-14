<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Metallbauer (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'settings',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Metallbau', 'query' => 'Metallbau', 'icon' => 'settings'], // 6.522 Treffer
        ['label' => 'Schlosserei', 'query' => 'Schlosserei', 'icon' => 'lock'], // 1.550 Treffer
        ['label' => 'Stahlbau', 'query' => 'Stahlbau', 'icon' => 'grid'], // 605 Treffer
        ['label' => 'Edelstahl', 'query' => 'Edelstahl', 'icon' => 'sparkles'], // 155 Treffer
        ['label' => 'Schmiedearbeiten', 'query' => 'Schmiede', 'icon' => 'flame'], // 170 Treffer
        ['label' => 'Treppen', 'query' => 'Treppen', 'icon' => 'list-ordered'], // 51 Treffer
        ['label' => 'Tore & Zäune', 'query' => 'Tore', 'icon' => 'grid'], // 26 Treffer
    ],

    'search' => [
        'branch_singular' => 'Metallbauer',
        'branch_plural' => 'Metallbauer',
        'placeholder' => 'Metallbaubetrieb oder Leistung',
        'quick_term' => 'Metallbau',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Metallbauer', 'label' => 'Metallbauer'],
            ['query' => 'Schlosser', 'label' => 'Schlosser'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Schweißer', 'label' => 'Schweißer'],
        ],
    ],

];
