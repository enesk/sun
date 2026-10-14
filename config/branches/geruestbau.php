<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Gerüstbauer (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'grid',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Gerüstbau', 'query' => 'Gerüstbau', 'icon' => 'grid'], // 3.308 Treffer
        ['label' => 'Gerüste mieten', 'query' => 'Gerüstverleih', 'icon' => 'briefcase'], // 110 Treffer
        ['label' => 'Baugerüst', 'query' => 'Gerüste', 'icon' => 'home'], // 131 Treffer
    ],

    'search' => [
        'branch_singular' => 'Gerüstbauer',
        'branch_plural' => 'Gerüstbauer',
        'placeholder' => 'Gerüstbaubetrieb oder Leistung',
        'quick_term' => 'Gerüstbau',
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
