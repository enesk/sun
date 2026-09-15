<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Branchenbegriffe je Portaldomain (#15)
|--------------------------------------------------------------------------
|
| Datengrundlage fuer `php artisan tenants:seed-terms`. Schluessel ist die
| Domain aus tenants.domain (kleingeschrieben, ohne `www.`), Wert sind alle
| Keys aus App\Support\Tenancy\TenantTerms. Das Command validiert jeden
| Eintrag gegen TenantTerms::rules(), bevor es etwas schreibt.
|
| Stand: Vorschlag zur Abnahme durch Enes (Produktion, 23 Portale). Nicht aus
| der Domain ableitbar und deshalb besonders zu pruefen: firmenfreund.de
| (Hoch-/Tiefbau), firmenfreund.net (Solar/PV), mjet.net (Gartenbau),
| speditionportal.com, fahrschulefinder.de.
|
| Die Liste ist bewusst Daten und kein Code: ein Portal kommt hier dazu, ohne
| dass das Command angefasst wird.
|
*/

return [

    'firmenfreund.de' => [
        'branche' => 'Bauunternehmen',
        'branche_plural' => 'Bauunternehmen',
        'branche_akk' => 'ein Bauunternehmen',
        'betrieb' => 'Hoch- und Tiefbaubetrieb',
        'betrieb_plural' => 'Hoch- und Tiefbaubetriebe',
        'portal' => 'FirmenFreund',
    ],

    'bodenlegerfinden.com' => [
        'branche' => 'Bodenleger',
        'branche_plural' => 'Bodenleger',
        'branche_akk' => 'einen Bodenleger',
        'betrieb' => 'Bodenlegerbetrieb',
        'betrieb_plural' => 'Bodenlegerbetriebe',
        'portal' => 'BodenlegerFinden',
    ],

    'sanitaerfinden.com' => [
        'branche' => 'Sanitärinstallateur',
        'branche_plural' => 'Sanitärinstallateure',
        'branche_akk' => 'einen Sanitärinstallateur',
        'betrieb' => 'Sanitärbetrieb',
        'betrieb_plural' => 'Sanitärbetriebe',
        'portal' => 'SanitärFinden',
    ],

    'fahrschulefinder.de' => [
        'branche' => 'Fahrschule',
        'branche_plural' => 'Fahrschulen',
        'branche_akk' => 'eine Fahrschule',
        'betrieb' => 'Fahrschule',
        'betrieb_plural' => 'Fahrschulen',
        'portal' => 'FahrschuleFinder',
    ],

    'elektrikerportal.com' => [
        'branche' => 'Elektriker',
        'branche_plural' => 'Elektriker',
        'branche_akk' => 'einen Elektriker',
        'betrieb' => 'Elektrobetrieb',
        'betrieb_plural' => 'Elektrobetriebe',
        'portal' => 'ElektrikerPortal',
    ],

    'malerfinder.de' => [
        'branche' => 'Maler',
        'branche_plural' => 'Maler',
        'branche_akk' => 'einen Maler',
        'betrieb' => 'Malerbetrieb',
        'betrieb_plural' => 'Malerbetriebe',
        'portal' => 'Malerfinder.de',
    ],

    'geruestbauer.gmbh' => [
        'branche' => 'Gerüstbauer',
        'branche_plural' => 'Gerüstbauer',
        'branche_akk' => 'einen Gerüstbauer',
        'betrieb' => 'Gerüstbaubetrieb',
        'betrieb_plural' => 'Gerüstbaubetriebe',
        'portal' => 'Gerüstbauer.GmbH',
    ],

    'metallbauer.io' => [
        'branche' => 'Metallbauer',
        'branche_plural' => 'Metallbauer',
        'branche_akk' => 'einen Metallbauer',
        'betrieb' => 'Metallbaubetrieb',
        'betrieb_plural' => 'Metallbaubetriebe',
        'portal' => 'Metallbauer.io',
    ],

    'tierarztportal.com' => [
        'branche' => 'Tierarzt',
        'branche_plural' => 'Tierärzte',
        'branche_akk' => 'einen Tierarzt',
        'betrieb' => 'Tierarztpraxis',
        'betrieb_plural' => 'Tierarztpraxen',
        'portal' => 'Tierarztportal.com',
    ],

    'kfzwerkstatt.io' => [
        'branche' => 'Kfz-Werkstatt',
        'branche_plural' => 'Kfz-Werkstätten',
        'branche_akk' => 'eine Kfz-Werkstatt',
        'betrieb' => 'Werkstatt',
        'betrieb_plural' => 'Werkstätten',
        'portal' => 'KfzWerkstatt.io',
    ],

    'findegutachter.de' => [
        'branche' => 'Gutachter',
        'branche_plural' => 'Gutachter',
        'branche_akk' => 'einen Gutachter',
        'betrieb' => 'Gutachterbüro',
        'betrieb_plural' => 'Gutachterbüros',
        'portal' => 'FindeGutachter',
    ],

    'fliesenleger.io' => [
        'branche' => 'Fliesenleger',
        'branche_plural' => 'Fliesenleger',
        'branche_akk' => 'einen Fliesenleger',
        'betrieb' => 'Fliesenlegerbetrieb',
        'betrieb_plural' => 'Fliesenlegerbetriebe',
        'portal' => 'Fliesenleger.io',
    ],

    'mjet.net' => [
        'branche' => 'Gartenbauer',
        'branche_plural' => 'Gartenbauer',
        'branche_akk' => 'einen Gartenbauer',
        'betrieb' => 'Garten- und Landschaftsbaubetrieb',
        'betrieb_plural' => 'Garten- und Landschaftsbaubetriebe',
        'portal' => 'GartenbauerFinden',
    ],

    'sanitaerfinder.com' => [
        'branche' => 'Sanitärinstallateur',
        'branche_plural' => 'Sanitärinstallateure',
        'branche_akk' => 'einen Sanitärinstallateur',
        'betrieb' => 'Sanitärbetrieb',
        'betrieb_plural' => 'Sanitärbetriebe',
        'portal' => 'SanitärFinder',
    ],

    'apotheke.firmenfreund.de' => [
        'branche' => 'Apotheke',
        'branche_plural' => 'Apotheken',
        'branche_akk' => 'eine Apotheke',
        'betrieb' => 'Apotheke',
        'betrieb_plural' => 'Apotheken',
        'portal' => 'ApothekeFinden',
    ],

    'firmenfreund.net' => [
        'branche' => 'Solarteur',
        'branche_plural' => 'Solarteure',
        'branche_akk' => 'einen Solarteur',
        'betrieb' => 'Photovoltaik-Fachbetrieb',
        'betrieb_plural' => 'Photovoltaik-Fachbetriebe',
        'portal' => 'SolarteurFinden',
    ],

    'unfallarzt.firmenfreund.de' => [
        'branche' => 'Unfallchirurg',
        'branche_plural' => 'Unfallchirurgen',
        'branche_akk' => 'einen Unfallchirurgen',
        'betrieb' => 'Praxis',
        'betrieb_plural' => 'Praxen',
        'portal' => 'UnfallarztFinden',
    ],

    'zahnarzt.firmenfreund.de' => [
        'branche' => 'Zahnarzt',
        'branche_plural' => 'Zahnärzte',
        'branche_akk' => 'einen Zahnarzt',
        'betrieb' => 'Zahnarztpraxis',
        'betrieb_plural' => 'Zahnarztpraxen',
        'portal' => 'ZahnarztFinden',
    ],

    'klempner.firmenfreund.de' => [
        'branche' => 'Klempner',
        'branche_plural' => 'Klempner',
        'branche_akk' => 'einen Klempner',
        'betrieb' => 'Klempnerbetrieb',
        'betrieb_plural' => 'Klempnerbetriebe',
        'portal' => 'KlempnerFinden',
    ],

    'energieberaterportal.net' => [
        'branche' => 'Energieberater',
        'branche_plural' => 'Energieberater',
        'branche_akk' => 'einen Energieberater',
        'betrieb' => 'Energieberatungsbüro',
        'betrieb_plural' => 'Energieberatungsbüros',
        'portal' => 'Energieberaterportal',
    ],

    'arztfinder.firmenfreund.de' => [
        'branche' => 'Arzt',
        'branche_plural' => 'Ärzte',
        'branche_akk' => 'einen Arzt',
        'betrieb' => 'Arztpraxis',
        'betrieb_plural' => 'Arztpraxen',
        'portal' => 'ArztFinder',
    ],

    'speditionportal.com' => [
        'branche' => 'Spedition',
        'branche_plural' => 'Speditionen',
        'branche_akk' => 'eine Spedition',
        'betrieb' => 'Speditionsunternehmen',
        'betrieb_plural' => 'Speditionsunternehmen',
        'portal' => 'SpeditionPortal',
    ],

    'schluesseldienstportal.com' => [
        'branche' => 'Schlüsseldienst',
        'branche_plural' => 'Schlüsseldienste',
        'branche_akk' => 'einen Schlüsseldienst',
        'betrieb' => 'Schlüsseldienst',
        'betrieb_plural' => 'Schlüsseldienste',
        'portal' => 'Schlüsseldienstportal',
    ],

];
