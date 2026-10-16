{{-- GA4 und Tag Manager der SaaSykit-Seiten (kein Portal-Theme): Skript laedt
     sofort. Rangfolge und Markup stecken in <x-analytics-meta> (#14). --}}
<x-analytics-meta :tenant="$currentTenant ?? null" />

{{-- Fremdes Tracking-HTML aus der Verwaltung: bringt eigene <script>-Bloecke
     mit (Nonce des Requests) und laeuft erst nach Einwilligung (#28). --}}
<x-tracking-scripts />
