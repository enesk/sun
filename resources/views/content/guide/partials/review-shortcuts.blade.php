{{--
    Übersicht der Tastenkürzel im Prüfblatt (#33, design/guide-dashboard.md §8.2).
    Erwartet: $keys (Taste => Beschriftung), $enabled (Schalter im Nutzermenü).
--}}
<div class="flex flex-col gap-content-3 text-content-table text-text-base">
    <dl class="grid grid-cols-[3rem_1fr] gap-x-content-3 gap-y-content-2">
        @foreach ($keys as $key => $label)
            <dt><kbd class="inline-flex min-w-7 justify-center rounded-content-sm border border-line-strong bg-surface-sunken px-content-2 font-mono text-text-strong">{{ $key }}</kbd></dt>
            <dd class="self-center">{{ $label }}</dd>
        @endforeach
    </dl>
    <p>{{ __('Die Tasten wirken nur im Prüfblatt und nie in Eingabefeldern.') }}</p>
    <p>
        {{ $enabled
            ? __('Einzeltasten sind eingeschaltet. Abschalten im Nutzermenü oben rechts: „Einzeltasten abschalten“.')
            : __('Einzeltasten sind abgeschaltet. Einschalten im Nutzermenü oben rechts: „Einzeltasten einschalten“.') }}
    </p>
</div>
