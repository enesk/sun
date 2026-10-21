{{--
    Werbeplaetze des Altsystems (Tabelle ad_slots, Ansprache ueber `position`).
    Laeuft bis Ticket #11 parallel zum Katalog v2 (<x-sun.ad-slot>).

    Einwilligung (#9): Der eingetragene Code ist AdSense — er laedt erst nach
    der Einwilligung in Marketing-Cookies. Bis dahin steht nur der
    CLS-Container in seiner reservierten Groesse, der Code liegt in einem
    <template data-ad-code> und der Container traegt `data-ad-consent`.
    resources/js/ads.js setzt ihn ein, sobald resources/js/consent-adapter.js
    die Zustimmung meldet — ohne Reload, und weil der Platz reserviert ist,
    springt dabei nichts. Ein <template> ist inert: vorher geht kein Request
    an Google.

    Wurde ausdruecklich abgelehnt, rendert die Komponente gar nicht
    (App\View\Components\AdSlot::shouldRender(), `on_denied = collapse`).
--}}
@php
    $consentGranted = \App\Ads\Support\AdConsent::granted();
@endphp
@foreach($slots as $slot)
    @if($position === 'auto_ads')
        {{-- Auto Ads nur mit Einwilligung, und bewusst ohne Nachziehen im
             laufenden Besuch: sie platzieren ausserhalb jeder Reservierung
             und wuerden das Layout nach dem Klick verschieben. Beim naechsten
             Seitenaufruf laufen sie. --}}
        @if($consentGranted)
            {{-- Kein CLS-Container: das Skript fuegt ausserhalb ein (Vorgabe #100, 3.2).
                 Der Code kommt aus der Verwaltung und bringt eigene <script>-Bloecke
                 mit — darum das Nonce des Requests statt 'unsafe-inline' (#21). --}}
            {!! \App\Services\Security\CspNonce::inject($slot->code) !!}
            @php
                $autoAdsPublisherId = \App\View\Components\AdSlot::publisherIdFrom($slot->code);
            @endphp
            @if($autoAdsPublisherId)
                @once
                    {{-- Seitenweiser Opt-out fuer den unteren Overlay/Anker-Banner.
                         Ausgefuehrt in resources/js/ads.js. --}}
                    <meta name="adsense-auto-client" content="{{ $autoAdsPublisherId }}">
                @endonce
            @endif
        @endif
    @else
        @php
            $deviceClasses = \App\View\Components\AdSlot::deviceClasses($slot->device_visibility ?? []);
            $clsClasses = \App\View\Components\AdSlot::clsContainerClasses($position);
            $hasCode = !empty(trim($slot->code ?? ''));
            $isLazy = \App\View\Components\AdSlot::isLazy($position);
            // Zurueckgehalten wird in zwei Faellen: unterhalb des Falzes
            // (Nachladen, wie bisher) und ohne Einwilligung (#9). Beides
            // laeuft ueber denselben Weg — <template> plus data-lazy-ad.
            $deferred = $isLazy || ! $consentGranted;
        @endphp
        <div class="{{ collect([$deviceClasses, $clsClasses])->filter()->implode(' ') }}"
            @if($deferred && $hasCode) data-lazy-ad @endif
            @if(! $consentGranted && $hasCode) data-ad-consent="marketing" data-ad-on-denied="{{ \App\Ads\Support\AdConsent::onDeniedAttribute() }}" @endif>
            @if($deferred && $hasCode)
                <template data-ad-code>
                    {!! \App\Services\Security\CspNonce::inject($slot->code) !!}
                </template>
            @else
                {!! \App\Services\Security\CspNonce::inject($slot->code) !!}
            @endif
        </div>
    @endif
@endforeach
