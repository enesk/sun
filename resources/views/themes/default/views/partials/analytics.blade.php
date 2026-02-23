{{-- LEGAL-3: Analytics nur noch per Cookie-Consent laden --}}
{{-- GA/GTM IDs werden als Meta-Tags bereitgestellt. --}}
{{-- Das tatsaechliche Laden uebernimmt der cookieConsent Alpine-Component --}}
{{-- wenn der User der Kategorie "Statistik" zugestimmt hat. --}}
@if(!empty($currentTenant))
    @php
        $gaId = $currentTenant->getAttribute('settings.google_analytics_id');
        $gtmId = $currentTenant->getAttribute('settings.google_tag_manager_id');
    @endphp

    @if(!empty($gaId))
        <meta name="ga-id" content="{{ e($gaId) }}">
    @endif

    @if(!empty($gtmId))
        <meta name="gtm-id" content="{{ e($gtmId) }}">
    @endif
@endif
