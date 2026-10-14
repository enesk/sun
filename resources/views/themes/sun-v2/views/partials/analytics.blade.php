{{-- GA4 und Tag Manager wie im Default-Theme, das Skript (~170 KB) laedt aber
     erst nach dem Seitenaufbau: Aufrufe landen vorher in dataLayer und gehen
     danach mit, der LCP wartet nicht darauf.
     Rangfolge und Markup stecken in <x-analytics-meta> (#14). --}}
<x-analytics-meta :tenant="$currentTenant ?? null" :defer="1500" />

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke mit
     und bekommt darum das Nonce des Requests. --}}
@if (!empty(config('app.tracking_scripts')))
    {!! \App\Services\Security\CspNonce::inject(config('app.tracking_scripts')) !!}
@endif
