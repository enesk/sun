<?php

/*
|--------------------------------------------------------------------------
| Schema.org-Typ der Firmenprofile je Portal (#8)
|--------------------------------------------------------------------------
|
| StructuredDataService::businessType() entscheidet in dieser Reihenfolge:
|
| 1. Tenant-Attribut 'schema_business_type' (Einzelfall-Override, falls gesetzt)
| 2. 'domains': exakte Domain des Portals (Produktionsstand 14.09.2026)
| 3. 'needles': Stichwort im Slug aus Tenant-Name + Domain, erste Regel gewinnt
|    (deckt lokale .test-Domains und neue Portale ab; spezifische vor
|    allgemeinen Stichwoertern, z. B. zahnarzt vor arzt)
| 4. 'default'
|
| Nur Typen, die es auf schema.org als Untertyp von LocalBusiness gibt. Wo
| kein passender Untertyp existiert (Bestatter, Spedition, Gutachter), bleibt
| es beim allgemeinen Typ statt eines falschen spezifischen.
|
*/

return [

    'default' => 'LocalBusiness',

    'domains' => [
        'elektrikerportal.com' => 'Electrician',
        'sanitaerfinden.com' => 'Plumber',
        'sanitaerfinder.com' => 'Plumber',
        'klempner.firmenfreund.de' => 'Plumber',
        'malerfinder.de' => 'HousePainter',
        'kfzwerkstatt.io' => 'AutoRepair',
        'firmenfreund.de' => 'GeneralContractor',
        'geruestbauer.gmbh' => 'GeneralContractor',
        'bodenlegerfinden.com' => 'HomeAndConstructionBusiness',
        'fliesenleger.io' => 'HomeAndConstructionBusiness',
        'metallbauer.io' => 'HomeAndConstructionBusiness',
        'mjet.net' => 'HomeAndConstructionBusiness',
        'firmenfreund.net' => 'HomeAndConstructionBusiness',
        'fahrschulefinder.de' => 'DrivingSchool',
        'tierarztportal.com' => 'VeterinaryCare',
        'apotheke.firmenfreund.de' => 'Pharmacy',
        'zahnarzt.firmenfreund.de' => 'Dentist',
        'unfallarzt.firmenfreund.de' => 'Physician',
        'arztfinder.firmenfreund.de' => 'Physician',
        'energieberaterportal.net' => 'ProfessionalService',
        'findegutachter.de' => 'LocalBusiness',
        'speditionportal.com' => 'LocalBusiness',
        'schluesseldienstportal.com' => 'Locksmith',
    ],

    'needles' => [
        'elektri' => 'Electrician',
        'sanitaer' => 'Plumber',
        'klempner' => 'Plumber',
        'installateur' => 'Plumber',
        'heizung' => 'HVACBusiness',
        'dachdeck' => 'RoofingContractor',
        'maler' => 'HousePainter',
        'lackier' => 'HousePainter',
        'kfz' => 'AutoRepair',
        'autowerkstatt' => 'AutoRepair',
        'umzug' => 'MovingCompany',
        'fahrschule' => 'DrivingSchool',
        'tierarzt' => 'VeterinaryCare',
        'tierklinik' => 'VeterinaryCare',
        'zahnarzt' => 'Dentist',
        'apotheke' => 'Pharmacy',
        'unfall' => 'Physician',
        'arzt' => 'Physician',
        'schluessel' => 'Locksmith',
        'immo' => 'RealEstateAgent',
        'hochbau' => 'GeneralContractor',
        'tiefbau' => 'GeneralContractor',
        'htbau' => 'GeneralContractor',
        'bauunternehmen' => 'GeneralContractor',
        'geruest' => 'GeneralContractor',
        'bodenleger' => 'HomeAndConstructionBusiness',
        'fliesen' => 'HomeAndConstructionBusiness',
        'metallbau' => 'HomeAndConstructionBusiness',
        'garten' => 'HomeAndConstructionBusiness',
        'solar' => 'HomeAndConstructionBusiness',
        'photovoltaik' => 'HomeAndConstructionBusiness',
        'handwerker' => 'HomeAndConstructionBusiness',
        'energieberat' => 'ProfessionalService',
    ],

    // Profil-JSON-LD je Company im Cache (Key <tenant>.company.{id}.jsonld).
    // Die TTL faengt Aenderungen ohne Model-Event ab (Oeffnungszeiten per
    // upsert/delete, Medien).
    'cache_ttl' => 21600,

];
