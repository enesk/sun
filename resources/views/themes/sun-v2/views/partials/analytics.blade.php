{{-- GA4 wie im Default-Theme, das Skript (~170 KB) laedt aber erst nach dem Seitenaufbau:
     Aufrufe landen vorher in dataLayer und gehen danach mit, der LCP wartet nicht darauf.
     Eingerichtet wird in resources/js/analytics.js — kein Inline-Skript, damit die
     Policy ohne 'unsafe-inline' auskommt (#21). --}}
@if (!empty(config('app.google_tracking_id')))
    <meta name="ga-id" content="{{ config('app.google_tracking_id') }}">
    <meta name="analytics-defer" content="1500">
@endif

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke mit
     und bekommt darum das Nonce des Requests. --}}
@if (!empty(config('app.tracking_scripts')))
    {!! \App\Services\Security\CspNonce::inject(config('app.tracking_scripts')) !!}
@endif
