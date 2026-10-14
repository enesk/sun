<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Unfallchirurg (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Unfallchirurgie', 'query' => 'Unfallchirurgie', 'icon' => 'plus'], // 966 Treffer
        ['label' => 'Orthopädie', 'query' => 'Orthopädie', 'icon' => 'users'], // 2.135 Treffer
        ['label' => 'Chirurgie', 'query' => 'Chirurgie', 'icon' => 'wrench'], // 791 Treffer
        ['label' => 'Durchgangsarzt', 'query' => 'Durchgangsarzt', 'icon' => 'shield-check'], // 50 Treffer
        ['label' => 'Sportmedizin', 'query' => 'Sportmedizin', 'icon' => 'chart'], // 106 Treffer
        ['label' => 'Handchirurgie', 'query' => 'Handchirurgie', 'icon' => 'wrench'], // 99 Treffer
    ],

    'search' => [
        'branch_singular' => 'Unfallchirurg',
        'branch_plural' => 'Unfallchirurg',
        'placeholder' => 'Praxis oder Leistung',
        'quick_term' => 'Unfallchirurgie',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Medizinische', 'label' => 'Medizinische'],
            ['query' => 'Facharzt', 'label' => 'Facharzt'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Praxismanagement', 'label' => 'Praxismanagement'],
        ],
    ],

];
