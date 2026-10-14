<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Zahnarzt (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Zahnarztpraxis', 'query' => 'Zahnarztpraxis', 'icon' => 'plus'], // 7.564 Treffer
        ['label' => 'Kieferorthopädie', 'query' => 'Kieferorthopädie', 'icon' => 'grid'], // 569 Treffer
        ['label' => 'Implantologie', 'query' => 'Implantologie', 'icon' => 'settings'], // 295 Treffer
        ['label' => 'Oralchirurgie', 'query' => 'Oralchirurgie', 'icon' => 'wrench'], // 296 Treffer
        ['label' => 'Zahnmedizin', 'query' => 'Zahnmedizin', 'icon' => 'star'], // 652 Treffer
        ['label' => 'Dentallabor', 'query' => 'Dental', 'icon' => 'briefcase'], // 337 Treffer
    ],

    'search' => [
        'branch_singular' => 'Zahnarzt',
        'branch_plural' => 'Zahnarzt',
        'placeholder' => 'Zahnarztpraxis oder Leistung',
        'quick_term' => 'Zahnarztpraxis',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Zahnmedizinische', 'label' => 'Zahnmedizinische'],
            ['query' => 'Zahnarzt', 'label' => 'Zahnarzt'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Praxismanagement', 'label' => 'Praxismanagement'],
        ],
    ],

];
