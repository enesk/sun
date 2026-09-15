<?php

/*
|--------------------------------------------------------------------------
| Portal-Texte (Theme sun-v2), branchenneutral
|--------------------------------------------------------------------------
|
| Textinventur aus den Vorlagen des Themes sun-v2 (#7, Vorhaben #1): Start,
| Suchergebnis, Stadtseite, Firmenprofil, Anfrage-Dialog, Firma eintragen,
| Header, Footer, leere Zustaende und Fehlermeldungen. SEO-Fliesstexte,
| Stadt-Intros, Leistungskacheln, Beliebt-Begriffe und die Antwortoptionen des
| Anfrage-Funnels sind Inhalt je Branche und stehen bewusst NICHT hier.
|
| Branchenbegriffe (tenant('terms'), #2) — werden vom TenantTranslator (#5)
| automatisch eingesetzt, Views uebergeben sie nicht selbst:
|
|   :branche         Elektriker        | Tierarzt         | Apotheke
|   :branche_plural  Elektriker        | Tierärzte        | Apotheken
|   :branche_akk     einen Elektriker  | einen Tierarzt   | eine Apotheke
|   :betrieb         Elektrobetrieb    | Tierarztpraxis   | Apotheke
|   :betrieb_plural  Elektrobetriebe   | Tierarztpraxen   | Apotheken
|   :portal          Elektrikerportal  | Tierarztportal   | Portalname
|
| Kontextbezogene Platzhalter, von der View uebergeben:
|
|   :firma     Name des Eintrags          :stadt   Stadtname
|   :anzahl    formatierte Zahl (1.234)   :begriff Suchbegriff
|   :ort       Ort/PLZ aus der Suche      :km      Umkreis in km
|   :wertung   Sternwert mit Komma (4,7)  :zeit    Uhrzeit (17:00)
|   :tag       Wochentag kurz (Mo)        :schritt Schrittnummer
|   :minuten   Wartezeit in Minuten       :jahr    Jahreszahl
|   :name      Name der angemeldeten Person
|   :telefon   Telefonnummer zur Anzeige  :sortierung Label der Sortierung
|   :ueberschrift  fertige H1 der Seite   :agb/:datenschutz  Links (HTML)
|   :land      Name des Bundeslands       :leistung Name der Leistung/Kategorie
|   :kategorie Ratgeber-Kategorie         :schlagwort Ratgeber-Schlagwort
|   :datum     formatiertes Datum         :dauer   Bearbeitungsdauer (HTML)
|   :grund     Ablehnungsgrund            :datei   Dateiname
|   :email     E-Mail-Link (HTML)
|
| Regeln fuer jeden Text hier:
|  - Du-Ansprache, sentence case, Buttons benennen die Aktion.
|  - Vor :branche, :betrieb (Singular) steht nie ein Artikel, Possessiv oder
|    Adjektiv — das Genus wechselt je Tenant (der Elektrobetrieb, die Apotheke,
|    die Tierarztpraxis). Wo ein Artikel noetig waere, Plural, :branche_akk
|    oder :firma nehmen.
|  - Zaehltexte mit :anzahl sind Pluralformen fuer trans_choice(); die Zahl
|    fuer die Auswahl ist der Rohwert, :anzahl der formatierte Wert.
|  - Nach Praepositionen mit Dativ (nach, mit, von, zu) kein Plural-Platzhalter:
|    "nach Elektriker" passt, "nach Tierärzte" nicht.
|  - Das Zeichen | trennt Zaehlformen. Titel tragen deshalb keinen
|    "| :portal"-Zusatz, den haengt das Layout an.
|  - Leere Zustaende laden zu einem naechsten Schritt ein.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | layout.* — Header, Footer und seitenuebergreifende Bausteine
    |----------------------------------------------------------------------
    */
    'layout' => [
        'header' => [
            'home_label' => ':portal – Startseite',
            'nav_label' => 'Hauptnavigation',
            'nav_mobile_label' => 'Mobile Navigation',
            'menu_open' => 'Menü öffnen',
            'cities' => 'Städte',
            'guide' => 'Ratgeber',
            'jobs' => 'Stellenanzeigen',
            'login' => 'Anmelden',
            'logout' => 'Abmelden',
            'cta_signup' => ':betrieb eintragen',
            'cta_owner' => 'Mein Profil verwalten',
        ],

        'footer' => [
            'portal' => 'Portal',
            'directory' => 'Alle :betrieb_plural',
            'services' => 'Leistungen',
            'cities' => 'Städte',
            'jobs' => 'Stellenanzeigen',
            'for_business' => 'Für :betrieb_plural',
            'signup' => ':betrieb eintragen',
            'premium' => 'Premium',
            'login' => 'Anmelden',
            'guide' => 'Ratgeber',
            'guide_all' => 'Alle Artikel',
            'legal' => 'Rechtliches',
            'imprint' => 'Impressum',
            'privacy' => 'Datenschutz',
            'privacy_settings' => 'Datenschutz-Einstellungen',
            'editorial' => 'Redaktionsprinzipien',
            'terms' => 'AGB',
            'support' => 'Support',
            'copyright' => '© :jahr :portal',
        ],

        'breadcrumb' => [
            'label' => 'Brotkrumen',
            'home' => 'Start',
            'search' => 'Suche „:begriff“',
        ],

        'search_form' => [
            'what_label' => 'Was suchst du?',
            'where_label' => 'Wo?',
            'where_placeholder' => 'Ort oder PLZ',
            'radius_label' => 'Umkreis',
            'radius_option' => ':km km',
            'placeholder' => ':branche_plural oder Leistung',
            'submit' => 'Finden',
            'popular' => 'Beliebt:',
        ],

        'filters' => [
            'label' => 'Filter',
            'sort' => 'Sortierung: :sortierung',
            'sort_rating' => 'Bewertung',
            'sort_newest' => 'Neueste',
            'sort_name' => 'Name',
            'city' => 'Stadt',
            'all_cities' => 'Alle Städte',
            'min_rating' => 'Ab 4 Sternen',
            'open_now' => 'Jetzt geöffnet',
            'rated' => 'Mit Bewertungen',
            'reset' => 'Filter zurücksetzen',
        ],

        'card' => [
            'premium' => 'Premium',
            'verified' => 'Geprüfter Eintrag',
            'photo_alt' => 'Foto von :firma',
            'call' => 'Anrufen',
            'call_label' => 'Anrufen: :telefon',
            'profile' => 'Profil ansehen',
            'profile_short' => 'Profil',
            'distance' => ':km km entfernt',
            'rating_sr' => '{1} :wertung von 5 Sternen bei einer Bewertung|[2,*] :wertung von 5 Sternen bei :anzahl Bewertungen',
            'reviews_count' => '{1} :anzahl Bewertung|[0,*] :anzahl Bewertungen',
            'no_reviews' => 'Noch keine Bewertungen',
        ],

        'opening' => [
            'open_until' => 'Jetzt geöffnet · bis :zeit',
            'opens_today' => 'Geschlossen · öffnet heute :zeit',
            'opens_on' => 'Geschlossen · öffnet :tag :zeit',
            'closed' => 'Geschlossen',
            'today' => 'heute',
        ],

        'pagination' => [
            'label' => 'Seiten',
            'previous' => 'Zurück',
            'next' => 'Weiter',
        ],

        'skip_link' => 'Zum Inhalt springen',
        'ad_label' => 'Anzeige',
        'reading_time' => ':minuten Min. Lesezeit',
        'close' => 'Schließen',
        'optional' => '(optional)',

        'cookies' => [
            'region_label' => 'Cookie-Hinweis',
            'banner' => 'Wir verwenden Cookies, damit das Portal zuverlässig funktioniert, und – nur mit deiner Zustimmung – für anonyme Statistik.',
            'privacy_link' => 'Datenschutzerklärung',
            'settings' => 'Einstellungen',
            'essential' => 'Nur notwendige',
            'accept_all' => 'Alle akzeptieren',
            'modal_title' => 'Datenschutz-Einstellungen',
            'modal_text' => 'Notwendige Cookies brauchen wir für den Betrieb der Website. Statistik- und Marketing-Cookies helfen uns, das Portal zu verbessern. Du kannst deine Auswahl jederzeit ändern.',
            'learn_more' => 'Mehr erfahren',
            'always_active' => 'Immer aktiv',
            'show_details' => 'Details anzeigen',
            'save' => 'Auswahl speichern',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | home.* — Startseite
    |----------------------------------------------------------------------
    */
    'home' => [
        'meta_title' => ':branche_plural in deiner Nähe finden',
        'meta_description' => ':anzahl :betrieb_plural mit echten Bewertungen, Öffnungszeiten und Direktkontakt. Finde passende :branche_plural in deiner Stadt.',

        'hero' => [
            'headline' => 'Finde :branche_plural in deiner Nähe',
            'text' => ':anzahl :betrieb_plural mit echten Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
            'search_button' => ':branche_plural finden',
        ],

        'stats' => [
            'businesses' => ':betrieb_plural',
            'reviews' => 'Bewertungen',
            'cities' => 'Städte',
            'avg_rating' => 'Ø Bewertung',
        ],

        'services' => [
            'heading' => 'Was steht an?',
            'all' => 'Alle Leistungen',
            'count' => '{1} :anzahl :betrieb|[0,*] :anzahl :betrieb_plural',
        ],

        'top_rated' => [
            'heading' => 'Top bewertet',
            'all' => 'Alle anzeigen',
        ],

        'guide' => [
            'heading' => 'Ratgeber',
            'all' => 'Alle Artikel',
        ],

        'cta' => [
            'headline' => 'Für :betrieb_plural: kostenlos eintragen und gefunden werden',
            'text' => 'Hier suchen täglich Menschen aus deiner Stadt. Dein Eintrag ist in drei Minuten online.',
            'benefit_free' => 'Kostenloser Basiseintrag, ohne Laufzeit',
            'benefit_reviews' => 'Bewertungen sammeln und beantworten',
            'benefit_requests' => 'Anfragen direkt aufs Handy',
            'button' => ':betrieb eintragen',
            'premium' => 'Premium ansehen',
            'social_proof' => '{1} Schon :anzahl :betrieb dabei|[2,*] Schon :anzahl :betrieb_plural dabei',
        ],

        'cities' => [
            'heading' => ':branche_plural in deiner Stadt',
            'all' => 'Alle Städte',
            'more' => 'Mehr Städte anzeigen',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | search.* — Suchergebnis /firmen
    |----------------------------------------------------------------------
    */
    'search' => [
        'meta_description' => ':ueberschrift – mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',

        // H1; Auswahl ueber die Trefferzahl, 0 = leeres Ergebnis
        'heading' => [
            'all' => '{0} Keine :branche_plural gefunden|{1} :anzahl :branche|[2,*] :anzahl :branche_plural',
            'term' => '{0} Keine :branche_plural für „:begriff“ gefunden|{1} :anzahl :branche für „:begriff“|[2,*] :anzahl :branche_plural für „:begriff“',
            'place' => '{0} Keine :branche_plural in :ort gefunden|{1} :anzahl :branche in :ort|[2,*] :anzahl :branche_plural in :ort',
            'term_place' => '{0} Keine :branche_plural für „:begriff“ in :ort gefunden|{1} :anzahl :branche für „:begriff“ in :ort|[2,*] :anzahl :branche_plural für „:begriff“ in :ort',
        ],

        'crumb' => ':branche_plural',
        'subtitle_sort' => 'Sortiert nach :sortierung',
        'subtitle_radius' => 'Umkreis :km km um :ort',
        'map_label' => '{1} Karte mit einem Ergebnis|[2,*] Karte mit :anzahl Ergebnissen',
        'map_open' => 'Karte öffnen',
        'cities_heading' => 'Ergebnisse nach Stadt eingrenzen',
        'cities_more' => '{1} Eine weitere Stadt|[2,*] :anzahl weitere Städte',
    ],

    /*
    |----------------------------------------------------------------------
    | city.* — Stadtseite /staedte/{slug}
    |----------------------------------------------------------------------
    */
    'city' => [
        'heading' => '{0} Keine :branche_plural in :stadt gefunden|{1} :anzahl :branche in :stadt|[2,*] :anzahl :branche_plural in :stadt',
        'services_heading' => 'Leistungen in :stadt',
        'nearby_heading' => ':branche_plural in der Nähe von :stadt',
        'guide_heading' => 'Ratgeber für :stadt',
        'guide_all' => 'Alle Artikel',

        // Einleitung aus echten Zahlen (CityViewComposer::intro()); die
        // Leistungs-Teilsaetze stehen je Branche in config('themes.sun-v2.services.*.intro')
        'intro' => [
            'companies' => '{1} In :stadt ist :anzahl :betrieb eingetragen|[2,*] In :stadt sind :anzahl :betrieb_plural eingetragen',
            'rating' => '{1} Der Durchschnitt liegt bei :schnitt Sternen aus einer Bewertung.|[2,*] Der Durchschnitt liegt bei :schnitt Sternen aus :bewertungen Bewertungen.',
            'cta' => 'Such nach Leistung – oder ruf direkt an.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | profile.* — Firmenprofil
    |----------------------------------------------------------------------
    */
    'profile' => [
        'meta_title' => ':firma in :stadt',
        'subtitle' => ':branche in :stadt',

        // Einziger Text fuer alle Buttons, die den Anfrage-Dialog oeffnen oder
        // abschicken (Leistungen, Seitenleiste, Mobilleiste, Schritt 3).
        // Per Tenant-Override aenderbar, z. B. 'Termin anfragen'.
        'request_cta' => 'Angebot anfragen',

        'facts' => [
            'location' => 'Standort',
            'reviews' => 'Bewertungen',
            'services' => 'Leistungen',
            'member_since' => 'Dabei seit',
        ],

        'services' => [
            'heading' => 'Leistungen',
            'cta_text' => 'Brauchst du etwas davon? Beschreib kurz dein Anliegen, :firma meldet sich mit einem Angebot.',
        ],

        'about' => [
            'heading' => 'Über :firma',
            'read_more' => 'Mehr lesen',
            'read_less' => 'Weniger anzeigen',
            'suggest_edit_text' => 'Stimmt etwas nicht?',
            'suggest_edit' => 'Änderung vorschlagen',
        ],

        'hours' => [
            'heading' => 'Öffnungszeiten',
        ],

        'location' => [
            'heading' => 'Standort',
            'map_label' => 'Standort auf Google Maps öffnen',
            'route' => 'Route planen',
            'show_on_map' => 'Auf der Karte zeigen',
        ],

        'reviews' => [
            'heading' => 'Bewertungen',
            'count' => '{1} :anzahl Bewertung|[0,*] :anzahl Bewertungen',
            'anonymous' => 'Anonym',
            'owner_response' => 'Antwort von :firma',
        ],

        'claim' => [
            'heading' => 'Gehört dir dieser Eintrag?',
            'text' => 'Übernimm den Eintrag kostenlos, aktualisiere Leistungen und Zeiten, antworte auf Bewertungen und erhalte Anfragen direkt aufs Handy.',
            'button' => 'Eintrag übernehmen',
            'status_claimed' => 'Bereits übernommen',
            'status_open' => 'Noch nicht übernommen',
        ],

        'sidebar' => [
            'lead_text' => 'Beschreib dein Anliegen, :firma meldet sich mit einem Angebot.',
            'website' => 'Website',
            'free_note' => 'Kostenlos & unverbindlich',
        ],

        'nearby' => [
            'heading' => 'Weitere :branche_plural in :stadt',
            'all' => 'Alle :anzahl anzeigen',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | request.* — Anfrage-Dialog auf dem Firmenprofil
    |----------------------------------------------------------------------
    | Die Antwortoptionen (Leistung, Zeitpunkt, Objekt, Erreichbarkeit) sind
    | Schluessel der Funnel-Vorlage im Leadsystem und gehoeren nicht hierher.
    */
    'request' => [
        'step' => 'Schritt :schritt von 3',
        'step_done' => 'Anfrage an :firma',
        'title_what' => 'Was brauchst du?',
        'title_where' => 'Wo und wann?',
        'title_contact' => 'Wie erreichen wir dich?',
        'title_done' => 'Danke!',

        'what' => [
            'legend' => 'Leistung',
            'description_label' => 'Beschreib kurz dein Anliegen',
            'description_placeholder' => 'z. B. Worum geht es, seit wann und was ist schon passiert?',
            'description_hint' => 'Je konkreter, desto besser kann :firma einschätzen, worum es geht.',
        ],

        'where' => [
            'legend' => 'Ort und Zeitpunkt',
            'zip_label' => 'Deine Postleitzahl',
            'when_label' => 'Wann soll es losgehen?',
            'object_label' => 'Um welches Objekt geht es?',
        ],

        'contact' => [
            'legend' => 'Kontaktdaten',
            'name_label' => 'Dein Name',
            'phone_label' => 'Telefonnummer',
            'phone_hint' => ':firma ruft dich zurück – darum brauchen wir die Nummer.',
            'reachable_label' => 'Wann erreicht man dich am besten?',
            'email_label' => 'E-Mail',
            // Einwilligungstext, rechtlich relevant: Overrides nur durch Admins (SUN-TXT-012)
            'more_businesses' => 'Falls :firma keine Kapazität hat: Bis zu zwei weitere :betrieb_plural in meiner Nähe dürfen sich ebenfalls melden.',
        ],

        'done' => [
            'heading' => 'Anfrage gesendet',
            'text' => ':firma meldet sich bei dir – meist am selben Werktag. Du bekommst eine SMS, sobald deine Anfrage gelesen wurde.',
        ],

        // Auswahlwerte: Schluessel = Optionswert der Funnel-Vorlage im Leadsystem, nicht umbenennen
        'options' => [
            'leistung' => [
                'elektroinstallation' => 'Elektroinstallation',
                'reparatur' => 'Reparatur / Störung',
                'smart_home' => 'Smart Home / KNX',
                'e_check' => 'E-Check',
                'wallbox' => 'Wallbox',
                'hausgeraet' => 'Hausgerät',
                'sicherungskasten' => 'Sicherungskasten',
                'sonstiges' => 'Sonstiges',
            ],
            'wann' => [
                'notfall' => 'Notfall – heute',
                'diese_woche' => 'Diese Woche',
                'vier_wochen' => 'In den nächsten 4 Wochen',
                'flexibel' => 'Bin flexibel',
            ],
            'objekt' => [
                'wohnung' => 'Wohnung',
                'haus' => 'Haus',
                'gewerbe' => 'Gewerbe',
            ],
            'erreichbar' => [
                'vormittags' => 'Vormittags',
                'nachmittags' => 'Nachmittags',
                'ab_17_uhr' => 'Ab 17 Uhr',
            ],
        ],

        // Honigtopf-Feld, nur fuer Bots sichtbar
        'honeypot_label' => 'Website',

        'back' => 'Zurück',
        'next' => 'Weiter',
        'close' => 'Schließen',
        'privacy' => 'Deine Daten gehen nur an die :betrieb_plural, die sich bei dir melden.',
        'privacy_link' => 'Datenschutz',
    ],

    /*
    |----------------------------------------------------------------------
    | signup.* — Firma eintragen / Konto erstellen (/eintragen)
    |----------------------------------------------------------------------
    */
    'signup' => [
        'meta_title' => ':betrieb kostenlos eintragen',
        'meta_description' => ':betrieb kostenlos eintragen: gefunden werden, Anfragen aufs Handy bekommen und Bewertungen sammeln.',
        'headline' => 'Trag dich kostenlos ein',
        'intro' => 'In zwei Minuten erledigt. Erst die Angaben zu deinem Eintrag, dann ein Konto zum Verwalten.',

        'progress' => [
            'label' => 'Fortschritt',
            'business' => 'Eintrag',
            'account' => 'Konto',
        ],

        'business' => [
            'name_label' => 'Name, wie er auf dem Schild steht',
            'street_label' => 'Straße und Hausnummer',
            'zip_label' => 'PLZ',
            'city_label' => 'Ort',
            'phone_label' => 'Telefonnummer',
            'phone_hint' => 'Diese Nummer steht öffentlich auf deinem Profil.',
            'website_label' => 'Website',
            'website_placeholder' => 'https://',
            'optional' => '(optional)',
        ],

        'account' => [
            'change' => 'Ändern',
            'name_label' => 'Dein Name',
            'email_label' => 'E-Mail',
            'email_hint' => 'Damit meldest du dich an und verwaltest deinen Eintrag.',
            'password_label' => 'Passwort',
            'password_hint' => 'Mindestens 8 Zeichen.',
            'password_show' => 'Passwort anzeigen',
            'password_hide' => 'Passwort verbergen',
            'terms' => 'Ich akzeptiere die :agb und habe die :datenschutz gelesen.',
            'terms_link' => 'AGB',
            'privacy_link' => 'Datenschutzhinweise',
            'logged_in_as' => 'Angemeldet als :name. Der Eintrag kommt in dein bestehendes Konto.',
        ],

        'next' => 'Weiter',
        'submit_guest' => 'Konto anlegen',
        'submit_user' => 'Eintrag anlegen',
        'note_step_one' => 'Kostenlos, ohne Laufzeit.',
        'note_step_two' => 'Kostenlos, keine Kreditkarte nötig.',
        'has_account' => 'Schon ein Konto?',
        'login' => 'Anmelden',

        'done' => [
            'heading' => 'Eintrag angelegt',
            'text' => 'Wir prüfen den Eintrag und schalten ihn meist innerhalb eines Werktags frei.',
            'verify_heading' => 'Bestätige deine E-Mail',
            'verify_text' => 'Wir haben dir einen Link geschickt. Danach prüfen wir den Eintrag und schalten ihn meist innerhalb eines Werktags frei.',
            'complete_profile' => 'Profil vervollständigen',
            'resend' => 'E-Mail noch mal senden',
            'resent' => 'E-Mail ist unterwegs',
        ],

        'panel' => [
            'heading' => 'Danach in deinem Profil',
            'text' => 'Diese Angaben ergänzt du jederzeit selbst – je vollständiger dein Profil, desto mehr Anfragen.',
            'services_title' => 'Leistungen und Einsatzgebiet',
            'services_text' => 'Wofür du Anfragen bekommen willst',
            'hours_title' => 'Öffnungszeiten',
            'hours_text' => 'Profile mit Zeiten werden häufiger angerufen',
            'description_title' => 'Beschreibung und Fotos',
            'description_text' => 'Was dich von anderen unterscheidet',
            'reviews_title' => 'Bewertungen beantworten',
            'reviews_text' => 'Öffentlich unter jeder Bewertung',
        ],

        'after' => [
            'heading' => 'Was danach passiert',
            'review' => 'Wir prüfen den Eintrag – meist innerhalb eines Werktags.',
            'online' => 'Dein Profil geht online und erscheint in der Suche und auf der Stadtseite.',
            'requests' => 'Anfragen kommen per SMS und E-Mail bei dir an.',
        ],

        // Feldnamen fuer :attribute in Validierungsmeldungen (Livewire validationAttributes)
        'attributes' => [
            'firma' => 'Name',
            'strasse' => 'Straße und Hausnummer',
            'plz' => 'PLZ',
            'ort' => 'Ort',
            'tel' => 'Telefonnummer',
            'web' => 'Website',
            'name' => 'Dein Name',
            'email' => 'E-Mail',
            'password' => 'Passwort',
            'agb' => 'AGB und Datenschutzhinweise',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | empty.* — leere Zustaende, immer mit naechstem Schritt
    |----------------------------------------------------------------------
    */
    'empty' => [
        'search' => [
            'heading' => 'Keine passenden :betrieb_plural',
            'text' => 'Zu deiner Suche haben wir nichts gefunden. Probier einen anderen Begriff oder einen größeren Ort in der Nähe.',
            'text_filtered' => 'Mit diesen Filtern bleibt kein Eintrag übrig. Nimm einen Filter heraus oder such ohne Einschränkung.',
            'tip_general' => 'Allgemeiner suchen, etwa nach einer Leistung statt nach einem Namen',
            'tip_place' => 'Nur den Ort oder nur die Postleitzahl eingeben',
            'tip_spelling' => 'Schreibweise prüfen',
            'show_all' => 'Alle :branche_plural anzeigen',
            'signup_text' => 'Fehlt hier ein Eintrag? Trag dich kostenlos ein und werde gefunden.',
            'signup_button' => ':betrieb eintragen',
        ],

        'city' => [
            'heading' => 'Noch keine :betrieb_plural in :stadt',
            'text' => 'In :stadt ist noch niemand eingetragen. Sei als Erstes dabei – der Eintrag ist kostenlos.',
            'text_filtered' => 'Mit diesen Filtern bleibt in :stadt kein Eintrag übrig. Nimm einen Filter heraus, um mehr zu sehen.',
            'signup_button' => ':betrieb eintragen',
            'show_all' => 'Alle :branche_plural anzeigen',
        ],

        'reviews' => [
            'text' => 'Noch keine Bewertungen. Sei die erste Person, die :firma bewertet.',
            'button' => 'Bewertung schreiben',
        ],

        'hours' => [
            'text' => 'Noch keine Öffnungszeiten hinterlegt. Ruf am besten kurz an.',
        ],

        'guide' => [
            'text' => 'Hier erscheinen bald Ratgeber-Artikel. Bis dahin findest du passende :branche_plural direkt über die Suche.',
            'button' => ':branche_plural finden',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | errors.* — Fehlerseiten und Fehlermeldungen
    |----------------------------------------------------------------------
    */
    'errors' => [
        'http' => [
            'back_home' => 'Zur Startseite',
            'search' => ':branche_plural suchen',
            '403' => [
                'title' => 'Kein Zugriff',
                'text' => 'Diese Seite ist für dein Konto nicht freigegeben. Melde dich mit einem anderen Konto an oder geh zurück zur Startseite.',
            ],
            '404' => [
                'title' => 'Seite nicht gefunden',
                'text' => 'Diese Seite gibt es nicht mehr oder die Adresse ist falsch. Über die Suche findest du :branche_plural in deiner Nähe.',
            ],
            '419' => [
                'title' => 'Sitzung abgelaufen',
                'text' => 'Du warst eine Weile inaktiv. Lade die Seite neu und versuch es noch einmal.',
                'button' => 'Seite neu laden',
            ],
            '429' => [
                'title' => 'Zu viele Anfragen',
                'text' => 'Gerade kommt sehr viel auf einmal an. Warte einen Moment und versuch es dann noch einmal.',
            ],
            '500' => [
                'title' => 'Da ist etwas schiefgegangen',
                'text' => 'Wir kümmern uns darum. Versuch es in ein paar Minuten noch einmal.',
            ],
            '503' => [
                'title' => 'Gleich wieder da',
                'text' => ':portal wird gerade gewartet. Schau in ein paar Minuten wieder vorbei.',
            ],
        ],

        'request' => [
            'offline' => 'Keine Verbindung. Prüfe dein Internet und versuch es noch einmal.',
            'rate_limited' => 'Gerade kommen sehr viele Anfragen an. Warte einen Moment und versuch es dann noch einmal.',
            'forbidden' => 'Anfragen sind von dieser Seite aus gerade nicht möglich. Ruf am besten direkt an.',
            'unavailable' => 'Anfragen sind gerade nicht möglich. Ruf am besten direkt an.',
            'generic' => 'Das hat nicht geklappt. Versuch es bitte noch einmal.',
            'contact_missing' => 'Gib Name und Telefonnummer an, damit :firma dich erreichen kann.',
        ],

        'signup' => [
            'name_required' => 'Gib bitte den Namen ein, unter dem man dich findet.',
            'street_required' => 'Ohne Adresse findet dich niemand auf der Stadtseite.',
            'zip_required' => 'Gib bitte die PLZ ein.',
            'zip_format' => 'Die PLZ hat fünf Ziffern.',
            'city_required' => 'Gib bitte den Ort ein.',
            'phone_required' => 'Ohne Telefonnummer kann dich niemand erreichen.',
            'phone_format' => 'Die Nummer sieht unvollständig aus.',
            'website_format' => 'Die Adresse sieht nicht wie eine Website aus.',
            'city_mismatch' => 'PLZ und Ort passen nicht zusammen – prüf bitte beides.',
            'person_required' => 'Wie heißt du?',
            'email_required' => 'Ohne E-Mail kannst du dich später nicht anmelden.',
            'email_format' => 'Die E-Mail-Adresse sieht unvollständig aus.',
            'email_taken' => 'Mit dieser E-Mail gibt es schon ein Konto – melde dich an.',
            'password_min' => 'Das Passwort braucht mindestens 8 Zeichen.',
            'terms_required' => 'Bestätige bitte AGB und Datenschutzhinweise.',
            'throttled' => '{1} Zu viele Versuche. Versuch es in einer Minute noch einmal.|[2,*] Zu viele Versuche. Versuch es in :minuten Minuten noch einmal.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | mail.* — Mails mit Portalbezug (Absender, Eintrag, E-Mail-Bestaetigung)
    |----------------------------------------------------------------------
    | Werden in der Queue mit dem Tenant des ausloesenden Aufrufs gerendert
    | (QueueTenancyBootstrapper), die Branchenbegriffe setzt der
    | TenantTranslator dort genauso ein wie im Request.
    */
    'mail' => [
        'from_name' => ':portal',
        'greeting' => 'Hallo :name,',
        'greeting_anonymous' => 'Hallo,',
        'sign_off' => 'Viele Grüße',
        'sign_off_name' => 'Dein Team von :portal',

        'new_company' => [
            'subject' => 'Neuer Eintrag auf :portal: :firma',
            'preview' => 'Neuer Eintrag auf :portal: :firma',
            'heading' => 'Neuer Eintrag',
            'intro' => 'Auf :portal wurde über „:betrieb eintragen“ ein Eintrag angelegt:',
            'company' => 'Name',
            'address' => 'Adresse',
            'phone' => 'Telefon',
            'website' => 'Website',
            'owner' => 'Eingetragen von',
            'status' => 'Status',
            'status_online' => 'online',
            'status_pending' => 'wartet auf Freischaltung',
            'created_at' => 'Zeitpunkt',
            'created_at_value' => ':zeit Uhr',
            'open' => 'Eintrag in der Verwaltung öffnen',
        ],

        'verify_email' => [
            'subject' => 'Bestätige deine E-Mail für :portal',
            'preview' => 'Nur noch ein Klick, dann prüfen wir deinen Eintrag auf :portal.',
            'heading' => 'Bestätige deine E-Mail',
            'intro' => 'du hast auf :portal ein Konto angelegt. Bestätige bitte deine E-Mail-Adresse, damit wir deinen Eintrag prüfen und freischalten können.',
            'button' => 'E-Mail bestätigen',
            'outro' => 'Du hast kein Konto angelegt? Dann kannst du diese Mail ignorieren.',
            'link_hint' => 'Falls der Button nicht funktioniert, kopier diesen Link in deinen Browser:',
        ],
    ],


    /*
    |----------------------------------------------------------------------
    | cities.* — Staedteuebersicht /staedte
    |----------------------------------------------------------------------
    | :land = Name des Bundeslands
    */
    'cities' => [
        'search' => [
            'label' => 'Stadt oder PLZ',
            'placeholder' => 'Stadt oder PLZ, z. B. Hamburg',
            'placeholder_land' => 'Ort in :land oder PLZ',
            'submit' => 'Stadt finden',
        ],

        'states' => [
            'label' => 'Bundesland',
            'prefix' => 'Bundesland:',
            'heading' => ':branche_plural nach Bundesland',
            'places_count' => '{1} :anzahl Ort|[0,*] :anzahl Orte',
        ],

        'stats' => [
            'businesses' => ':betrieb_plural',
            'places' => 'Orte',
            'states' => 'Bundesländer',
        ],

        'results' => [
            'heading' => '{0} Keine Stadt zu „:begriff“|{1} :anzahl Ort zu „:begriff“|[2,*] :anzahl Orte zu „:begriff“',
            'heading_limit' => 'Mindestens :anzahl Orte zu „:begriff“',
            'empty' => 'Für „:begriff“ ist kein Ort dabei, in dem :betrieb_plural eingetragen sind. Prüf die Schreibweise oder such nach der nächstgrößeren Stadt.',
            'empty_land' => 'Für „:begriff“ ist in :land kein Ort dabei, in dem :betrieb_plural eingetragen sind. Prüf die Schreibweise oder such nach der nächstgrößeren Stadt.',
            'reset' => 'Suche zurücksetzen',
        ],

        'empty' => [
            'heading' => 'Noch keine Städte',
            'text' => 'Es sind noch keine :betrieb_plural mit Ort eingetragen.',
        ],

        'top' => [
            'heading' => 'Größte Städte',
            'heading_land' => 'Größte Orte in :land',
            'count' => '{1} :anzahl :betrieb|[0,*] :anzahl :betrieb_plural',
        ],

        'alphabet' => [
            'heading' => 'Alle Orte in :land von A bis Z',
            'label' => 'Buchstaben',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | categories.* — Leistungsuebersicht /kategorien und Kategorieseite
    |----------------------------------------------------------------------
    | :leistung = Name der Leistung (Kategorie)
    */
    'categories' => [
        'index' => [
            'meta_title' => 'Leistungen',
            'meta_description' => 'Alle Leistungen auf :portal im Überblick – finde passende :betrieb_plural für dein Vorhaben unter :anzahl Einträgen.',
            'heading' => 'Leistungen im Überblick',
            'intro' => '{1} :anzahl Eintrag, sortiert nach dem, was er macht. Wähl eine Leistung – oder such direkt nach deinem Vorhaben.|[0,*] :anzahl :betrieb_plural, sortiert nach dem, was sie machen. Wähl eine Leistung – oder such direkt nach deinem Vorhaben.',
            'search_button' => ':branche_plural finden',
            'all_heading' => 'Alle Leistungen',
            'count' => '{1} :anzahl :betrieb|[0,*] :anzahl :betrieb_plural',
            'stats' => [
                'businesses' => ':betrieb_plural',
                'services' => 'Leistungen',
            ],
            'empty' => [
                'heading' => 'Noch keine Leistungen',
                'text' => 'Für dieses Portal sind noch keine Leistungen angelegt. Über die Suche findest du trotzdem alle :betrieb_plural.',
            ],
        ],

        'show' => [
            'heading' => '{0} Keine :betrieb_plural für :leistung|[1,*] :anzahl :leistung',
            'heading_city' => '{0} Keine :betrieb_plural für :leistung in :stadt|[1,*] :anzahl :leistung in :stadt',
            'meta_description' => '{1} :anzahl :betrieb für :leistung – mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.|[0,*] :anzahl :betrieb_plural für :leistung – mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.',
            'search_label' => 'In :leistung suchen',
            'all_places' => 'Alle Orte',
            'empty_text' => 'Für :leistung sind noch keine :betrieb_plural eingetragen.',
            'empty_text_filtered' => 'Mit den gesetzten Filtern bleiben für :leistung keine :betrieb_plural übrig.',
            'all_services' => 'Alle Leistungen ansehen',
            'more_heading' => 'Weitere Leistungen',
            'places_heading' => ':leistung nach Ort',
        ],
    ],

    'blog' => [
        // Bausteine der Ratgeber-Listen (Uebersicht, Kategorie, Suche, Schlagwort)
        'list' => [
            'search_label' => 'Ratgeber durchsuchen',
            'search_submit' => 'Suchen',
            'editorial_link' => 'So arbeitet unsere Redaktion',
            'filter_all' => 'Alle',
            'article_count' => '{1} :anzahl Artikel|[0,*] :anzahl Artikel',
            'empty_text' => 'Schau bald wieder vorbei – oder stöber in allen Ratgebern.',
            'all_guides' => 'Alle Ratgeber ansehen',
        ],

        // Betriebssuche am Ende der Ratgeberseiten
        'cta' => [
            'button' => ':branche finden',
        ],

        'index' => [
            'topics_heading' => 'Themen',
            'featured_heading' => 'Meistgelesen',
            'latest_heading' => 'Neueste Artikel',
            'filter_label' => 'Nach Thema filtern',
            'empty_heading' => 'Noch keine Artikel vorhanden',
            'empty_text' => 'Bald findest du hier Ratgeber rund um dein Vorhaben.',
            'load_more' => 'Weitere Artikel laden',
        ],

        'category' => [
            'intro' => 'Alle Ratgeber zum Thema :kategorie – verständlich erklärt, damit du Angebote prüfen und auf Augenhöhe mitreden kannst.',
            'heading' => 'Artikel zu :kategorie',
            'filter_label' => 'Thema wechseln',
            'empty_heading' => 'Noch keine Artikel in :kategorie',
        ],

        'search' => [
            'meta_title' => 'Suche: :begriff',
            'crumb' => 'Suche',
            'heading' => 'Suche: „:begriff“',
            'result_count' => '{1} :anzahl Artikel gefunden|[0,*] :anzahl Artikel gefunden',
            'results_heading' => 'Ergebnisse',
            'browse_label' => 'Nach Thema stöbern',
            'empty_heading' => 'Keine Artikel gefunden',
            'empty_text' => 'Versuch es mit einem anderen Suchbegriff – oder stöber in allen Ratgebern.',
        ],

        'tag' => [
            'meta_description' => 'Ratgeber-Artikel zum Thema :schlagwort',
            'intro' => 'Alle Ratgeber-Artikel mit dem Schlagwort :schlagwort.',
            'label' => 'Schlagwort',
            'heading' => 'Artikel zu #:schlagwort',
            'other_tags_label' => 'Weitere Schlagwörter',
            'empty_heading' => 'Noch keine Artikel zu #:schlagwort',
        ],

        // Artikelseite; :datum ist in Kopfzeile und Quellenblock ein <time>-Element (HTML)
        'show' => [
            'faq_heading' => 'Häufige Fragen',
            'updated_at' => 'Aktualisiert am :datum',
            'ai_notice' => 'Maschinell erstellt, redaktionell geprüft',
            'toc_heading' => 'Inhalt',
            'key_facts_caption' => 'Das Wichtigste in Zahlen',
            'region' => [
                'heading' => 'Was in :stadt gilt',
                'compare' => 'Anbieter in :stadt vergleichen',
            ],
            'cta' => [
                'heading_city' => ':branche aus :stadt',
                'heading_nearby' => ':branche aus deiner Nähe',
                'text_count' => '{1} :anzahl :betrieb im Portal. Gib deinen Ort ein und vergleiche zwei bis drei Angebote.|[2,*] :anzahl :betrieb_plural im Portal. Gib deinen Ort ein und vergleiche zwei bis drei Angebote.',
                'text' => 'Gib deinen Ort ein und vergleiche zwei bis drei Angebote.',
            ],
            'freshness_label' => 'Aktualität dieses Beitrags',
            'published_at' => 'Erstellt am :datum',
            'checked_at' => 'Zuletzt geprüft am :datum',
            'sources_heading' => 'Verwendete Quellen',
            'source_as_of' => '(Stand :jahr)',
            'author' => [
                'label' => 'Verantwortlich für diesen Beitrag',
                'process' => 'Recherche und Erstentwurf maschinell, Prüfung und Freigabe redaktionell.',
                'checked_at' => 'Zuletzt geprüft am :datum.',
            ],
            'changelog_heading' => 'Aktualisierungen',
            'changelog_reasons' => 'Anlass: :anlaesse',
            'related_heading' => 'Passende Artikel',
            'sidebar_label' => 'Weitere Informationen zum Artikel',
            'toc_sidebar_label' => 'Inhalt (Seitenleiste)',
            'toc_sidebar_heading' => 'Auf dieser Seite',
        ],
    ],

    'jobs' => [
        'meta_title' => 'Jobs rund um :betrieb_plural',
        'meta_description' => '{1} :anzahl offene Stelle in ganz Deutschland – direkt vom Betrieb, ohne Personalvermittler dazwischen.|[0,*] :anzahl offene Stellen in ganz Deutschland – direkt vom Betrieb, ohne Personalvermittler dazwischen.',
        'headline' => 'Jobs rund um :betrieb_plural',

        'search' => [
            'what_label' => 'Welche Stelle?',
            'what_placeholder' => 'Beruf oder Stichwort',
            'submit' => 'Jobs finden',
        ],

        'results_heading' => '{1} :anzahl Stelle in ganz Deutschland|[0,*] :anzahl Stellen in ganz Deutschland',
        'sorted_by_date' => 'Sortiert nach Aktualität',

        'filters' => [
            'type' => 'Anstellungsart',
            'with_salary' => 'Mit Gehaltsangabe',
            'apprenticeship' => 'Ausbildung',
            'career_change' => 'Quereinstieg',
            'new_this_week' => 'Neu diese Woche',
        ],

        'card' => [
            'location' => 'Ort',
            'type' => 'Anstellungsart',
            'salary' => 'Gehalt',
            'salary_none' => 'Keine Angabe',
            'apply' => 'Bewerben',
            'details' => 'Details',
        ],

        'employer' => [
            'heading' => 'Du suchst Personal?',
            'text' => 'Deine Anzeige erreicht Fachkräfte, die ohnehin auf diesem Portal unterwegs sind.',
            'button' => 'Stelle ausschreiben',
            'price_note' => '30 Tage online · ab 99 €',
        ],

        'alert' => [
            'heading' => 'Job-Mail',
            'text' => 'Neue Stellen in deiner Nähe, einmal pro Woche. Jederzeit abbestellbar.',
            'email_label' => 'E-Mail',
            'email_placeholder' => 'deine@mail.de',
            'button' => 'Job-Mail aktivieren',
            'heading_mobile' => 'Job-Mail einrichten',
            'text_mobile' => 'Neue Stellen in deiner Nähe, einmal pro Woche per Mail. Jederzeit mit einem Klick abbestellbar.',
            'button_mobile' => 'Aktivieren',
        ],

        'cta' => [
            'headline' => 'Du suchst Verstärkung fürs eigene Team?',
            'text' => 'Auf :portal sind Menschen unterwegs, die sich ohnehin für die Branche interessieren – Fachkräfte, die den Betrieb wechseln, und Azubis, die den ersten suchen. Deine Anzeige läuft 30 Tage, erscheint bei Google for Jobs und ist mit deinem Firmenprofil verknüpft.',
            'benefit_duration' => '30 Tage Laufzeit, Verlängerung optional – keine Vertragsbindung',
            'benefit_inbox' => 'Bewerbungen landen direkt in deinem Postfach, ohne Zwischenportal',
            'benefit_google' => 'Anzeige wird automatisch an Google for Jobs übergeben',
            'benefit_profile' => 'Verlinkt mit deinem Firmenprofil – Bewerber sehen Bewertungen und Leistungen',
            'single_ad' => 'Einzelanzeige',
            'price_period' => '/ 30 Tage',
            'price_hint' => 'zzgl. MwSt. Ab 3 Anzeigen günstiger.',
            'button' => 'Stelle ausschreiben',
            'prices' => 'Preise ansehen',
            'questions' => 'Fragen?',
            'write_us' => 'Schreib uns',
            'response_time' => '– Antwort werktags am selben Tag.',
        ],

        'links' => [
            'by_profession' => 'Jobs nach Beruf',
            'by_city' => 'Jobs nach Stadt',
        ],

        'seo' => [
            'heading' => 'Als Fachkraft den Betrieb wechseln',
            'text_choice' => 'Fachkräfte werden überall gesucht – die Frage ist nicht, ob du eine Stelle findest, sondern welche. Achte deshalb weniger auf das Gehalt allein und mehr auf die Punkte darum herum: Wie weit ist der Arbeitsweg im Schnitt? Gibt es einen Dienstwagen oder andere Zuschüsse? Wer zahlt Weiterbildungen und Zertifizierungen?',
            'text_salary' => 'Anzeigen ohne Gehaltsangabe sind kein Ausschlusskriterium, aber ein Grund nachzufragen – über den Filter „Mit Gehaltsangabe“ siehst du zuerst die :betrieb_plural, die Farbe bekennen. Jede Anzeige hier ist mit dem Firmenprofil verknüpft: Ein Blick auf die Bewertungen zeigt dir oft mehr über den Betrieb als der Anzeigentext.',
        ],
    ],

    'faq' => [
        'title' => 'Häufige Fragen',
        'meta_description' => 'Antworten auf häufige Fragen rund um :portal: Suche, Bewertungen, Firmeneinträge und Kontakt.',
        'intro' => 'Kurze Antworten zu Suche, Bewertungen und Firmeneinträgen auf :portal.',

        'empty' => [
            'heading' => 'Noch keine Fragen',
            'text' => 'Hier stehen bald die häufigsten Fragen. Bis dahin helfen wir dir gern direkt weiter.',
        ],

        'contact' => [
            'heading' => 'Deine Frage ist nicht dabei?',
            'text' => 'Schreib uns – wir melden uns in der Regel innerhalb von zwei Werktagen.',
        ],
    ],

    'legal' => [
        'as_of' => 'Stand: :datum',
        'toc' => 'Inhalt',
        'toc_sidebar_label' => 'Inhalt (Seitenleiste)',
        'toc_sidebar' => 'Auf dieser Seite',
        'report' => 'Inhalt melden',
        'report_heading' => 'Fehlerhaften Eintrag melden',
        'report_text' => 'Stimmt eine Angabe zu deinem Betrieb nicht oder soll ein Eintrag entfernt werden? Schreib uns – wir kümmern uns in der Regel innerhalb von zwei Werktagen.',
        'pending' => 'Dieser Text wird noch eingerichtet.',
    ],

    'consent' => [
        'essential' => 'Nur Notwendige',

        'necessary' => [
            'title' => 'Notwendig',
            'text' => 'Technisch erforderliche Cookies für Login, Formulare und Sicherheit. Ohne diese funktioniert die Website nicht.',
            'session' => 'Session-Cookie — hält deine Sitzung aktiv (Ablauf: Sitzungsende)',
            'csrf' => 'CSRF-Token — schützt vor Cross-Site-Angriffen (Ablauf: Sitzungsende)',
            'consent' => 'Cookie-Einstellungen — speichert deine Auswahl (Ablauf: 12 Monate)',
        ],

        'statistics' => [
            'title' => 'Statistik',
            'text' => 'Hilft uns zu verstehen, wie Besucher die Website nutzen. Die Daten werden anonymisiert erhoben (Google Analytics).',
            'analytics' => 'Google Analytics (_ga, _ga_*) — anonymisierte Besucherstatistiken (Ablauf: 2 Jahre)',
        ],

        'marketing' => [
            'title' => 'Marketing',
            'text' => 'Werden genutzt, um dir relevante Werbung und Inhalte anzuzeigen. Aktuell setzen wir keine Marketing-Cookies ein.',
        ],
    ],

    'auth' => [
        'login' => [
            'title' => 'Anmelden',
            'forgot' => 'Vergessen?',
            'failed' => 'E-Mail oder Passwort stimmt nicht. Prüf beides noch mal.',
            'remember' => 'Angemeldet bleiben',
            'submit' => 'Anmelden',
            'no_account' => 'Noch kein Konto?',
            'signup' => ':betrieb kostenlos eintragen',
            'problems' => 'Probleme beim Anmelden?',
        ],

        'reset' => [
            'title' => 'Passwort zurücksetzen',
            'text' => 'Gib deine E-Mail ein, wir schicken dir einen Link zum Neuvergeben.',
            'email_hint' => 'Die Adresse, mit der du dich registriert hast.',
            'submit' => 'Link senden',
            'back' => 'Zurück zur Anmeldung',
        ],

        'sent' => [
            'title' => 'Schau in dein Postfach',
            'text' => 'Falls ein Konto mit dieser Adresse existiert, ist der Link unterwegs. Er gilt :minuten Minuten.',
        ],
    ],


    /*
    |----------------------------------------------------------------------
    | claim.* — Eintrag übernehmen, Nachweis hochladen (#20)
    |----------------------------------------------------------------------
    */
    'claim' => [
        'step' => 'Schritt :schritt von 3',

        // Seite "Ist das dein Betrieb?" (pages/companies/suggest-edit)
        'page' => [
            'meta_title' => 'Ist das dein Betrieb? :firma',
            'meta_description' => 'Übernimm den Eintrag von :firma kostenlos oder melde, was daran nicht stimmt.',
            'heading' => 'Ist das dein Betrieb?',
            'intro' => 'Übernimm den Eintrag von :firma – kostenlos, in zwei Minuten. Oder sag uns, was daran nicht stimmt.',
            'tabs_label' => 'Was möchtest du tun?',
            'tab_claim' => 'Übernehmen',
            'tab_suggest' => 'Fehler melden',

            'benefits' => [
                'heading' => 'Das bekommst du als Inhaberin oder Inhaber',
                'requests_title' => 'Anfragen direkt aufs Handy',
                'requests_text' => 'Kundinnen und Kunden schicken über dein Profil Anfragen – du bekommst sie per SMS und E-Mail.',
                'reviews_title' => 'Auf Bewertungen antworten',
                'reviews_text' => 'Deine Antwort steht öffentlich unter der Bewertung.',
                'profile_title' => 'Profil selbst pflegen',
                'profile_text' => 'Fotos, Leistungen, Öffnungszeiten – Änderungen sind sofort live.',
                'badge_title' => 'Badge „Geprüfter Eintrag“',
                'badge_text' => 'Steht sichtbar auf deinem Profil und in der Ergebnisliste.',
            ],

            'stats' => [
                'claimed' => 'Übernommene Einträge',
                'duration' => 'Dauer',
                'duration_value' => '2 Min.',
                'cost' => 'Kosten',
                'cost_value' => '0 €',
                'cancellation' => 'Kündigung',
                'cancellation_value' => 'Jederzeit',
            ],

            // :email = mailto-Link (HTML), setzt die View nach dem Escapen ein
            'contact' => 'Fragen? :email – wir antworten werktags innerhalb eines Tages.',
        ],

        // Seite /firma/{slug}/verifizierung (pages/companies/verify-claim)
        'verify_page' => [
            'meta_title' => 'Nachweis hochladen – :firma',
            'heading' => 'Fast geschafft',
            'intro' => 'Noch der Nachweis, dann gehört der Eintrag von :firma dir.',
        ],

        'pending' => [
            'heading' => 'Übernahme beantragt',
            'text' => 'Du verwaltest bereits einen Betrieb. Wir prüfen die zusätzliche Übernahme von :firma und melden uns per E-Mail.',
            'dashboard' => 'Zur Verwaltung',
        ],

        'confirm' => [
            'heading' => 'Betrieb bestätigen',
            'already_claimed' => 'Dieser Eintrag wurde bereits von jemand anderem übernommen. Gehört er dir, schreib uns über „Fehler melden“ – wir klären das.',
            'not_yours' => 'Nicht dein Betrieb?',
            'create' => 'Neuen Eintrag anlegen',
        ],

        'register' => [
            'email_hint' => 'Damit meldest du dich später an. Am besten eine Adresse mit Firmen-Domain.',
            // :datenschutz = Link (HTML), Linktext privacy_link
            'note' => 'Kostenlos, ohne Kreditkarte. Hinweise zum :datenschutz.',
            'privacy_link' => 'Datenschutz',
            'has_account' => 'Schon registriert?',
        ],

        'login' => [
            'submit' => 'Anmelden und weiter',
            'no_account' => 'Noch kein Konto?',
            'register' => 'Jetzt kostenlos registrieren',
        ],

        'logged_in' => [
            // :name wird von der View hervorgehoben (HTML)
            'as' => 'Angemeldet als :name.',
            'has_company' => 'Du verwaltest bereits :firma. Dieser Eintrag kommt zusätzlich dazu.',
            'confirm_owner' => 'Ich gehöre zu :firma und darf den Eintrag verwalten.',
        ],

        // Nachweis-Upload im Theme sun-v2 (livewire/portal/claim-verification-upload)
        'verification' => [
            'heading' => 'Nachweisen, dass du zum Betrieb gehörst',
            'intro' => 'So kann niemand fremde Betriebe übernehmen. Lade ein Dokument hoch, das dich mit dem Betrieb verbindet.',
            'no_request' => 'Für diesen Eintrag liegt noch keine Übernahme von dir vor. Lade die Seite neu und starte bei Schritt 1.',
            'rejected' => 'Dein letzter Nachweis hat nicht gereicht. Lade bitte neue Unterlagen hoch.',
            // :grund = Ablehnungsgrund aus der Verwaltung
            'rejected_reason' => 'Dein letzter Nachweis hat nicht gereicht: :grund Lade bitte neue Unterlagen hoch.',
            'manual_title' => 'Manuell prüfen lassen',
            'manual_text' => 'Du lädst Gewerbeanmeldung oder Briefkopf hoch, wir melden uns innerhalb von 2 Werktagen.',
            'choose_files' => 'Dateien auswählen',
            'file_hint' => 'PDF, JPG oder PNG · bis 5 Dateien · je max. 10 MB',
            'uploading' => 'Wird hochgeladen …',
            // :datei = Dateiname
            'remove_label' => 'Datei entfernen: :datei',
            'remove' => 'Entfernen',
            'comment_label' => 'Hinweis für uns',
            'comment_placeholder' => 'z. B. Ich bin seit 2019 Geschäftsführer, der Briefkopf ist noch auf den alten Namen.',
            'submit' => 'Nachweis senden',
            'sending' => 'Wird gesendet …',

            'done' => [
                'heading' => 'Nachweis ist da – wir prüfen',
                'text' => 'Wir sehen uns die Unterlagen innerhalb von 2 Werktagen an. Danach gehört der Eintrag :firma dir und du bekommst eine E-Mail.',
                'next_heading' => 'Als Nächstes lohnt sich:',
                'next_hours' => 'Öffnungszeiten eintragen – Profile mit Zeiten bekommen 2× so viele Anrufe',
                'next_photo' => 'Ein echtes Foto vom Betrieb oder Team hochladen',
                'next_services' => 'Die Leistungen anhaken, für die du Anfragen willst',
            ],
        ],

        // Nachweis-Upload im Default-Theme (livewire/portal/claim-verification-upload)
        'upload' => [
            'region_label' => 'Dokumente hochladen',
            'drop_title' => 'Dateien hierher ziehen',
            'drop_text' => 'oder klicken zum Auswählen',
            'hint' => 'PDF, JPG oder PNG — max. 10 MB pro Datei — max. 5 Dateien',

            'accepted' => [
                'heading' => 'Welche Dokumente werden akzeptiert?',
                'trade_registration' => 'Gewerbeanmeldung',
                'commercial_register' => 'Handelsregisterauszug',
                'craft_card' => 'Handwerkskarte',
                'chamber_certificate' => 'IHK-/HWK-Bescheinigung',
                'business_letter' => 'Geschäftsbrief/Rechnung mit Firmenname + Adresse',
            ],

            'uploading' => 'Dateien werden hochgeladen …',
            'files_label' => 'Hochgeladene Dateien',
            'comment_label' => 'Optionaler Kommentar',
            'comment_placeholder' => 'z. B. Ich bin der Inhaber seit 2018, die Gewerbeanmeldung ist auf meinen Namen ausgestellt …',
            'comment_hint' => 'Hilft uns bei der Prüfung — ist aber nicht verpflichtend.',
            'privacy' => 'Deine Dokumente werden verschlüsselt übertragen und nach 90 Tagen automatisch gelöscht.',
            'privacy_link' => 'Datenschutzerklärung',
            'submit' => 'Dokumente einreichen',
            'submitting' => 'Wird eingereicht …',

            'success' => [
                'heading' => 'Deine Unterlagen sind eingereicht!',
                // :dauer = fett gesetzte Dauer (HTML), Text aus duration
                'text' => 'Wir prüfen deine Dokumente innerhalb von :dauer. Du erhältst eine E-Mail, sobald die Prüfung abgeschlossen ist.',
                'duration' => '48 Stunden',
                'next_heading' => 'Was passiert jetzt?',
                'step_uploaded' => 'Dokumente hochgeladen',
                'step_review' => 'Prüfung durch unser Team (bis 48h)',
                'step_assigned' => 'Firma wird dir zugewiesen + Trial startet',
                'back' => 'Zurück zum Portal',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | review_form.* — Bewertung schreiben (livewire/portal/submit-review-form)
    |----------------------------------------------------------------------
    */
    'review_form' => [
        'heading' => 'Bewertung für :firma schreiben',
        'rating_label' => 'Wie bewertest du den Betrieb? *',
        'rating_group' => 'Bewertung in Sternen',
        // trans_choice() mit dem Sternwert (auch 0,5er-Schritte), :wertung mit Komma
        'star_label' => '{1} :wertung Stern|[0,*] :wertung Sterne',
        'author_placeholder' => 'z. B. Maria S.',
        'author_hint' => 'Wird öffentlich angezeigt. Leer = „Anonym“',
        'title_label' => 'Titel',
        'title_placeholder' => 'z. B. Sehr zufrieden',
        'body_label' => 'Deine Erfahrung',
        'body_placeholder' => 'Wie lief der Auftrag?',
        'moderation_note' => 'Deine Bewertung wird geprüft und erscheint erst nach der Freigabe.',
        'cancel' => 'Abbrechen',
        'submit' => 'Bewertung absenden',
        'sending' => 'Wird gesendet …',

        'done' => [
            'heading' => 'Danke für deine Bewertung!',
            'text' => 'Wir prüfen sie und schalten sie danach frei — erst dann erscheint sie auf dieser Seite, meist innerhalb von 24 Stunden.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | suggest_edit.* — Fehler melden (livewire/portal/suggest-edit-form)
    |----------------------------------------------------------------------
    */
    'suggest_edit' => [
        'heading' => 'Was stimmt nicht?',
        'intro' => 'Du musst nicht zum Betrieb gehören. Wir prüfen den Hinweis und korrigieren den Eintrag.',
        'legend' => 'Was ist falsch?',

        // Beschriftung geht als "reason" an die Verwaltung
        'choices' => [
            'address' => 'Adresse',
            'phone' => 'Telefonnummer',
            'hours' => 'Öffnungszeiten',
            'website' => 'Website',
            'services' => 'Leistungen',
            'closed' => 'Betrieb existiert nicht mehr',
            'duplicate' => 'Doppelter Eintrag',
            'other' => 'Sonstiges',
        ],

        'correction_label' => 'Wie lautet es richtig?',
        'correction_placeholder' => 'z. B. Die Telefonnummer ist seit Mai eine andere, die alte ist abgeschaltet.',
        'email_label' => 'Deine E-Mail',
        'email_hint' => 'Nur falls wir eine Rückfrage haben. Wird nicht veröffentlicht.',
        'submit' => 'Änderung senden',

        'done' => [
            'heading' => 'Danke für den Hinweis',
            'text' => 'Wir prüfen die Änderung meist innerhalb von 2 Werktagen.',
            'back' => 'Zurück zum Eintrag',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | report_review.* — Bewertung melden (livewire/reviews/report-review-button)
    |----------------------------------------------------------------------
    */
    'report_review' => [
        'button' => 'Bewertung melden',
        'legend' => 'Warum möchtest du diese Bewertung melden?',
        'comment_label' => 'Hinweis',
        'cancel' => 'Abbrechen',
        'submit' => 'Melden',
        'done' => 'Danke für den Hinweis. Wir prüfen die Bewertung.',
    ],

    /*
    |----------------------------------------------------------------------
    | claim_modal.* — Eintrag-uebernehmen-Dialog (Default-Theme,
    | livewire/portal/claim-modal). Sie-Ansprache wie in der Vorlage.
    | :firma kommt teils als fertiges, escaptes HTML (Akzent-Span) an;
    | :agb/:datenschutz sind Links (HTML).
    |----------------------------------------------------------------------
    */
    'claim_modal' => [
        'success' => [
            'heading' => 'Fast geschafft!',
            'text' => 'Bestätigen Sie kurz, dass :firma Ihnen gehört.',
            'upload_hint' => 'Laden Sie ein Dokument hoch (z.B. Gewerbeanmeldung).',
            'upload' => 'Dokumente hochladen',
            'loading' => 'Wird geladen…',
            'verify_sent' => 'Wir haben Ihnen eine Bestätigungs-E-Mail gesendet.',
            'verify_click' => 'Bitte klicken Sie den Link in der E-Mail.',
        ],

        'guest' => [
            'heading' => 'Übernehmen Sie :firma',
            'subtitle' => 'Kostenlos registrieren und Ihren Eintrag verwalten.',
            'tabs_label' => 'Registrierung oder Anmeldung',
            'tab_register' => 'Registrieren',
            'tab_login' => 'Anmelden',
        ],

        'register' => [
            'name_label' => 'Name',
            'name_placeholder' => 'Ihr vollständiger Name',
            'email_label' => 'E-Mail',
            'email_placeholder' => 'ihre@email.de',
            'password_label' => 'Passwort',
            'password_placeholder' => 'Mindestens 8 Zeichen',
            'submit' => 'Kostenlos registrieren & übernehmen',
            'loading' => 'Wird erstellt…',
            'legal' => 'Mit der Registrierung akzeptieren Sie unsere :agb und :datenschutz.',
            'terms_link' => 'Nutzungsbedingungen',
            'privacy_link' => 'Datenschutzerklärung',
        ],

        'login' => [
            'email_label' => 'E-Mail',
            'email_placeholder' => 'ihre@email.de',
            'password_label' => 'Passwort',
            'password_placeholder' => 'Ihr Passwort',
            'remember' => 'Angemeldet bleiben',
            'forgot' => 'Passwort vergessen?',
            'submit' => 'Anmelden & :firma übernehmen',
            'loading' => 'Wird angemeldet…',
        ],

        'benefits' => [
            'free' => 'Kostenlos',
            'gdpr' => 'DSGVO-konform',
            'premium_trial' => '30 Tage Premium gratis',
        ],

        'no_company' => [
            'heading' => ':firma übernehmen',
            'subtitle' => 'Hallo :name! Bestätigen Sie, dass Sie der Inhaber dieses Unternehmens sind.',
            'update_title' => 'Daten aktualisieren',
            'update_text' => 'Adresse, Beschreibung, Kontakt und mehr selbst pflegen.',
            'reviews_title' => 'Auf Bewertungen antworten',
            'reviews_text' => 'Reagieren Sie auf Kundenfeedback — zeigen Sie Engagement.',
            'stats_title' => 'Statistiken einsehen',
            'stats_text' => 'Profilaufrufe, Kontaktklicks und Trends im Dashboard.',
            'confirm' => 'Ich bestätige, dass ich der Inhaber oder ein bevollmächtigter Vertreter von :firma bin.',
            'submit' => 'Jetzt übernehmen',
            'loading' => 'Wird übernommen…',
        ],

        'has_company' => [
            'heading' => 'Weitere Firma übernehmen',
            'subtitle' => 'Sie verwalten bereits ein Unternehmen. Möchten Sie :firma zusätzlich übernehmen?',
            'current' => 'Ihre aktuelle Firma',
            'confirm' => 'Ich bestätige, dass ich auch Inhaber oder bevollmächtigter Vertreter von :firma bin.',
            'submit' => ':firma zusätzlich übernehmen',
            'loading' => 'Wird übernommen…',
        ],

        'already_claimed' => [
            'heading' => 'Eintrag bereits übernommen',
            'subtitle' => ':firma wird bereits von einem anderen Nutzer verwaltet.',
            'dispute_text' => 'Wenn Sie der rechtmäßige Inhaber sind, können Sie eine Überprüfung beantragen. Unser Team wird den Fall innerhalb von 48 Stunden prüfen.',
            'dispute' => 'Überprüfung beantragen',
            'loading' => 'Wird gesendet…',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | newsletter.* — Newsletter-Anmeldung im Footer (Default-Theme)
    |----------------------------------------------------------------------
    */
    'newsletter' => [
        'success' => 'Vielen Dank! Sie erhalten ab sofort unseren Newsletter.',
        'form_label' => 'Newsletter-Anmeldung',
        'email_label' => 'E-Mail-Adresse',
        'email_placeholder' => 'Ihre E-Mail-Adresse',
        'submit' => 'Abonnieren',
    ],

];
