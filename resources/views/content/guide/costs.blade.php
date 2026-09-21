{{--
    Verlauf › Kosten (#16, design/guide-dashboard.md §7.3), nur Inhaber.
    Kennzahlen · Tagesdiagramm (ChartWidget) mit Tabelle · je Portal · je Thema.
    Alle Beträge aus llm_usage_logs (Präfix guide.).
--}}
@use('App\Guide\Support\Usd')
@use('App\Guide\Services\CostReport')
@php
    $report = $this->report();
    $totals = $report['totals'];
    $tz = config('guide.timezone');
    $asOf = \Illuminate\Support\Carbon::parse($report['generated_at'])->timezone($tz)->format('H:i');
    $tiles = [
        ['label' => __('Heute'), 'value' => Usd::format($totals['today']), 'sub' => $totals['budget_day'] > 0 ? __('von :budget', ['budget' => Usd::format($totals['budget_day'])]) : null],
        ['label' => __('Dieser Monat'), 'value' => Usd::format($totals['month']), 'sub' => $totals['budget_month'] > 0 ? __('von :budget', ['budget' => Usd::format($totals['budget_month'])]) : null],
        ['label' => __('Prognose Monatsende'), 'value' => Usd::format($totals['forecast']), 'sub' => __('lineare Hochrechnung')],
        ['label' => __('Web-Suchen'), 'value' => number_format($totals['searches_today'], 0, ',', '.').' '.__('heute'), 'sub' => __(':count im Monat', ['count' => number_format($totals['searches_month'], 0, ',', '.')])],
        ['label' => __('⌀ je Lauf'), 'value' => $totals['runs_month'] > 0 ? Usd::format($totals['month'] / $totals['runs_month']) : '–', 'sub' => __('Modell :model', ['model' => Usd::format((float) config('guide.estimates.check_usd'))]).' '.__('je Prüfung')],
    ];
@endphp
<x-filament-panels::page>
    <p class="content-asof">{{ __('Stand :time · Quelle: Kostenbuchung je Aufruf', ['time' => $asOf]) }}</p>

    <div class="grid grid-cols-1 gap-content-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($tiles as $tile)
            <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                <h2 class="text-content-label font-medium uppercase tracking-wide text-text-muted">{{ $tile['label'] }}</h2>
                <p class="mt-content-2 text-[1.75rem] font-semibold tabular-nums text-text-strong">{{ $tile['value'] }}</p>
                @if ($tile['sub'])
                    <p class="text-content-table text-text-base">{{ $tile['sub'] }}</p>
                @endif
            </section>
        @endforeach
    </div>

    @livewire($this->chartWidget(), [], key('guide-costs-chart'))

    <details class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
        <summary class="cursor-pointer text-content-table font-medium text-text-base">{{ __('Als Tabelle zeigen') }}</summary>
        <div class="mt-content-3 overflow-x-auto">
            <table class="w-full text-content-table">
                <thead>
                    <tr class="border-b border-line-soft text-content-label text-text-muted">
                        <th class="py-content-2 text-start font-medium">{{ __('Tag') }}</th>
                        @foreach (['research', 'writing', 'quality'] as $kind)
                            <th class="py-content-2 text-end font-medium">{{ CostReport::kindLabel($kind) }}</th>
                        @endforeach
                        <th class="py-content-2 text-end font-medium">{{ __('Summe') }}</th>
                        <th class="py-content-2 text-end font-medium">{{ __('Suchen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft text-text-base">
                    @foreach (array_reverse($report['days'], true) as $date => $day)
                        <tr>
                            <td class="py-content-1">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') }}</td>
                            @foreach (['research', 'writing', 'quality'] as $kind)
                                <td class="py-content-1 text-end tabular-nums">{{ Usd::format($day[$kind]) }}</td>
                            @endforeach
                            <td class="py-content-1 text-end font-medium tabular-nums">{{ Usd::format($day['research'] + $day['writing'] + $day['quality'] + $day['other']) }}</td>
                            <td class="py-content-1 text-end tabular-nums">{{ $day['searches'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">{{ __('Je Portal (dieser Monat)') }}</h2>
        @if ($report['tenants'] === [])
            <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('In diesem Monat sind noch keine Kosten angefallen.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-soft text-content-label text-text-muted">
                            <th class="px-content-6 py-content-2 text-start font-medium">Portal</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Kosten') }}</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Anteil') }}</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Läufe') }}</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Aufrufe') }}</th>
                            <th class="px-content-6 py-content-2 text-end font-medium">{{ __('Suchen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft text-text-base">
                        @foreach ($report['tenants'] as $row)
                            <tr>
                                <td class="px-content-6 py-content-2 font-medium text-text-strong">{{ $row['name'] }}</td>
                                <td class="py-content-2 text-end tabular-nums">{{ Usd::format($row['cost']) }}</td>
                                <td class="py-content-2 text-end tabular-nums">{{ number_format($row['share'] * 100, 1, ',', '.') }} %</td>
                                <td class="py-content-2 text-end tabular-nums">{{ $row['runs'] }}</td>
                                <td class="py-content-2 text-end tabular-nums">{{ $row['calls'] }}</td>
                                <td class="px-content-6 py-content-2 text-end tabular-nums">{{ $row['searches'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">{{ __('Teuerste Themen (dieser Monat)') }}</h2>
        @if ($report['topics'] === [])
            <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('Noch keine Kosten je Thema gebucht.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-soft text-content-label text-text-muted">
                            <th class="px-content-6 py-content-2 text-start font-medium">{{ __('Thema') }}</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Kosten') }}</th>
                            <th class="py-content-2 text-end font-medium">{{ __('Läufe') }}</th>
                            <th class="px-content-6 py-content-2 text-end font-medium">{{ __('Suchen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft text-text-base">
                        @foreach ($report['topics'] as $row)
                            <tr>
                                <td class="px-content-6 py-content-2">
                                    <a href="{{ \App\Guide\Filament\Resources\TopicResource::detailUrl($row['topic_key']) }}" class="text-text-strong hover:underline">{{ $row['question'] }}</a>
                                    @if ($this->isNetworkWide())
                                        <span class="block text-content-label text-text-muted">{{ $row['tenant_name'] }}</span>
                                    @endif
                                </td>
                                <td class="py-content-2 text-end tabular-nums">{{ Usd::format($row['cost']) }}</td>
                                <td class="py-content-2 text-end tabular-nums">{{ $row['runs'] }}</td>
                                <td class="px-content-6 py-content-2 text-end tabular-nums">{{ $row['searches'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-panels::page>
