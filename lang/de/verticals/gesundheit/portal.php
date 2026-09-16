<?php

/*
|--------------------------------------------------------------------------
| Portaltexte der Vertikale 'gesundheit' (#14)
|--------------------------------------------------------------------------
|
| Arzt, Zahnarzt, Unfallarzt, Apotheke, Tierarzt. Wird vom
| TenantOverrideLoader ueber lang/de/portal.php gelegt, Tenant-Overrides aus
| tenant_texts gehen weiterhin vor. NUR abweichende Keys eintragen — alles
| andere kommt aus der Basisdatei.
|
*/

return [

    'profile' => [
        'request_cta' => 'Termin anfragen',

        'services' => [
            'cta_text' => 'Brauchst du etwas davon? Beschreib kurz dein Anliegen, :firma meldet sich mit einem Terminvorschlag.',
        ],

        'sidebar' => [
            'lead_text' => 'Beschreib dein Anliegen, :firma meldet sich mit einem Terminvorschlag.',
        ],
    ],

];
