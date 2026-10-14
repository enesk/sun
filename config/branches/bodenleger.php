<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Bodenleger (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Parkett', 'query' => 'Parkett', 'icon' => 'grid'], // 1.646 Treffer
        ['label' => 'Bodenbeläge', 'query' => 'Bodenbeläge', 'icon' => 'grid'], // 1.044 Treffer
        ['label' => 'Estrich', 'query' => 'Estrich', 'icon' => 'settings'], // 464 Treffer
        ['label' => 'Fußboden verlegen', 'query' => 'Fußboden', 'icon' => 'wrench'], // 286 Treffer
        ['label' => 'Raumausstattung', 'query' => 'Raumausstatter', 'icon' => 'home'], // 259 Treffer
        ['label' => 'Teppichboden', 'query' => 'Teppich', 'icon' => 'grid'], // 88 Treffer
        ['label' => 'Laminat', 'query' => 'Laminat', 'icon' => 'grid'], // 67 Treffer
        ['label' => 'Schleifen & Aufarbeiten', 'query' => 'Schleifen', 'icon' => 'sparkles'], // 52 Treffer
    ],

    'search' => [
        'branch_singular' => 'Bodenleger',
        'branch_plural' => 'Bodenleger',
        'placeholder' => 'Bodenlegerbetrieb oder Leistung',
        'quick_term' => 'Parkett',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Bodenleger', 'label' => 'Bodenleger'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Meister', 'label' => 'Meister'],
            ['query' => 'Estrichleger', 'label' => 'Estrichleger'],
        ],
    ],

];
