{{--
    LEGAL-3: DSGVO Cookie-Hinweis — Markup und Mechanik liegen seit #34
    einmal in <x-cookie-consent-dialog> (resources/views/components), damit
    das zentrale SaaSykit-Layout denselben Hinweis zeigt.
--}}
@php
    // Spielt dieses Portal Werbung aus? Derselbe Schalter wie der
    // AdSense-Abschnitt der Datenschutzerklaerung (#26).
    $adsActive = app(\App\Ads\Support\AdSensePrivacySection::class)->active();
@endphp

<x-cookie-consent-dialog :privacy-url="route('portal.datenschutz')" :ads-active="$adsActive" />
