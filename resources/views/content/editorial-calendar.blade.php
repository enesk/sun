{{--
    Redaktionskalender (#19), design/content-dashboard.md, §3.

    Monat: Zellhoehe fest, Zaehlerstand oben rechts, darunter hoechstens drei
    gestapelte Statusbalken — keine Titelliste, die bei 48 Artikeln am Tag
    ohnehin unlesbar waere. Woche: Zeitachse mit einzelnen Bloecken; nur hier
    laesst sich ein eingeplanter Artikel per Ziehen und Ablegen verschieben.
    Veroeffentlichte Artikel sind fixiert (`movable` ist dort false).
--}}
@php
    use App\Content\Enums\DisplayStatus;

    $barOrder = DisplayStatus::board();
@endphp

<div
    x-data="{ dragging: null }"
    class="pb-content-8"
>
    @include('content.partials.filter-bar', [
        'meta' => __('Stand :time Uhr', ['time' => now()->format('H:i')]),
    ])

    <div class="mb-content-4 flex flex-wrap items-center gap-content-3">
        <div class="flex items-center gap-content-1 rounded-content-md border border-line-strong bg-surface-card p-content-1">
            @foreach ([\App\Content\Livewire\EditorialCalendar::VIEW_MONTH => __('Monat'), \App\Content\Livewire\EditorialCalendar::VIEW_WEEK => __('Woche')] as $value => $label)
                <button
                    type="button"
                    wire:click="switchTo('{{ $value }}')"
                    @class([
                        'h-8 rounded-content-sm px-content-3 text-content-table font-medium',
                        'bg-content-600 text-white' => $mode === $value,
                        'text-text-base' => $mode !== $value,
                    ])
                    aria-pressed="{{ $mode === $value ? 'true' : 'false' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-content-1">
            <button type="button" wire:click="previous" class="h-8 rounded-content-md border border-line-strong bg-surface-card px-content-3 text-content-table" aria-label="{{ __('Zurück') }}">‹</button>
            <button type="button" wire:click="today" class="h-8 rounded-content-md border border-line-strong bg-surface-card px-content-3 text-content-table">{{ __('Heute') }}</button>
            <button type="button" wire:click="next" class="h-8 rounded-content-md border border-line-strong bg-surface-card px-content-3 text-content-table" aria-label="{{ __('Weiter') }}">›</button>
        </div>

        <h2 class="text-content-h2 font-semibold text-text-strong">
            {{ $mode === \App\Content\Livewire\EditorialCalendar::VIEW_WEEK
                ? __('Woche ab :date', ['date' => $anchorDate->startOfWeek()->translatedFormat('d. F Y')])
                : $anchorDate->translatedFormat('F Y') }}
        </h2>

        @if ($mode === \App\Content\Livewire\EditorialCalendar::VIEW_MONTH && $monthTarget > 0)
            <p class="ms-auto text-content-table text-text-muted">
                {{ __(':month: :published von :target Artikeln', [
                    'month' => $anchorDate->translatedFormat('F'),
                    'published' => number_format($monthPublished, 0, ',', '.'),
                    'target' => number_format($monthTarget, 0, ',', '.'),
                ]) }}
            </p>
        @endif
    </div>

    @if ($mode === \App\Content\Livewire\EditorialCalendar::VIEW_MONTH)
        <div class="overflow-hidden rounded-content-lg border border-line-strong bg-surface-card">
            <div class="grid grid-cols-7 border-b border-line-soft">
                @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday)
                    <div class="p-content-2 text-content-label font-medium text-text-muted">{{ $weekday }}</div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="grid grid-cols-7">
                    @foreach ($week as $day)
                        @php
                            $share = $day['target'] > 0 ? $day['published'] / $day['target'] : 0;
                            $level = $share >= 1 ? 'good' : ($share >= 0.5 ? 'mid' : 'poor');
                            $isPast = $day['date']->isPast() && ! $day['is_today'];
                        @endphp

                        <button
                            type="button"
                            wire:key="day-{{ $day['key'] }}"
                            wire:click="mountAction('day', {{ \Illuminate\Support\Js::from(['date' => $day['key']]) }})"
                            @class([
                                'h-[132px] border-b border-e border-line-soft p-content-2 text-start align-top',
                                'opacity-50' => ! $day['in_month'],
                                'border-2 border-content-600' => $day['is_today'],
                            ])
                            @if ($isPast && $day['total'] === 0 && $day['target'] > 0)
                                style="background: var(--color-status-failed-bg)"
                            @endif
                        >
                            <span class="flex items-baseline justify-between">
                                <span class="text-content-table font-medium text-text-strong">{{ $day['date']->day }}</span>

                                @if ($day['target'] > 0)
                                    <span class="text-content-label font-semibold" style="color: var(--color-score-{{ $level }})">
                                        {{ $day['published'] }}/{{ $day['target'] }}
                                    </span>
                                @endif
                            </span>

                            <span class="mt-content-2 block space-y-content-1">
                                @foreach ($barOrder as $status)
                                    @php $count = $day['counts'][$status->value] ?? 0; @endphp

                                    @if ($count > 0)
                                        <span
                                            class="block h-1.5 rounded-content-sm"
                                            style="width: {{ max(8, (int) round(($count / max(1, $day['total'])) * 100)) }}%; background: var(--color-status-{{ $status->value }}-fill)"
                                            title="{{ $status->label() }}: {{ $count }}"
                                        ></span>
                                    @endif
                                @endforeach
                            </span>

                            @if ($day['total'] === 0 && $day['date']->isFuture() && $day['target'] > 0)
                                <span class="mt-content-2 block text-content-label text-text-muted">
                                    {{ __('geplant: :count', ['count' => $day['target']]) }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>

        <p class="mt-content-2 text-content-label text-text-muted">
            {{ __('Termine verschieben: in der Wochenansicht per Ziehen und Ablegen oder im Tagesblatt über „Termin ändern".') }}
        </p>
    @else
        <div class="overflow-x-auto rounded-content-lg border border-line-strong bg-surface-card">
            <div class="grid min-w-[900px]" style="grid-template-columns: 64px repeat(7, minmax(0, 1fr))">
                <div class="border-b border-line-soft p-content-2"></div>

                @foreach ($weeks[0] as $day)
                    <div @class([
                        'border-b border-s border-line-soft p-content-2 text-content-label font-medium',
                        'text-content-700' => $day['is_today'],
                        'text-text-muted' => ! $day['is_today'],
                    ])>
                        {{ $day['date']->translatedFormat('D d.m.') }}
                    </div>
                @endforeach

                @foreach ($hours as $hour)
                    <div class="border-b border-line-soft p-content-2 text-content-label text-text-muted">
                        {{ sprintf('%02d:00', $hour) }}
                    </div>

                    @foreach ($weeks[0] as $day)
                        <div
                            wire:key="slot-{{ $day['key'] }}-{{ $hour }}"
                            class="min-h-14 border-b border-s border-line-soft p-content-1"
                            x-on:dragover.prevent
                            x-on:drop.prevent="if (dragging) { $wire.moveCard(dragging, '{{ $day['key'] }}', {{ $hour }}); dragging = null }"
                        >
                            @foreach ($blocks[$day['key']][$hour] ?? [] as $card)
                                <button
                                    type="button"
                                    wire:key="block-{{ $card['key'] }}"
                                    wire:click="mountAction('reschedule', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                                    @if ($card['movable'])
                                        draggable="true"
                                        x-on:dragstart="dragging = '{{ $card['key'] }}'"
                                        x-on:dragend="dragging = null"
                                    @endif
                                    @class([
                                        'mb-content-1 block w-full rounded-content-sm p-content-1 text-start text-content-label',
                                        'cursor-move' => $card['movable'],
                                        'cursor-default' => ! $card['movable'],
                                    ])
                                    style="background: var(--color-status-{{ $card['status'] }}-bg); color: var(--color-status-{{ $card['status'] }}-fg)"
                                    title="{{ $card['tenant'] }} · {{ $card['status_label'] }}{{ $card['movable'] ? '' : ' · '.__('fixiert') }}"
                                    @disabled(! $card['movable'])
                                >
                                    <span class="block truncate font-semibold">{{ $card['time'] }} {{ $card['title'] }}</span>
                                    <span class="block truncate">{{ $card['tenant'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <p class="mt-content-2 text-content-label text-text-muted">
            {{ __('Nur eingeplante Artikel lassen sich ziehen; veröffentlichte Artikel sind fixiert.') }}
        </p>
    @endif

    <div wire:loading class="mt-content-2 text-content-label text-text-muted">
        {{ __('Kalender wird geladen …') }}
    </div>

    <x-filament-actions::modals />
</div>
