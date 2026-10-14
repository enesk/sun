<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Arzt (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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
        ['label' => 'Allgemeinmedizin', 'query' => 'Allgemeinmedizin', 'icon' => 'plus'], // 983 Treffer
        ['label' => 'Hausarzt', 'query' => 'Hausarzt', 'icon' => 'home'], // 88 Treffer
        ['label' => 'Facharzt', 'query' => 'Facharzt', 'icon' => 'star'], // 884 Treffer
        ['label' => 'Gemeinschaftspraxis', 'query' => 'Gemeinschaftspraxis', 'icon' => 'users'], // 370 Treffer
        ['label' => 'Internist', 'query' => 'Internist', 'icon' => 'chart'], // 44 Treffer
        ['label' => 'Kinderarzt', 'query' => 'Kinderarzt', 'icon' => 'users'], // 15 Treffer
    ],

    'search' => [
        'branch_singular' => 'Arzt',
        'branch_plural' => 'Arzt',
        'placeholder' => 'Arztpraxis oder Leistung',
        'quick_term' => 'Allgemeinmedizin',
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
