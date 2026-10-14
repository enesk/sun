{{-- GA4 und Tag Manager: laufen mit granted consent, Skript laedt sofort.
     Rangfolge und Markup stecken in <x-analytics-meta> (#14). --}}
<x-analytics-meta :tenant="$currentTenant ?? null" />
