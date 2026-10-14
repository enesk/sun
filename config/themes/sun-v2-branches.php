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

    /*
     * Nicht ausgelieferte Portale stehen NICHT hier, sondern als Attribut am
     * Tenant (Tenant::NOT_DELIVERED, #16) — eine Liste an zwei Stellen laeuft
     * beim naechsten Portalwechsel auseinander. `sun:branches:status` liest
     * das Attribut.
     */

    'domains' => [
        // Umgestellt und mit Texten
        'kfzwerkstatt.io' => 'kfz',
        'sanitaerfinden.com' => 'sanitaer',
        'fahrschulefinder.de' => 'fahrschule',
        'findegutachter.de' => 'gutachter',

        // Zugeordnet, Texte vorhanden — nur noch umstellen (#15)
        'sanitaerfinder.com' => 'sanitaer',
        'klempner.firmenfreund.de' => 'sanitaer',

        // Geruest, Texte fehlen (#12 angelegt, #13 schreibt, #15 stellt um)
        'firmenfreund.de' => 'bau',
        'bodenlegerfinden.com' => 'bodenleger',
        'malerfinder.de' => 'maler',
        'geruestbauer.gmbh' => 'geruestbau',
        'metallbauer.io' => 'metallbau',
        'tierarztportal.com' => 'tierarzt',
        'fliesenleger.io' => 'fliesenleger',
        'mjet.net' => 'gartenbau',
        'apotheke.firmenfreund.de' => 'apotheke',
        'firmenfreund.net' => 'solar',
        'unfallarzt.firmenfreund.de' => 'unfallchirurgie',
        'zahnarzt.firmenfreund.de' => 'zahnarzt',
        'energieberaterportal.net' => 'energieberatung',
        'arztfinder.firmenfreund.de' => 'arzt',
        'speditionportal.com' => 'spedition',
        'schluesseldienstportal.com' => 'schluesseldienst',
    ],

    /*
     * Stichwoerter fuer lokale .test-Domains und neue Portale derselben
     * Branche. Reihenfolge zaehlt: die erste Regel gewinnt, spezifische
     * Stichwoerter gehoeren deshalb vor allgemeine (zahnarzt vor arzt,
     * unfallarzt vor arzt, solar vor energie).
     */
    'needles' => [
        'kfz' => 'kfz',
        'werkstatt' => 'kfz',
        'autowerkstatt' => 'kfz',
        'sanitaer' => 'sanitaer',
        'klempner' => 'sanitaer',
        'installateur' => 'sanitaer',
        'fahrschule' => 'fahrschule',
        'gutachter' => 'gutachter',
        'sachverstaendig' => 'gutachter',
        'bodenleger' => 'bodenleger',
        'parkett' => 'bodenleger',
        'maler' => 'maler',
        'lackierer' => 'maler',
        'geruestbau' => 'geruestbau',
        'metallbau' => 'metallbau',
        'schlosser' => 'metallbau',
        'tierarzt' => 'tierarzt',
        'fliesenleger' => 'fliesenleger',
        'garten' => 'gartenbau',
        'landschaftsbau' => 'gartenbau',
        'apotheke' => 'apotheke',
        'solar' => 'solar',
        'photovoltaik' => 'solar',
        'zahnarzt' => 'zahnarzt',
        'unfallarzt' => 'unfallchirurgie',
        'unfallchirurg' => 'unfallchirurgie',
        'energieberater' => 'energieberatung',
        'arztfinder' => 'arzt',
        'spedition' => 'spedition',
        'schluesseldienst' => 'schluesseldienst',
        'bauunternehmen' => 'bau',
        'hochbau' => 'bau',
        'tiefbau' => 'bau',
        // Allgemein, deshalb zuletzt: trifft nur, wenn keine der oberen
        // Regeln gepasst hat (htbau.test, firmenfreund.de).
        'bau' => 'bau',
        'arzt' => 'arzt',
    ],

];
