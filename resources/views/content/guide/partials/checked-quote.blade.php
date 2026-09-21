{{--
    Quote „geprüft / fällig“ (#38 G7, design/guide-dashboard.md §11.2).
    Parameter: $percent (?int, abgerundet; null bei due = 0), $warn (bool:
    Warnfarbe erlaubt, d. h. Tageslauf nicht mehr im Fenster), $variant
    ('tile' = Zusatz sichtbar, 'cell' = Zusatz nur für Screenreader).
--}}
@use('App\Guide\Support\CheckedQuote')
@if ($percent === null)
    <span class="tabular-nums text-text-muted">–</span>
@elseif ($warn && CheckedQuote::belowTarget($percent))
    <span class="inline-flex items-center gap-content-1 tabular-nums" style="color: var(--color-status-review-fg)">
        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-4 shrink-0" aria-hidden="true" />
        {{ CheckedQuote::label($percent) }}
        <span @class(['sr-only' => $variant === 'cell'])>{{ __('unter Ziel :target %', ['target' => CheckedQuote::TARGET_PERCENT]) }}</span>
    </span>
@else
    <span class="tabular-nums text-text-base">{{ CheckedQuote::label($percent) }}</span>
@endif
