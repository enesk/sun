{{-- Fremdes Tracking-HTML aus der Verwaltung (config('app.tracking_scripts')) —
     erst nach Einwilligung (#28).

     Die einzige Stelle, die diesen Block ausgibt; eingebunden in beiden
     analytics-Partials (sun-v2 und die SaaSykit-Seiten). Das Nonce aus
     CspNonce loest nur die CSP-Frage, die Kategorie und den Zustand liefert
     App\Analytics\TrackingScriptsConsent:

       granted  direkt ausgeben wie bisher
       pending  inert im <template data-consent-script="…">; nachgezogen von
                resources/js/consent-scripts.js, sobald die Einwilligung im
                laufenden Besuch gemeldet wird
       denied   gar nichts ausgeben

     Reserviert wird hier bewusst kein Platz: Tracking-Schnipsel sind
     unsichtbar (Pixel, Behaelter), es gibt nichts, was springen koennte. --}}
@php
    $trackingHtml = \App\Services\Security\CspNonce::inject(config('app.tracking_scripts'));
    $trackingKategorie = \App\Analytics\TrackingScriptsConsent::category();
    $trackingErlaubt = \App\Analytics\TrackingScriptsConsent::granted();
    $trackingAbgelehnt = \App\Analytics\TrackingScriptsConsent::denied();
@endphp

@if ($trackingHtml !== '' && ! $trackingAbgelehnt)
    @if ($trackingErlaubt)
        {!! $trackingHtml !!}
    @else
        <template data-consent-script="{{ $trackingKategorie }}">{!! $trackingHtml !!}</template>
    @endif
@endif
