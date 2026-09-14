<?php

/*
|--------------------------------------------------------------------------
| Moderation von Nutzerinhalten (#13)
|--------------------------------------------------------------------------
|
| Bewertungen sind fail-closed: neu = pending, sichtbar erst nach Freigabe.
| Der ReviewSpamDetector sortiert Verdachtsfaelle nur vor (needs_review) und
| lehnt nie selbst ab.
|
*/

return [

    'reviews' => [

        /*
         * Stichwoerter, ohne Gross-/Kleinschreibung und als ganzes Wort.
         * Ein * am Ende erlaubt Verlaengerungen ('Bewerbung*' trifft auch
         * 'Bewerbungsunterlagen'). Umlaute und ß werden vor dem Vergleich
         * ausgeschrieben (ae, oe, ue, ss), 'gruessen' trifft also auch 'Grüßen'.
         * Mehrere Woerter duerfen durch beliebige Leerzeichen getrennt sein.
         *
         * 'Stelle' und 'Position' allein treffen vor allem "an dieser Stelle"
         * und Rechnungspositionen (Elektrikerportal: 1.297 bzw. 67 Treffer),
         * deshalb 'Stelle' nur im Bewerbungskontext und 'Position' ohne Plural.
         */
        'keywords' => [
            'Bewerbung*',
            'bewerbe',
            'bewerben',
            'Lebenslauf*',
            'Anschreiben',
            'Position',
            'Stellenangebot*',
            'Stellenanzeige*',
            'Stellenausschreibung*',
            'offene Stelle*',
            'freie Stelle*',
            'ausgeschriebene Stelle*',
            'um die Stelle',
            'um eine Stelle',
            'Ausbildungsplatz*',
            'Praktikum*',
        ],

        /*
         * Grussformeln zaehlen nur zusammen mit einem Stichwort aus 'keywords'.
         * Allein sind sie meist Dankschreiben an den Betrieb (Elektrikerportal
         * #19: 132 x 'mit freundlichen gruessen', 43 x 'sehr geehrte' im Bestand).
         */
        'greeting_keywords' => [
            'sehr geehrte*',
            'mit freundlichen gruessen',
        ],

        // E-Mail-Adressen, URLs und Telefonnummern im Text (Titel, Text, Name)
        'detect_emails' => true,
        'detect_urls' => true,
        'detect_phone_numbers' => true,

        // Mindestanzahl Ziffern, ab der eine Ziffernfolge als Telefonnummer gilt
        'phone_min_digits' => 7,

        /*
         * Texte ueber dieser Laenge (Zeichen, Titel + Text) gehen in die Pruefung.
         * Das gilt allein nur fuer neue Bewertungen. Beim Bestandsscan
         * (moderation:scan-existing-reviews) zaehlt nur ein Bewerbungsschreiben
         * (Stichwort plus Grussformel) — dort waren lange Texte, Links und
         * einzelne Stichwoerter fast immer echte Kundenbewertungen (#19).
         */
        'max_length' => 1500,

        // Melden-Button: Versuche je IP bzw. angemeldetem Nutzer im Zeitfenster
        'report' => [
            'max_attempts' => 5,
            'decay_seconds' => 3600,
            'reasons' => [
                'spam' => 'Spam oder Werbung',
                'no_customer' => 'Kein echter Kunde',
                'offensive' => 'Beleidigend',
                'other' => 'Sonstiges',
            ],
        ],

        // Navigations-Badge in der Filament-Resource
        'badge_cache_seconds' => 60,
    ],

];
