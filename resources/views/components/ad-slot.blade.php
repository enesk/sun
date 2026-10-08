@foreach($slots as $slot)
    @if($position === 'auto_ads')
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
    @else
        @php
            $deviceClasses = \App\View\Components\AdSlot::deviceClasses($slot->device_visibility ?? []);
            $clsClasses = \App\View\Components\AdSlot::clsContainerClasses($position);
            $hasCode = !empty(trim($slot->code ?? ''));
            $isLazy = \App\View\Components\AdSlot::isLazy($position);
        @endphp
        <div class="{{ collect([$deviceClasses, $clsClasses, 'bg-base-200/30'])->filter()->implode(' ') }}"
            @if($isLazy && $hasCode) data-lazy-ad @endif>
            @if($isLazy && $hasCode)
                <template data-ad-code>
                    {!! \App\Services\Security\CspNonce::inject($slot->code) !!}
                </template>
            @else
                {!! \App\Services\Security\CspNonce::inject($slot->code) !!}
            @endif
        </div>
    @endif
@endforeach
