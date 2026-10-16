{{--
    Vorschau eines Werbemittels im Admin-Panel (#10).

    AdSense wird hier bewusst NICHT geladen: eine Impression auf einer
    Verwaltungsseite ist bei Google ein ungueltiger Vorfall
    (docs/ad-slots.md 6). Statt des Anzeigenblocks steht der Platzhalter mit
    Anzeigenblock-ID und Format.

    Eigenes HTML laeuft in einem <iframe sandbox> ohne allow-same-origin und
    ueber eine eigene Route — nie als srcdoc und nie im DOM des Panels, sonst
    erbte fremdes Markup die CSP des Panels (docs/ad-slots.md 5.2, Regel 3).

    Erwartete Variablen: provider, providerLabel, config, formatLabel,
    previewUrl, unsaved.
--}}
@php
    $slotId = $config['slot_id'] ?? null;
    $width = (int) ($config['width'] ?? 0);
    $height = (int) ($config['height'] ?? 0);
@endphp

<div class="space-y-3 text-sm">
    @if ($provider === 'adsense')
        <div
            class="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center dark:border-gray-600 dark:bg-gray-900"
            role="img"
            aria-label="{{ __('Platzhalter des AdSense-Anzeigenblocks') }}"
        >
            <p class="font-medium text-gray-700 dark:text-gray-200">{{ $providerLabel }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ $slotId ? __('Anzeigenblock :id', ['id' => $slotId]) : __('noch keine Anzeigenblock-ID') }}
                @if ($formatLabel)
                    · {{ $formatLabel }}
                @endif
            </p>
        </div>
        <p class="text-gray-500 dark:text-gray-400">
            {{ __('AdSense wird in der Verwaltung nicht geladen. Die Publisher-ID kommt aus den Werbe-Einstellungen des Portals.') }}
        </p>
    @elseif ($previewUrl)
        <iframe
            src="{{ $previewUrl }}"
            sandbox="{{ \App\Ads\Providers\HtmlProvider::SANDBOX }}"
            @if ($width > 0) width="{{ $width }}" @endif
            @if ($height > 0) height="{{ $height }}" @endif
            style="{{ $width > 0 ? "width:{$width}px;" : 'width:100%;' }}{{ $height > 0 ? "height:{$height}px;" : 'height:250px;' }}max-width:100%;border:0;display:block"
            referrerpolicy="no-referrer"
            scrolling="no"
            title="{{ __('Vorschau des Werbemittels') }}"
            class="rounded-lg border border-gray-200 dark:border-gray-700"
        ></iframe>
        <p class="text-gray-500 dark:text-gray-400">
            {{ __('Der Rahmen zeigt den gespeicherten Stand; Änderungen am Markup erscheinen nach dem Speichern.') }}
        </p>
    @else
        <p class="text-gray-500 dark:text-gray-400">
            @if ($unsaved)
                {{ __('Die Vorschau erscheint, sobald das Werbemittel gespeichert ist.') }}
            @else
                {{ __('Für dieses Werbemittel gibt es nichts zu zeigen — es fehlt das HTML.') }}
            @endif
        </p>
    @endif
</div>
