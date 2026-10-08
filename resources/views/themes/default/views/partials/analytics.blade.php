{{-- GA4 und Tag Manager: laufen mit granted consent.
     Eingerichtet wird in resources/js/analytics.js aus diesen Meta-Angaben;
     kein Inline-Skript, damit die Policy ohne 'unsafe-inline' auskommt (#21). --}}
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
