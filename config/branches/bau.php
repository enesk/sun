<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Bauunternehmen (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'home',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Hoch- und Tiefbau', 'query' => 'Tiefbau', 'icon' => 'home'], // 1.849 Treffer
        ['label' => 'Rohbau & Maurerarbeiten', 'query' => 'Bauunternehmen', 'icon' => 'grid'], // 2.861 Treffer
        ['label' => 'Straßen- und Pflasterbau', 'query' => 'Straßenbau', 'icon' => 'map-lines'], // 537 Treffer
        ['label' => 'Erdarbeiten', 'query' => 'Erdbau', 'icon' => 'settings'], // 438 Treffer
        ['label' => 'Abbruch & Rückbau', 'query' => 'Abbruch', 'icon' => 'trash'], // 303 Treffer
        ['label' => 'Sanierung', 'query' => 'Sanierung', 'icon' => 'wrench'], // 346 Treffer
        ['label' => 'Baustoffe', 'query' => 'Baustoffe', 'icon' => 'briefcase'], // 341 Treffer
        ['label' => 'Bauservice', 'query' => 'Bauservice', 'icon' => 'clock'], // 741 Treffer
    ],

    'search' => [
        'branch_singular' => 'Bauunternehmen',
        'branch_plural' => 'Bauunternehmen',
        'placeholder' => 'Bauunternehmen oder Leistung',
        'quick_term' => 'Tiefbau',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Bauleiter', 'label' => 'Bauleiter'],
            ['query' => 'Maurer', 'label' => 'Maurer'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Baugeräteführer', 'label' => 'Baugeräteführer'],
        ],
    ],

];
