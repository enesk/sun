{{-- Messkennungen als Markup — die einzige Stelle, die ga-id/gtm-id ausgibt (#14).
     Aufgeloest wird in App\Analytics\AnalyticsResolver (Rangfolge Tenant ->
     global -> aus), eingerichtet in resources/js/analytics.js. Kein
     Inline-Skript, damit die Policy ohne 'unsafe-inline' auskommt (#21).

     $defer: Millisekunden nach 'load', bis gtag.js/gtm.js nachgeladen wird.
     Ohne Angabe laedt das Skript sofort. --}}
@props(['defer' => null, 'tenant' => null])

@php
    $analytics = app(\App\Analytics\AnalyticsResolver::class)->resolve($tenant);
@endphp

@if ($analytics->gaId !== null)
    <meta name="ga-id" content="{{ $analytics->gaId }}">
@endif

@if ($analytics->gtmId !== null)
    <meta name="gtm-id" content="{{ $analytics->gtmId }}">
@endif

@if ($analytics->measures() && $defer !== null)
    <meta name="analytics-defer" content="{{ (int) $defer }}">
@endif
