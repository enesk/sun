{{-- GA4 und Tag Manager wie im Default-Theme. GA4 laeuft im Consent Mode v2
     (Advanced) und steht darum fest im <head>: Consent-Default 'denied', dann
     gtag.js. Nur der Tag Manager laedt verzoegert nach dem Seitenaufbau.
     Rangfolge und Markup stecken in <x-analytics-meta> (#14). --}}
<x-analytics-meta :tenant="$currentTenant ?? null" :defer="1500" />

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke
     mit (Nonce des Requests) und laeuft erst nach Einwilligung (#28). --}}
<x-tracking-scripts />
