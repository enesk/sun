<?php

/**
 * Kategorie-Mapping: Google Places EN → Deutsches Branchenportal
 *
 * Struktur:
 * - 'categories' = Hierarchische deutsche Kategorien mit Icons
 * - 'mapping' = Zuordnung jeder EN-Quellkategorie zu einer DE-Zielkategorie
 *
 * Konsolidierung:
 * - 84 Quellkategorien → ~45 Zielkategorien (12 Parents + 33 Children)
 * - Generische Kategorien (establishment, point_of_interest, store) werden
 *   beim Import NICHT als Kategorie zugewiesen — die Firma behält nur ihre
 *   spezifischen Kategorien. Wenn eine Firma NUR generische Kategorien hat,
 *   wird sie der Parent-Kategorie 'Sonstiges' zugeordnet.
 * - source_key verlinkt die neue Kategorie mit dem EN-Slug für den Import
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Deutsche Kategorie-Hierarchie
    |--------------------------------------------------------------------------
    | parent_name => [icon, description, [children mit source_keys]]
    */
    'categories' => [
        [
            'name' => 'Handwerk & Bau',
            'icon' => 'wrench',
            'description' => 'Handwerker, Bauunternehmen und technische Dienstleister',
            'children' => [
                ['name' => 'Klempner & Sanitär', 'icon' => 'droplet', 'source_keys' => ['plumber']],
                ['name' => 'Elektriker', 'icon' => 'zap', 'source_keys' => ['electrician']],
                ['name' => 'Maler & Lackierer', 'icon' => 'paintbrush', 'source_keys' => ['painter']],
                ['name' => 'Dachdecker', 'icon' => 'home', 'source_keys' => ['roofing_contractor']],
                ['name' => 'Bauunternehmen', 'icon' => 'building', 'source_keys' => ['general_contractor']],
                ['name' => 'Schlüsseldienst', 'icon' => 'key', 'source_keys' => ['locksmith']],
                ['name' => 'Baumarkt & Eisenwaren', 'icon' => 'hammer', 'source_keys' => ['hardware_store', 'home_goods_store']],
            ],
        ],
        [
            'name' => 'Gastronomie',
            'icon' => 'utensils',
            'description' => 'Restaurants, Cafés, Bars und Lieferdienste',
            'children' => [
                ['name' => 'Restaurant', 'icon' => 'utensils', 'source_keys' => ['restaurant']],
                ['name' => 'Café', 'icon' => 'coffee', 'source_keys' => ['cafe']],
                ['name' => 'Bar & Kneipe', 'icon' => 'wine', 'source_keys' => ['bar']],
                ['name' => 'Bäckerei', 'icon' => 'croissant', 'source_keys' => ['bakery']],
                ['name' => 'Imbiss & Takeaway', 'icon' => 'pizza', 'source_keys' => ['meal_takeaway']],
                ['name' => 'Lieferservice', 'icon' => 'truck', 'source_keys' => ['meal_delivery']],
                ['name' => 'Getränkehandel', 'icon' => 'glass-water', 'source_keys' => ['liquor_store']],
            ],
        ],
        [
            'name' => 'Gesundheit & Medizin',
            'icon' => 'heart-pulse',
            'description' => 'Ärzte, Zahnärzte, Therapeuten und Apotheken',
            'children' => [
                ['name' => 'Arztpraxis', 'icon' => 'stethoscope', 'source_keys' => ['doctor', 'health']],
                ['name' => 'Zahnarzt', 'icon' => 'tooth', 'source_keys' => ['dentist']],
                ['name' => 'Physiotherapie', 'icon' => 'activity', 'source_keys' => ['physiotherapist']],
                ['name' => 'Apotheke', 'icon' => 'pill', 'source_keys' => ['pharmacy']],
                ['name' => 'Krankenhaus', 'icon' => 'hospital', 'source_keys' => ['hospital']],
                ['name' => 'Tierarzt', 'icon' => 'paw-print', 'source_keys' => ['veterinary_care']],
            ],
        ],
        [
            'name' => 'Automobile',
            'icon' => 'car',
            'description' => 'Autowerkstätten, Autohäuser und Fahrzeugservice',
            'children' => [
                ['name' => 'Autowerkstatt', 'icon' => 'wrench', 'source_keys' => ['car_repair']],
                ['name' => 'Autohaus', 'icon' => 'car', 'source_keys' => ['car_dealer']],
                ['name' => 'Autowaschanlage', 'icon' => 'droplets', 'source_keys' => ['car_wash']],
                ['name' => 'Autovermietung', 'icon' => 'key-round', 'source_keys' => ['car_rental']],
                ['name' => 'Tankstelle', 'icon' => 'fuel', 'source_keys' => ['gas_station']],
                ['name' => 'Fahrradladen', 'icon' => 'bike', 'source_keys' => ['bicycle_store']],
            ],
        ],
        [
            'name' => 'Einzelhandel',
            'icon' => 'shopping-bag',
            'description' => 'Geschäfte, Supermärkte und Fachhandel',
            'children' => [
                ['name' => 'Lebensmittel & Supermarkt', 'icon' => 'shopping-cart', 'source_keys' => ['grocery_or_supermarket', 'supermarket', 'convenience_store', 'food']],
                ['name' => 'Bekleidung & Schuhe', 'icon' => 'shirt', 'source_keys' => ['clothing_store', 'shoe_store']],
                ['name' => 'Elektronik', 'icon' => 'monitor', 'source_keys' => ['electronics_store']],
                ['name' => 'Möbel & Einrichtung', 'icon' => 'armchair', 'source_keys' => ['furniture_store']],
                ['name' => 'Buchhandlung', 'icon' => 'book-open', 'source_keys' => ['book_store']],
                ['name' => 'Blumenladen', 'icon' => 'flower', 'source_keys' => ['florist']],
                ['name' => 'Tierhandlung', 'icon' => 'paw-print', 'source_keys' => ['pet_store']],
                ['name' => 'Einkaufszentrum', 'icon' => 'store', 'source_keys' => ['shopping_mall']],
            ],
        ],
        [
            'name' => 'Beauty & Wellness',
            'icon' => 'sparkles',
            'description' => 'Friseure, Kosmetik, Spa und Wellness',
            'children' => [
                ['name' => 'Friseur', 'icon' => 'scissors', 'source_keys' => ['hair_care']],
                ['name' => 'Kosmetik & Beauty', 'icon' => 'sparkles', 'source_keys' => ['beauty_salon']],
                ['name' => 'Spa & Wellness', 'icon' => 'flower', 'source_keys' => ['spa']],
                ['name' => 'Fitnessstudio', 'icon' => 'dumbbell', 'source_keys' => ['gym']],
                ['name' => 'Wäscherei & Reinigung', 'icon' => 'shirt', 'source_keys' => ['laundry']],
            ],
        ],
        [
            'name' => 'Dienstleistungen',
            'icon' => 'briefcase',
            'description' => 'Finanz-, Rechts- und Immobiliendienstleistungen',
            'children' => [
                ['name' => 'Versicherung', 'icon' => 'shield', 'source_keys' => ['insurance_agency']],
                ['name' => 'Finanzdienstleistung', 'icon' => 'banknote', 'source_keys' => ['finance']],
                ['name' => 'Immobilienmakler', 'icon' => 'home', 'source_keys' => ['real_estate_agency']],
                ['name' => 'Umzugsunternehmen', 'icon' => 'truck', 'source_keys' => ['moving_company']],
                ['name' => 'Lagerung', 'icon' => 'warehouse', 'source_keys' => ['storage']],
                ['name' => 'Bestattung', 'icon' => 'flower', 'source_keys' => ['funeral_home']],
            ],
        ],
        [
            'name' => 'Tourismus & Freizeit',
            'icon' => 'map-pin',
            'description' => 'Hotels, Reisebüros, Sehenswürdigkeiten und Freizeitangebote',
            'children' => [
                ['name' => 'Hotel & Unterkunft', 'icon' => 'bed', 'source_keys' => ['lodging']],
                ['name' => 'Reisebüro', 'icon' => 'plane', 'source_keys' => ['travel_agency']],
                ['name' => 'Camping & Wohnmobil', 'icon' => 'tent', 'source_keys' => ['campground', 'rv_park']],
                ['name' => 'Sehenswürdigkeit', 'icon' => 'landmark', 'source_keys' => ['tourist_attraction', 'museum', 'zoo', 'amusement_park', 'art_gallery']],
                ['name' => 'Kino', 'icon' => 'clapperboard', 'source_keys' => ['movie_theater']],
            ],
        ],
        [
            'name' => 'Öffentliches & Bildung',
            'icon' => 'landmark',
            'description' => 'Behörden, Schulen, Kirchen und öffentliche Einrichtungen',
            'children' => [
                ['name' => 'Schule & Bildung', 'icon' => 'graduation-cap', 'source_keys' => ['school']],
                ['name' => 'Kirche & Religion', 'icon' => 'church', 'source_keys' => ['church', 'place_of_worship']],
                ['name' => 'Rathaus & Behörde', 'icon' => 'landmark', 'source_keys' => ['city_hall', 'local_government_office']],
                ['name' => 'Post', 'icon' => 'mail', 'source_keys' => ['post_office']],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignorierte Quell-Kategorien
    |--------------------------------------------------------------------------
    | Diese generischen Google-Places-Typen werden beim Import übersprungen.
    | Sie liefern keine sinnvolle Branchenzuordnung.
    |
    | Wenn eine Firma NUR ignorierte Kategorien hat → 'Sonstiges'
    */
    'ignored' => [
        'establishment',        // 19.596 — generischer Google-Typ, jede Firma hat diesen
        'point_of_interest',    // 19.595 — fast identisch mit establishment
        'store',                // 7.091 — zu generisch, spezifische Stores sind gemappt
        'political',            // 11 — Wahlkreise, keine Firma
        'sublocality',          // 11 — Ortsteil-Geodaten
        'sublocality_level_1',  // 11 — Ortsteil-Geodaten
        'route',                // 7 — Straßen-Geodaten
        'natural_feature',      // 1 — Natur-Geodaten
        'parking',              // 10 — Parkplätze
        'park',                 // 11 — öffentliche Parks
        'transit_station',      // 10 — ÖPNV
        'train_station',        // 6 — Bahnhöfe
        'airport',              // 3 — Flughäfen
        'fire_station',         // 2 — Feuerwehr
        'police',               // 1 — Polizei
        'cemetery',             // 2 — Friedhöfe
        'atm',                  // 1 — Geldautomaten
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback-Kategorie
    |--------------------------------------------------------------------------
    | Firmen die NUR ignorierte Kategorien haben, bekommen diese Kategorie.
    */
    'fallback' => 'Sonstiges',
];
