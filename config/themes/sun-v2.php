<?php

/*
|--------------------------------------------------------------------------
| Theme sun-v2 — Inhalte der Startseite
|--------------------------------------------------------------------------
|
| Branchentexte und Leistungskacheln aus der Vorlage sun-v2.html
| (Elektriker). Zahlen kommen nie von hier, sondern aus der Datenbank; die
| Platzhalter :portal, :companies, :cities werden beim Rendern ersetzt
| (App\Themes\SunV2\HomeViewComposer).
|
| Icons sind Symbol-IDs aus public/themes/sun-v2/images/icons.svg.
|
*/

return [

    'brand_icon' => 'bolt',

    // Voreinstellung fuer --brand, solange das Portal keine eigene
    // Primaerfarbe (branding.primary_color) gepflegt hat.
    'default_brand_color' => '#1d4ed8',

    'meta' => [
        'title' => 'Finde deinen Elektriker in deiner Nähe | :portal',
        'description' => 'Über :companies Elektrobetriebe in :cities Städten – mit echten Bewertungen, Öffnungszeiten und Direktkontakt. Finde den passenden Elektriker für Installation, Notdienst, Wallbox und Smart Home.',
    ],

    'hero' => [
        'headline' => 'Finde deinen Elektriker in deiner Nähe',
        'text' => 'Über :companies Elektrobetriebe mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer – von der Steckdose bis zur Wallbox.',
        'search_placeholder' => 'Elektriker oder Leistung, z. B. Wallbox',
        'search_button' => 'Elektriker finden',
        // Suchbegriffe, die unter der Suche als "Beliebt" stehen
        'popular' => ['Elektroinstallation', 'Notdienst', 'Wallbox', 'Smart Home'],
    ],

    // Kacheln "Was steht an?". 'query' ist der Suchbegriff fuer die
    // Firmensuche; die Betriebszahl je Kachel ist dieselbe Volltextsuche.
    'services' => [
        ['label' => 'Elektroinstallation', 'query' => 'Elektroinstallation', 'icon' => 'plug'],
        ['label' => 'Notdienst', 'query' => 'Notdienst', 'icon' => 'bolt'],
        ['label' => 'Wallbox & E-Auto', 'query' => 'Wallbox', 'icon' => 'car'],
        ['label' => 'Photovoltaik', 'query' => 'Photovoltaik', 'icon' => 'sun'],
        ['label' => 'Smart Home', 'query' => 'Smart Home', 'icon' => 'home'],
        ['label' => 'Sicherungskasten', 'query' => 'Sicherungskasten', 'icon' => 'fuse-box'],
        ['label' => 'Beleuchtung & LED', 'query' => 'Beleuchtung', 'icon' => 'lightbulb'],
        ['label' => 'E-Check & Prüfung', 'query' => 'E-Check', 'icon' => 'shield-check'],
    ],

    // Mindestzahl Bewertungen fuer "Top bewertet", damit kein Betrieb mit
    // einer einzigen 5-Sterne-Bewertung oben steht.
    'top_rated_min_reviews' => 5,

    'cta' => [
        'headline' => 'Du bist Elektriker? Trag deinen Betrieb kostenlos ein',
        'text' => 'Kund:innen suchen hier täglich nach Elektrikern in ihrer Stadt. Dein Eintrag ist in drei Minuten online.',
        'benefits' => [
            'Kostenloser Basiseintrag, ohne Laufzeit',
            'Bewertungen sammeln und beantworten',
            'Anfragen direkt aufs Handy',
        ],
    ],

    'cities_heading' => 'Elektriker in deiner Stadt',

    'seo' => [
        'headline' => 'Den richtigen Elektriker finden – so geht\'s',
        'paragraphs' => [
            'Ob eine Steckdose nicht mehr funktioniert, der FI-Schalter ständig fliegt oder eine Wallbox ans Netz soll: Arbeiten an der Elektroinstallation gehören in die Hände eines eingetragenen Elektrofachbetriebs. Auf :portal findest du über :companies Betriebe in ganz Deutschland – mit Adresse, Telefonnummer, Leistungen und Bewertungen von Kund:innen, die dort schon Kunde waren.',
            'Gib einfach deinen Ort oder deine Postleitzahl ein und filtere nach dem, was du brauchst. Für Notfälle zeigen wir dir Betriebe mit 24-Stunden-Notdienst, für größere Projekte wie Photovoltaik oder Smart Home Betriebe mit entsprechender Spezialisierung. Über den Anruf-Button erreichst du den Betrieb direkt – ohne Umweg über ein Kontaktformular.',
        ],
    ],

    // Suchergebnisseite /firmen (App\Themes\SunV2\SearchViewComposer)
    'search' => [
        'branch_singular' => 'Elektriker',
        'branch_plural' => 'Elektriker',
        'placeholder' => 'Elektriker oder Leistung',
        // Pille in der Filterleiste, setzt den Suchbegriff
        'quick_term' => 'Notdienst',
        'city_chips' => 12,
        'seo' => [
            'headline' => 'Elektriker finden: Worauf du bei der Auswahl achten solltest',
            'paragraphs' => [
                'Ein eingetragener Elektrofachbetrieb ist Pflicht, sobald es an den Sicherungskasten oder feste Leitungen geht. Achte auf den Eintrag in der Handwerksrolle, auf Bewertungen mit konkretem Inhalt und darauf, ob der Betrieb die Leistung wirklich anbietet, die du brauchst – ein Notdienst-Spezialist ist nicht automatisch der richtige für eine Photovoltaik-Anlage.',
                'Hol dir bei größeren Arbeiten zwei bis drei Angebote ein. Über den Anruf-Button erreichst du jeden Betrieb direkt; wer lieber schreibt, nutzt die Kontaktdaten auf dem Profil.',
            ],
        ],
    ],

    // Stadtseite /staedte/{slug} (App\Themes\SunV2\CityViewComposer).
    // Der SEO-Text gilt nur, wenn fuer die Stadt kein eigener Text in
    // city_contents.intro_text gepflegt ist.
    'city' => [
        // Sortierung ohne ?sort= (PublicCityController::show)
        'default_sort' => 'rating',
        'seo' => [
            'headline' => 'Elektriker in :city finden',
            'paragraphs' => [
                'Für einen Notdienst zählt vor allem die Entfernung: Betriebe aus der Nähe sind meist schneller da als ein günstigerer Anbieter vom anderen Ende von :city. Achte in der Liste auf die Adresse und ruf direkt an.',
                'Bei größeren Vorhaben – Wallbox, Photovoltaik, komplette Sanierung – lohnt sich der Blick auf die Leistungsangaben. Wallbox-Montage etwa muss beim Netzbetreiber angemeldet werden; Betriebe, die das regelmäßig machen, übernehmen die Anmeldung mit. Hol dir zwei bis drei Angebote ein, bevor du beauftragst.',
            ],
        ],
    ],

    // Staedteuebersicht /staedte (App\Themes\SunV2\CityIndexViewComposer).
    // :land ist das gewaehlte Bundesland, sonst "Deutschland".
    'cities_index' => [
        'seo' => [
            'headline' => 'Elektriker in :land nach Ort finden',
            'paragraphs' => [
                'Die Liste zeigt jeden Ort, in dem mindestens ein Elektrobetrieb eingetragen ist, mit der Zahl der Betriebe. Auf der Stadtseite siehst du alle Betriebe vor Ort mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
                'Wohnst du in einem kleinen Ort ohne eigenen Betrieb, lohnt sich der Blick in die nächstgrößere Stadt: Viele Elektriker fahren 20 bis 30 Kilometer zu ihren Kund:innen, im Notdienst oft auch weiter.',
            ],
        ],
    ],

    // Ratgeber-Uebersicht /ratgeber (App\Themes\SunV2\BlogIndexViewComposer).
    // :posts ist die Zahl veroeffentlichter Artikel, :companies die Betriebe.
    'blog' => [
        'headline' => 'Ratgeber rund um Elektrik',
        'text' => ':posts Artikel zu Kosten, Sicherheit und Technik – geschrieben, damit du ein Angebot prüfen und mit dem Elektriker auf Augenhöhe reden kannst.',
        'search_placeholder' => 'Thema suchen, z. B. Wallbox',
        // Kategorie-Slug => Symbol-ID; alles andere bekommt brand_icon
        'category_icons' => [
            'kosten' => 'fuse-box',
            'sicherheit' => 'shield-check',
            'e-mobilitaet' => 'car',
            'wallbox' => 'car',
            'photovoltaik' => 'sun',
            'solar' => 'sun',
            'smart-home' => 'home',
            'sanierung' => 'home',
            'beleuchtung' => 'lightbulb',
            'notfall' => 'bolt',
        ],
        'cta' => [
            'headline' => 'Lieber jemanden fragen, der es macht?',
            'text' => ':companies Elektrobetriebe sind hier eingetragen – mit Bewertungen, Öffnungszeiten und Telefonnummer. Gib deinen Ort ein und such dir zwei bis drei zum Vergleichen.',
        ],
    ],

    // Anfrage-Dialog auf dem Firmenprofil: headless an die oeffentliche
    // Funnel-Runtime-API des Leadsystems (partials/sun/lead-dialog,
    // js/modules/lead-dialog.js). Der Funnel-Token ist oeffentlich (kein
    // Schluessel), kommt aber je Umgebung aus dem Seeder des Leadsystems.
    'lead' => [
        'api_url' => 'https://leads.widimedia.com/api/public/v1',
        'funnel_token' => env('LEADS_ELEKTRIKER_FUNNEL_TOKEN'),
    ],

    // Anmeldung /login (App\Themes\SunV2\LoginViewComposer).
    // :companies ist die Zahl aktiver Betriebe, :portal der Portalname.
    'login' => [
        'intro' => 'Verwalte deinen Eintrag, deine Anfragen und Bewertungen.',
        'headline' => 'Dein Betrieb, dein Profil',
        'text' => ':companies Elektrobetriebe sind auf :portal eingetragen. Mit deinem Konto steuerst du, was Kund:innen von deinem sehen.',
        'benefits' => [
            'Anfragen direkt aufs Handy',
            'Bewertungen beantworten',
            'Profil jederzeit selbst pflegen',
            'Stellenanzeigen verwalten',
        ],
        'support_email' => 'info@widimedia.com',
    ],

    'footer' => [
        'operator' => 'Made with love in Rastatt',
    ],

];
