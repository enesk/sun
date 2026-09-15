<?php

/*
|--------------------------------------------------------------------------
| Feste Texte in Portal-Views (#16)
|--------------------------------------------------------------------------
|
| Grundlage fuer 'php artisan portal:find-hardcoded-texts'. Das Command meldet
| sichtbare Texte, die nicht ueber __() laufen. Bewusste Ausnahmen gehoeren
| in 'allow' (exakter Text nach trim) oder 'allow_patterns' (Regex).
|
*/

return [

    /*
    | Verzeichnisse relativ zu base_path(). Nicht vorhandene werden still
    | uebersprungen. Das umgestellte Theme sun-v2 liegt unter
    | resources/views/themes/sun-v2/views und ist deshalb mit aufgenommen.
    */
    'paths' => [
        'resources/views/portal',
        'resources/views/components/portal',
        'resources/views/livewire/portal',
        'resources/views/mail/portal',
        'resources/views/themes/sun-v2/views',
    ],

    /*
    | Einzelne Dateien (relativ zu base_path()), die nicht geprueft werden.
    |
    | Seit #20 leer: alle Portal-Views laufen ueber __(). Die Liste ist kein
    | Freibrief, neue Views kommen hier nicht hinzu.
    */
    'ignore_files' => [],

    /*
    | Attribute, deren fester Wert als Treffer zaehlt.
    */
    'attributes' => ['placeholder', 'title', 'alt', 'aria-label'],

    /*
    | Exakte Texte (nach trim, Gross-/Kleinschreibung beachtet), die kein
    | Treffer sind: Kuerzel, Markennamen, Einheiten.
    */
    'allow' => [
        'PLZ',
        'Google',
        'WhatsApp',
        'Facebook',
        'Instagram',
        'LinkedIn',
    ],

    /*
    | Regulaere Ausdruecke fuer Ausnahmen, gegen den getrimmten Text geprueft.
    */
    'allow_patterns' => [
        '/^[A-Z]{1,4}$/',        // reine Kuerzel wie 'PLZ', 'FAQ'
    ],

];
