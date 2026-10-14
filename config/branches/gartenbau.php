<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Gartenbauer (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Garten- und Landschaftsbau', 'query' => 'Landschaftsbau', 'icon' => 'sun'], // 6.572 Treffer
        ['label' => 'Gartengestaltung', 'query' => 'Gartengestaltung', 'icon' => 'sparkles'], // 1.432 Treffer
        ['label' => 'Gartenpflege', 'query' => 'Gartenpflege', 'icon' => 'droplet'], // 874 Treffer
        ['label' => 'Baumpflege', 'query' => 'Baumpflege', 'icon' => 'wind'], // 607 Treffer
        ['label' => 'Pflasterarbeiten', 'query' => 'Pflasterarbeiten', 'icon' => 'grid'], // 111 Treffer
        ['label' => 'Baumfällung', 'query' => 'Baumfällung', 'icon' => 'wrench'], // 102 Treffer
        ['label' => 'Zaunbau', 'query' => 'Zaunbau', 'icon' => 'grid'], // 59 Treffer
        ['label' => 'Winterdienst', 'query' => 'Winterdienst', 'icon' => 'snowflake'], // 62 Treffer
    ],

    'search' => [
        'branch_singular' => 'Gartenbauer',
        'branch_plural' => 'Gartenbauer',
        'placeholder' => 'Gartenbaubetrieb oder Leistung',
        'quick_term' => 'Landschaftsbau',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Landschaftsgärtner', 'label' => 'Landschaftsgärtner'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Baumpfleger', 'label' => 'Baumpfleger'],
        ],
    ],

];
