{{-- GA4 und Tag Manager der SaaSykit-Seiten (kein Portal-Theme): Skript laedt
     sofort. Rangfolge und Markup stecken in <x-analytics-meta> (#14). --}}
<x-analytics-meta :tenant="$currentTenant ?? null" />

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke mit
     und bekommt darum das Nonce des Requests. --}}
@if (!empty(config('app.tracking_scripts')))
    {!! \App\Services\Security\CspNonce::inject(config('app.tracking_scripts')) !!}
@endif
