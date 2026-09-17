<?php

/*
 * Texte des Premium-Moduls (#17): Preisseite, "Mein Plan", Sperr-Hinweise,
 * Upsell-Banner und Hinweise oben im Betriebsbereich. Plaene, Features und
 * Limits selbst stehen ausschliesslich in config/premium.php.
 * Geladen ueber den Zusatzpfad lang/ (AppServiceProvider), aufgeloest ueber
 * fallback_locale de.
 */
return [

    'tiers' => [
        'free' => 'Basis',
        'pro' => 'Pro',
        'premium' => 'Premium',
    ],

    'tier_taglines' => [
        'free' => 'Der kostenlose Eintrag im Verzeichnis.',
        'pro' => 'Mehr Sichtbarkeit und Anfragen direkt an deinen Betrieb.',
        'premium' => 'Alles aus Pro, dazu Referenzen, Video und Top-Platzierung.',
    ],

    // Schluessel = PremiumFeature-Wert
    'features' => [
        'ad_free' => 'Profil ohne Werbung und ohne Wettbewerber',
        'gallery_photos' => 'Fotos in der Galerie',
        'video_embed' => 'Video im Profil',
        'references' => 'Projektreferenzen',
        'service_catalog' => 'Leistungskatalog',
        'verified_badge' => 'Verifiziert-Badge',
        'review_replies' => 'Auf Bewertungen antworten',
        'review_widget' => 'Bewertungs-Widget für die eigene Website',
        'exclusive_leads' => 'Anfragen direkt an deinen Betrieb',
        'lead_quota' => 'Exklusive Anfragen pro Monat',
        'job_postings' => 'Aktive Stellenanzeigen',
        'job_highlight' => 'Hervorgehobene Stellenanzeigen',
        'statistics' => 'Statistiken',
        'monthly_report' => 'Monatlicher Report per E-Mail',
        'featured_placement' => 'Top-Platzierung buchbar',
    ],

    'limit' => [
        'unlimited' => 'unbegrenzt',
        'count' => ':anzahl',
        'yes' => 'enthalten',
        'no' => 'nicht enthalten',
    ],

    'price' => [
        'per_month' => '/ Monat',
        'per_year' => '/ Jahr',
        'free' => '0 €',
        'vat' => 'zzgl. 19 % USt.',
        'free_months' => '{1} :count Monat gratis|[2,*] :count Monate gratis',
        'unavailable' => 'Bald verfügbar',
    ],

    'pricing' => [
        'meta_title' => 'Preise für Betriebe | :portal',
        'meta_description' => 'Basis, Pro oder Premium: Was ein Eintrag auf :portal kostet, was jedes Paket enthält und wie die Top-Platzierung in deiner Stadt funktioniert.',
        'crumb' => 'Preise',
        'title' => 'Mehr Kunden über :portal',
        'intro' => 'Der Basis-Eintrag bleibt kostenlos. Mit Pro und Premium bekommst du Anfragen direkt, ein Profil ohne Wettbewerber und mehr Platz für deine Arbeit.',
        'billing_label' => 'Zahlweise',
        'monthly' => 'Monatlich',
        'yearly' => 'Jährlich',
        'recommended' => 'Empfohlen',
        'cta_free' => 'Kostenlos eintragen',
        'cta_book' => ':plan wählen',
        'cta_current' => 'Dein aktuelles Paket',
        'cta_manage' => 'Paket verwalten',
        'trial' => ':tage Tage kostenlos testen, danach monatlich kündbar.',
        'terms' => 'Alle Preise verstehen sich zuzüglich 19 % Umsatzsteuer. Die Vertragslaufzeit beläuft sich auf 12 Monate. Der Vertrag verlängert sich automatisch um die Vertragsdauer, sofern er nicht mindestens drei Monate vor Ablauf des jeweils vereinbarten Vertragszeitraums gekündigt wird.',
        'yearly_saving' => 'Bei jährlicher Zahlung sind zwei Monate geschenkt.',
        'business_only' => 'Das Angebot richtet sich ausschließlich an Unternehmer im Sinne des § 14 BGB.',
        'compare_title' => 'Alle Funktionen im Vergleich',
        'compare_feature' => 'Funktion',
        'addon' => [
            'title' => 'Top-Platzierung',
            'text' => 'Dein Betrieb steht in deiner Stadt und Branche ganz oben – vor allen anderen Einträgen. Pro Stadt und Branche gibt es nur :max Plätze.',
            'requires' => 'Buchbar mit dem Premium-Paket, zusätzlich zum Abo.',
            'slots' => '{0} Keine Plätze mehr frei in :stadt|{1} Noch :anzahl Platz frei in :stadt|[2,*] Noch :anzahl Plätze frei in :stadt',
            'slots_category' => 'Branche: :branche',
            'choose' => 'Wie viele Plätze sind in deiner Stadt noch frei?',
            'city' => 'Stadt',
            'category' => 'Branche',
            'choose_city' => 'Stadt wählen',
            'show' => 'Anzeigen',
            'cta' => 'Top-Platzierung buchen',
        ],
    ],

    'plan' => [
        'title' => 'Mein Plan',
        'intro' => 'Dein Paket, die Laufzeit und deine gebuchten Top-Platzierungen.',
        'current' => 'Aktuelles Paket',
        'since' => 'Seit',
        'until' => 'Läuft bis',
        'renews' => 'Nächste Abbuchung',
        'amount' => 'Betrag',
        'unlimited' => 'unbefristet',
        'canceled' => 'Gekündigt zum :datum. Bis dahin bleiben alle Funktionen aktiv.',
        'free_text' => 'Du nutzt den kostenlosen Basis-Eintrag.',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Downgrade',
        'cancel' => 'Kündigen',
        'cancel_confirm' => 'Abo wirklich zum Ende der Laufzeit kündigen?',
        'billing' => 'Rechnungen & Zahlungsdaten',
        'compare' => 'Pakete vergleichen',
        'cancel_failed' => 'Das Abo kann derzeit nicht gekündigt werden.',
        'cancel_done' => 'Dein Abo endet zum :datum. Bis dahin bleiben alle Funktionen aktiv.',
        'features_title' => 'In deinem Paket',
        'placements_title' => 'Gebuchte Top-Platzierungen',
        'placements_empty' => 'Du hast keine Top-Platzierung gebucht.',
        'placement_period' => ':von bis :bis',
        'placement_open' => 'seit :von, verlängert sich monatlich',
        'placements_book' => 'Top-Platzierung buchen',
    ],

    'locked' => [
        'label' => 'Gesperrt',
        'text' => 'Ab :plan verfügbar.',
        'link' => 'Pakete ansehen',
    ],

    'upsell_banner' => [
        'title' => 'Mehr aus deinem Eintrag machen',
        'competitors' => 'Auf deinem Profil werden Wettbewerber angezeigt.',
        'marketplace' => 'Anfragen gehen an den Marktplatz, nicht direkt an dich.',
        'cta' => 'Pakete ansehen',
        'dismiss' => 'Hinweis schließen',
    ],

    'alerts' => [
        // SUN-PREM-009
        'quota_exhausted' => 'Dein Anfragen-Kontingent für diesen Monat ist erschöpft. Neue Anfragen gehen bis zum Monatsende an den Marktplatz.',
        'quota_exhausted_cta' => 'Paket vergleichen',
        // SUN-PREM-004
        'grace' => '{0} Zahlung fehlgeschlagen – heute letzter Tag. Bitte aktualisiere deine Zahlungsdaten.|{1} Zahlung fehlgeschlagen – noch :tage Tag. Bitte aktualisiere deine Zahlungsdaten.|[2,*] Zahlung fehlgeschlagen – noch :tage Tage. Bitte aktualisiere deine Zahlungsdaten.',
        'grace_cta' => 'Zahlungsdaten prüfen',
    ],
];
