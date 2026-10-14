<?php

/*
|--------------------------------------------------------------------------
| Branchenpaket Schlüsseldienst (Theme sun-v2) — GERUEST, Texte fehlen (#12)
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

    'brand_icon' => 'lock',

    // Ratgeber: keines dieser Portale hat bisher Artikel. Sobald welche da
    // sind, hier auf true.
    'nav' => [
        'guide' => false,
    ],

    'services' => [
        ['label' => 'Schlüsseldienst', 'query' => 'Schlüsseldienst', 'icon' => 'lock'], // 570 Treffer
        ['label' => 'Notdienst', 'query' => 'Schlüsselnotdienst', 'icon' => 'bolt'], // 36 Treffer
        ['label' => 'Türöffnung', 'query' => 'Türöffnung', 'icon' => 'wrench'], // 8 Treffer
        ['label' => 'Sicherheitstechnik', 'query' => 'Sicherheitstechnik', 'icon' => 'shield-check'], // 50 Treffer
        ['label' => 'Schließanlagen', 'query' => 'Schließanlagen', 'icon' => 'settings'], // 6 Treffer
    ],

    'search' => [
        'branch_singular' => 'Schlüsseldienst',
        'branch_plural' => 'Schlüsseldienst',
        'placeholder' => 'Schlüsseldienst oder Leistung',
        'quick_term' => 'Schlüsseldienst',
    ],

    'jobs' => [
        'quick_filters' => [
            ['query' => 'Monteur', 'label' => 'Monteur'],
            ['query' => 'Ausbildung', 'label' => 'Ausbildung'],
            ['query' => 'Techniker', 'label' => 'Techniker'],
            ['query' => 'Aushilfe', 'label' => 'Aushilfe'],
        ],
    ],

];
