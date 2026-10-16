{{--
    Werbeplatz-Anker (SUN-AD-004, Ticket #5) — Markup zu
    App\View\Components\Sun\AdSlot.

    Der HTML-Kommentar steht **immer** da, auch ohne Belegung: der
    Editor-Modus (#6) findet daran alle Positionen einer Seite, und ein
    Kommentar hat keine Geometrie — ohne Belegung aendert sich das Layout
    gegenueber heute also nicht.

    Die Mindesthoehen gehen als CSS-Variablen in den Container, nicht als
    Tailwind-Klassen: `min-h-[280px]` muesste zur Bauzeit im Quelltext stehen,
    die Werte stehen aber im Katalog (config/ad-slots.php). Die Regeln dazu
    stehen in resources/views/themes/sun-v2/css/app.css (.ad-slot__area).

    Das Fragment liegt in einem <template>: resources/js/ads.js setzt es ein,
    sobald der Container sichtbar in den Viewport kommt. Damit laedt die
    mobile Fassung nie auf dem Desktop (display:none schneidet den
    IntersectionObserver ab) und unter dem Falz laedt nichts im Voraus.

    Einwilligung (#9): braucht das Werbemittel eine, steht am Container
    `data-ad-consent` und ads.js laesst das <template> liegen, bis
    resources/js/consent-adapter.js die Zustimmung meldet. Bis dahin steht nur
    die Reservierung in Katalog-Hoehe mit der Zeile "Anzeige" — kein Request
    an Google, und nach der Einwilligung springt nichts. Auch das Head-Skript
    (AdSense-Loader) wartet: es steht dann in einem <template data-ad-head>,
    das ads.js nach der Einwilligung in ein echtes <script> umsetzt. Ein
    <template> ist inert, der Browser laedt daraus nichts.

    Im Editor-Modus (#6) steht an derselben Stelle statt Anzeigenflaeche und
    Fragment der Anker-Rahmen: ein <button> je Container, beschriftet mit
    Label, Schluessel, Geraet und Zustand. Geladen wird dort nichts — kein
    Werbemittel, kein Loader. Markup: components/sun/ad-slot-editor-frame.
--}}
<!-- {{ \App\View\Components\Sun\AdSlot::ANCHOR_PREFIX }}{{ $key }} -->
@if($headScripts !== [])
    @push('head')
        @foreach($headScripts as $dependency)
            @if(\App\View\Components\Sun\AdSlot::deferredUntilConsent($dependency))
            <template data-ad-head>{!! \App\View\Components\Sun\AdSlot::scriptTag($dependency) !!}</template>
            @else
            {!! \App\View\Components\Sun\AdSlot::scriptTag($dependency) !!}
            @endif
        @endforeach
    @endpush
@endif
@if($containers !== [])
@if($wrap !== '')<div class="{{ $wrap }}">@endif
@foreach($containers as $container)
    <div @class([
            'ad-slot',
            'ad-slot--editor' => $editorMode,
            'ad-slot--collapsed' => $container['collapsed'],
            $container['visibility'],
        ])
        data-ad-anchor="{{ $key }}"
        data-ad-device="{{ $container['device'] }}"
        @if($container['needsConsent']) data-ad-consent="marketing" data-ad-on-denied="{{ \App\View\Components\Sun\AdSlot::onDenied() }}" @endif
        {{-- Impression-Zaehlen (#12): nur mit Belegung, nie im Editor-Modus.
             Ohne diese beiden Attribute zaehlt resources/js/ads.js nichts. --}}
        @if($trackImpressions && $container['placementId'] !== null) data-ad-placement="{{ $container['placementId'] }}" data-ad-track="{{ \App\View\Components\Sun\AdSlot::trackAttribute() }}" @endif
        @if($eager) data-ad-eager @endif>
        {{-- Im Editor-Modus steht die Zeile "Anzeige" nur ueber einem belegten
             Platz: dort hat sie die ausgelieferte Seite auch, und der Rahmen
             bekommt damit genau deren Hoehe. Ueber einem freien Platz waere
             sie eine falsche Auskunft. --}}
        @if(! $editorMode || $container['placementId'] !== null)
        <span class="block px-3 pt-2 text-xs text-zinc-400">{{ __('portal.layout.ad_label') }}</span>
        @endif
        @if($editorMode)
            @include('components.sun.ad-slot-editor-frame', ['key' => $key, 'container' => $container])
        @else
        <div class="ad-slot__area" style="--ad-mh:{{ $container['minHeightMobile'] }}px;--ad-mh-lg:{{ $container['minHeightDesktop'] }}px">
            @if($container['html'] !== null)
                <template data-ad-fragment>{!! $container['html'] !!}</template>
            @endif
        </div>
        @endif
    </div>
@endforeach
@if($wrap !== '')</div>@endif
@endif
