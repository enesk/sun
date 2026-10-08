{{-- GA4: Vollständiges Tracking — lädt immer mit granted consent.
     Eingerichtet wird in resources/js/analytics.js aus diesen Meta-Angaben;
     kein Inline-Skript, damit die Policy ohne 'unsafe-inline' auskommt (#21). --}}
@if (!empty(config('app.google_tracking_id')))
    <meta name="ga-id" content="{{ config('app.google_tracking_id') }}">
@endif

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke mit
     und bekommt darum das Nonce des Requests. --}}
@if (!empty(config('app.tracking_scripts')))
    {!! \App\Services\Security\CspNonce::inject(config('app.tracking_scripts')) !!}
@endif
