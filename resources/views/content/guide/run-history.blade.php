{{--
    Verlauf › Läufe (#33, design/guide-dashboard.md §7.1).
    Zeitraum-Filter (Heute · 7 · 30 Tage · Frei) und Laufergebnis, darunter
    ein Kleinbalken je Tag der letzten 14 Tage (Klick setzt den Zeitraum auf
    den Tag), dann die Tabelle aller Läufe, 50 je Seite.
--}}
@use('App\Guide\Enums\RunDisplay')
@use('App\Guide\Filament\Pages\DailyRunMonitor')
@use('App\Guide\Filament\Pages\RunHistory')
@use('App\Guide\Filament\Resources\TopicResource')
@use('App\Guide\Support\Usd')
@php
    $tz = config('guide.timezone');
    $runs = $this->runs();
    $days = $this->days();
    $lastPage = $this->lastPage();
    $maxTotal = max(1, collect($days)->max('total'));
@endphp
<x-filament-panels::page>
    {{-- Filterbalken: Zeitraum links davor (§1.3) --}}
    <div class="flex flex-wrap items-end gap-content-4">
        <div role="radiogroup" aria-label="{{ __('Zeitraum') }}" class="inline-flex overflow-hidden rounded-content-sm border border-line-strong bg-surface-card text-content-table">
            @foreach (RunHistory::PERIODS as $value => $label)
                <button
                    type="button"
                    role="radio"
                    aria-checked="{{ $this->period === (string) $value ? 'true' : 'false' }}"
                    wire:click="$set('period', '{{ $value }}')"
                    @class([
                        'min-h-9 px-content-3 font-medium',
                        'bg-content-600 text-white' => $this->period === (string) $value,
                        'text-text-base hover:bg-surface-sunken' => $this->period !== (string) $value,
                    ])
                >{{ __($label) }}</button>
            @endforeach
        </div>

        @if ($this->period === 'frei')
            <label class="flex flex-col gap-content-1 text-content-label text-text-muted">
                {{ __('von') }}
                <input type="date" wire:model.live="from" class="rounded-content-sm border-line-strong text-content-table text-text-base" />
            </label>
            <label class="flex flex-col gap-content-1 text-content-label text-text-muted">
                {{ __('bis') }}
                <input type="date" wire:model.live="until" class="rounded-content-sm border-line-strong text-content-table text-text-base" />
            </label>
        @endif

        <label class="flex flex-col gap-content-1 text-content-label text-text-muted">
            {{ __('Laufergebnis') }}
            <select wire:model.live="display" class="rounded-content-sm border-line-strong text-content-table text-text-base">
                <option value="">{{ __('Alle') }}</option>
                @foreach (RunDisplay::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->isFiltered())
            <button type="button" wire:click="resetFilters" class="min-h-9 text-content-table font-medium text-content-700 underline underline-offset-2">{{ __('Zurücksetzen') }}</button>
        @endif
    </div>

    {{-- Kleinbalken je Tag, letzte 14 Tage --}}
    <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
        <h2 class="text-content-h3 font-semibold text-text-strong">{{ __('Letzte :days Tage', ['days' => RunHistory::BAR_DAYS]) }}</h2>
        <ul class="mt-content-3 flex flex-col gap-content-1">
            @foreach (array_reverse($days) as $day)
                @php($date = \Illuminate\Support\Carbon::parse($day['date'], $tz))
                <li wire:key="day-{{ $day['date'] }}">
                    <button
                        type="button"
                        wire:click="selectDay('{{ $day['date'] }}')"
                        @if ($this->isSelectedDay($day['date'])) aria-current="date" @endif
                        @class([
                            'grid w-full grid-cols-[6.5rem_1fr_4.5rem] items-center gap-content-3 rounded-content-sm px-content-2 py-content-1 text-start text-content-table hover:bg-surface-sunken',
                            'bg-surface-sunken' => $this->isSelectedDay($day['date']),
                        ])
                    >
                        <span class="text-text-base">{{ $date->translatedFormat('D, d.m.') }}</span>
                        <span class="block" style="width: {{ $day['total'] > 0 ? max(4, round($day['total'] / $maxTotal * 100, 1)) : 0 }}%">
                            @if ($day['total'] > 0)
                                @include('content.guide.partials.run-bar', [
                                    'counts' => $day['counts'],
                                    'label' => $date->format('d.m.').': '.DailyRunMonitor::barLabel($day['counts'], $day['checked'], $day['total']),
                                    'height' => 'h-2',
                                ])
                            @endif
                        </span>
                        <span class="text-end tabular-nums text-text-base">{{ $day['total'] > 0 ? $day['checked'].' / '.$day['total'] : '–' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Tabelle aller Läufe im Zeitraum --}}
    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">
            {{ __('Läufe') }}
            <span class="text-content-body font-normal text-text-muted">({{ number_format($runs['total'], 0, ',', '.') }})</span>
        </h2>

        @if ($runs['rows'] === [])
            <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('In diesem Zeitraum gab es keinen Lauf.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-soft text-content-label text-text-muted">
                            <th class="px-content-6 py-content-2 text-start font-medium">{{ __('Zeit') }}</th>
                            @if ($this->isNetworkWide())
                                <th class="py-content-2 pe-content-3 text-start font-medium">Portal</th>
                            @endif
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Thema') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Art') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Ergebnis') }}</th>
                            <th class="py-content-2 pe-content-3 text-end font-medium">{{ __('Dauer') }}</th>
                            @if ($this->canSeeCosts())
                                <th class="px-content-6 py-content-2 text-end font-medium">{{ __('Kosten') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft align-top text-text-base">
                        @foreach ($runs['rows'] as $row)
                            <tr wire:key="run-{{ $row['__key'] }}">
                                <td class="whitespace-nowrap px-content-6 py-content-3 tabular-nums">
                                    {{ $row['started_at'] ? \Illuminate\Support\Carbon::parse($row['started_at'])->timezone($tz)->format('d.m., H:i') : \Illuminate\Support\Carbon::parse($row['run_date'])->format('d.m.') }}
                                </td>
                                @if ($this->isNetworkWide())
                                    <td class="py-content-3 pe-content-3">{{ $row['tenant_name'] }}</td>
                                @endif
                                <td class="min-w-64 py-content-3 pe-content-3">
                                    <a href="{{ TopicResource::detailUrl($row['topic_key'], 'laeufe') }}" class="font-medium text-text-strong hover:underline">{{ $row['question'] ?: '–' }}</a>
                                </td>
                                <td class="whitespace-nowrap py-content-3 pe-content-3">
                                    {{ $row['mode'] === 'create' ? __('Neuanlage') : __('Aktualisierung') }}
                                </td>
                                <td class="py-content-3 pe-content-3">
                                    @include('content.guide.partials.run-status', ['run' => $row, 'replaceSource' => false])
                                </td>
                                <td class="whitespace-nowrap py-content-3 pe-content-3 text-end tabular-nums">{{ RunHistory::durationLabel($row['duration_seconds']) }}</td>
                                @if ($this->canSeeCosts())
                                    <td class="whitespace-nowrap px-content-6 py-content-3 text-end tabular-nums">{{ $row['cost'] > 0 ? Usd::format($row['cost']) : '–' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <nav class="flex items-center justify-between gap-content-3 border-t border-line-soft px-content-6 py-content-3 text-content-table" aria-label="{{ __('Seiten') }}">
                    <button type="button" wire:click="goToPage({{ $this->pageNumber - 1 }})" @disabled($this->pageNumber <= 1) class="min-h-9 font-medium text-content-700 disabled:text-text-muted">{{ __('Zurück') }}</button>
                    <span class="tabular-nums text-text-base">{{ __('Seite :page von :last', ['page' => $this->pageNumber, 'last' => $lastPage]) }}</span>
                    <button type="button" wire:click="goToPage({{ $this->pageNumber + 1 }})" @disabled($this->pageNumber >= $lastPage) class="min-h-9 font-medium text-content-700 disabled:text-text-muted">{{ __('Weiter') }}</button>
                </nav>
            @endif
        @endif
    </section>
</x-filament-panels::page>
