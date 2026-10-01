<?php

/*
|--------------------------------------------------------------------------
| Branchenpakete des Themes sun-v2 (#44)
|--------------------------------------------------------------------------
|
| config/themes/sun-v2.php enthaelt die Vorgabe (Elektriker, erstes Portal
| mit dem Theme). Portale anderer Branchen legen ein Paket unter
| config/branches/<slug>.php, das die Vorgabe ueberlagert —
| App\Themes\SunV2\BranchConfig setzt es beim Aufloesen des Themes.
|
| Zuordnung in dieser Reihenfolge (Muster config/tenant-schema-types.php):
|   1. Tenant-Attribut 'sun_v2_branch' (Einzelfall)
|   2. 'domains': exakte Domain des Portals
|   3. 'needles': Stichwort im Slug aus Tenant-Name + Domain — deckt die
|      lokalen .test-Domains und neue Portale derselben Branche ab
|   4. 'default' (null = keine Ueberlagerung, es bleibt bei der Vorgabe)
|
*/

return [

    'default' => null,

    'domains' => [
        'kfzwerkstatt.io' => 'kfz',
        'sanitaerfinden.com' => 'sanitaer',
        'sanitaerfinder.com' => 'sanitaer',
        'klempner.firmenfreund.de' => 'sanitaer',
        'fahrschulefinder.de' => 'fahrschule',
    ],

    'needles' => [
        'kfz' => 'kfz',
        'werkstatt' => 'kfz',
        'autowerkstatt' => 'kfz',
        'sanitaer' => 'sanitaer',
        'klempner' => 'sanitaer',
        'installateur' => 'sanitaer',
        'fahrschule' => 'fahrschule',
    ],

];
