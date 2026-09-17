<?php

/*
|--------------------------------------------------------------------------
| Leadsystem (Funnels je Portal, Epic #23)
|--------------------------------------------------------------------------
|
| Oeffentliche Funnel-API des Leadsystems. Der FunnelDefinitionClient (#25)
| laedt darueber den veroeffentlichten Snapshot eines Funnels. Der Token ist
| oeffentlich und haengt je Portal am Tenant (#24), er steht nicht hier.
|
*/

return [

    'api_url' => 'https://leads.widimedia.com/api/public/v1',

    'funnel' => [

        // Harte Obergrenze fuer den Abruf in Sekunden. Laeuft sie ab, gilt
        // der letzte gute Stand — der Seitenaufbau wartet nie laenger.
        'timeout' => 3,

        // So lange gilt ein geladener Snapshot als frisch (Sekunden).
        'fresh_ttl' => 3600,

        // So lange wird "nicht veroeffentlicht" (404) gemerkt, bevor erneut
        // gefragt wird. 'leads:funnel:refresh' raeumt beides sofort ab.
        'missing_ttl' => 300,

        // Nach einem Fehlschlag (Timeout, 5xx) so lange nicht erneut fragen,
        // damit nicht jeder Seitenaufruf die drei Sekunden abwartet.
        'retry_after' => 60,

        // Fragen mit diesen Schluesseln setzt das Portal selbst (z. B. das
        // Firmenprofil). Sie werden mitgeschickt, aber nie angezeigt.
        'system_field_keys' => ['firmenprofil'],
    ],

    /*
     * Exklusive Anfragen (#9): Pro/Premium-Betriebe mit freiem Kontingent
     * bekommen die Anfrage direkt, ohne Leadsystem.
     */
    'exclusive' => [

        // Fragen, die nur auf dem Marktplatz-Weg Sinn ergeben (Opt-in fuer
        // weitere Betriebe). Bei exklusiven Anfragen blendet der Dialog sie aus.
        'marketplace_only_keys' => ['weitere_betriebe'],

        // Kontaktdaten und Freitexte werden nach so vielen Monaten geloescht
        // (leads:purge-contacts, taeglich je Portal).
        'retention_months' => 12,
    ],

];
