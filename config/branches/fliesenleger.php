<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Fliesenleger (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Fliesenarbeiten', 'query' => 'Fliesen', 'icon' => 'grid'], // 5.037 Treffer
        ['label' => 'Naturstein', 'query' => 'Naturstein', 'icon' => 'sparkles'], // 290 Treffer
        ['label' => 'Mosaik', 'query' => 'Mosaik', 'icon' => 'grid'], // 100 Treffer
        ['label' => 'Marmor', 'query' => 'Marmor', 'icon' => 'sparkles'], // 57 Treffer
        ['label' => 'Badsanierung', 'query' => 'Badsanierung', 'icon' => 'shower'], // 68 Treffer
        ['label' => 'Estrich', 'query' => 'Estrich', 'icon' => 'settings'], // 56 Treffer
    ],

    'search' => [
        'branch_singular' => 'Fliesenleger',
        'branch_plural' => 'Fliesenleger',
        'placeholder' => 'Fliesenlegerbetrieb oder Leistung',
        'quick_term' => 'Fliesen',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Fliesenleger', 'label' => 'Fliesenleger'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Bauhelfer', 'label' => 'Bauhelfer'],
        ],
    ],

];
