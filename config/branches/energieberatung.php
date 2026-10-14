<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Energieberater (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'chart',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Energieberatung', 'query' => 'Energieberatung', 'icon' => 'chart'], // 2.765 Treffer
        ['label' => 'Energieausweis', 'query' => 'Energieausweis', 'icon' => 'text'], // 39 Treffer
        ['label' => 'Thermografie', 'query' => 'Thermografie', 'icon' => 'flame'], // 22 Treffer
        ['label' => 'Bauphysik', 'query' => 'Bauphysik', 'icon' => 'home'], // 82 Treffer
        ['label' => 'Sachverständige', 'query' => 'Sachverständiger', 'icon' => 'shield-check'], // 36 Treffer
        ['label' => 'Ingenieurbüro', 'query' => 'Ingenieur', 'icon' => 'settings'], // 95 Treffer
    ],

    'search' => [
        'branch_singular' => 'Energieberater',
        'branch_plural' => 'Energieberater',
        'placeholder' => 'Energieberatungsbüro oder Leistung',
        'quick_term' => 'Energieberatung',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Energieberater', 'label' => 'Energieberater'],
            ['query' => 'Ingenieur', 'label' => 'Ingenieur'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Werkstudent', 'label' => 'Werkstudent'],
        ],
    ],

];
