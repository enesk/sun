{{--
    Gestapelter Tagesbalken (design/guide-dashboard.md §3.2, §3.4).
    Erwartet: $counts (Segment => Zahl, Reihenfolge RunOverviewService::SEGMENTS),
    $label (aria-label), optional $height ('h-4' Kachel, 'h-2' Tabelle).
    Segmente unter 0,5 % behalten 4 px Mindestbreite; die Aussage steht in
    Legende und aria-label, nie nur in der Farbe.
--}}
@php
    $fills = [
        'neu' => 'var(--color-run-new-fill)',
        'aktualisiert' => 'var(--color-run-updated-fill)',
        'unveraendert' => 'var(--color-run-unchanged-fill)',
        'pruefung' => 'var(--color-run-review-fill)',
        'fehlgeschlagen' => 'var(--color-run-failed-fill)',
        'in-arbeit' => 'var(--color-run-active-fill)',
        'offen' => 'var(--color-run-open-fill)',
    ];
    $widths = \App\Guide\Filament\Pages\DailyRunMonitor::segmentWidths($counts);
@endphp
<div
    role="img"
    aria-label="{{ $label }}"
    class="flex w-full gap-0.5 overflow-hidden rounded-content-sm {{ $height ?? 'h-4' }}"
    style="background: var(--color-run-open-fill)"
>
    @foreach ($fills as $segment => $fill)
        @if (($counts[$segment] ?? 0) > 0)
            <span
                @class(['block h-full', 'content-run-segment--new' => $segment === 'neu'])
                style="flex: {{ $widths[$segment] }} 0 0; min-width: 4px; @if ($segment !== 'neu') background: {{ $fill }}; @endif"
            ></span>
        @endif
    @endforeach
</div>
