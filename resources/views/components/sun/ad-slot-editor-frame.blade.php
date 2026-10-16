{{--
    Anker-Rahmen im Editor-Modus (Ticket #6, docs/ad-slots.md 6).

    Eingebunden von components/sun/ad-slot.blade.php, einmal je Container.
    Erwartet: $key und $container (die Beschriftung steckt darin — `$slot` ist in
    einer Komponenten-View der Blade-ComponentSlot, nicht die Katalog-Zeile).

    Ein <button>, kein <div> mit Klick-Handler: der Rahmen ist mit der Tastatur
    erreichbar und loest mit Enter/Space aus. Beschriftung und Zustand stehen
    als data-Attribute daran — das Modul
    resources/views/themes/sun-v2/js/ad-editor.js liest sie fuer `slots:ready`
    und schreibt sie bei `placement:updated` zurueck, ohne Reload.

    Die Mindesthoehe sitzt wie im Normalfall auf der Flaeche (.ad-slot__area,
    CSS-Variablen aus dem Katalog), die Beschriftung liegt **darin** — sonst
    waere der Rahmen im Editor hoeher als der echte Platz und die Vorschau
    zeigte ein anderes Layout als der Besucher sieht (Akzeptanz 6).
--}}
@php
    $belegt = $container['placementId'] !== null;
    $geraet = \App\Ads\Enums\AdDevice::tryFrom($container['device']) ?? \App\Ads\Enums\AdDevice::ALL;
    $beschriftung = $belegt
        ? (string) $container['adUnitName']
        : __('portal.layout.ad_editor.empty');
@endphp
<button type="button"
    @class([
        'ad-slot__area block w-full cursor-pointer rounded-2xl border-2 text-left transition',
        'px-3 py-2',
        'data-[ad-editor-highlight]:ring-4 data-[ad-editor-highlight]:ring-amber-400',
        'border-emerald-500 bg-emerald-50/60 hover:bg-emerald-50' => $belegt,
        'border-dashed border-zinc-400 bg-white/60 hover:bg-white' => ! $belegt,
    ])
    style="--ad-mh:{{ $container['minHeightMobile'] }}px;--ad-mh-lg:{{ $container['minHeightDesktop'] }}px"
    data-ad-editor-frame
    data-ad-slot-key="{{ $key }}"
    data-ad-slot-device="{{ $container['device'] }}"
    data-ad-slot-label="{{ $container['label'] }}"
    data-ad-slot-filled="{{ $belegt ? '1' : '0' }}"
    data-ad-slot-placement="{{ $container['placementId'] }}"
    data-ad-slot-min-height="{{ $container['device'] === 'desktop' ? $container['minHeightDesktop'] : $container['minHeightMobile'] }}">
    <span class="block text-xs font-semibold leading-snug text-zinc-700">{{ $container['label'] }}</span>
    <span class="mt-0.5 block font-mono text-[11px] leading-snug break-all text-zinc-500">{{ $key }}</span>
    <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] leading-snug">
        <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-zinc-600">{{ $geraet->label() }}</span>
        <span @class([
            'rounded px-1.5 py-0.5',
            'bg-emerald-600 text-white' => $belegt,
            'bg-white text-zinc-500 ring-1 ring-zinc-300' => ! $belegt,
        ]) data-ad-slot-state data-ad-empty-label="{{ __('portal.layout.ad_editor.empty') }}">{{ $beschriftung }}</span>
    </span>
</button>
