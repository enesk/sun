{{--
    Leistung, Reiter "Artikel" (#25), design/content-dashboard.md, §5.

    Sieben Spalten, 44 px Zeilenhoehe, Zahlen rechtsbuendig und tabellar,
    Standardsortierung nach Impressionen absteigend. Unter der Tabelle eine
    Summenzeile, danach Top 10, Flop 10 und die Refresh-Kandidaten.

    Erwartete Variablen: $snapshot, $list, $columns, $ariaSort, $arrow,
    $int, $dec.
--}}
@php
    $totals = $snapshot['totals'];
    $first = max(1, min($list['page'] - 3, $list['pages'] - 6));
    $last = min($list['pages'], max($list['page'] + 3, 7));
@endphp

<div class="space-y-content-8">
    @if ($list['total'] === 0)
        <div class="rounded-content-lg border border-dashed border-line-strong p-content-8 text-center">
            <p class="text-content-h3 font-semibold text-text-strong">{{ __('Noch keine Zahlen.') }}</p>
            <p class="mt-content-1 text-content-body text-text-muted">
                {{ __('Der Metrik-Collector holt die Zahlen täglich um :time. Search Console liefert erst einige Tage nach der Veröffentlichung.', [
                    'time' => config('content.pipeline.schedule.metrics_at', '06:30'),
                ]) }}
            </p>
        </div>
    @else
        {{-- Desktop: Tabelle --}}
        <div class="hidden overflow-hidden rounded-content-lg bg-surface-card lg:block">
            <table class="w-full table-fixed border-collapse text-content-table">
                <caption class="sr-only">
                    {{ __('Artikel mit Impressionen, Klicks, Position und Ertrag') }}
                </caption>
                <thead>
                    <tr class="bg-surface-sunken">
                        @foreach ($columns as $column)
                            {{-- text-base statt text-muted: die Kopfzeile steht auf
                                 surface-sunken, dort traegt text-muted nur 4,45:1 (#64). --}}
                            <th
                                scope="col"
                                aria-sort="{{ $ariaSort($column['key']) }}"
                                @class(['h-10 p-0 text-content-label font-medium text-text-base', $column['class']])
                            >
                                <button
                                    type="button"
                                    wire:click="sortBy('{{ $column['key'] }}')"
                                    @class([
                                        'flex h-10 w-full items-center gap-content-1 px-content-3',
                                        'justify-end' => $column['align'] === 'text-end',
                                    ])
                                >
                                    <span>{{ $column['label'] }}</span>
                                    @if ($column['key'] === $list['sort'])
                                        <span class="text-[12px] text-text-strong" aria-hidden="true">{{ $arrow }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($list['rows'] as $row)
                        <tr wire:key="metric-{{ $row['tenant_id'] }}-{{ $row['draft_id'] }}" class="h-11 border-t border-line-soft">
                            <td class="max-w-0 px-content-3">
                                <span class="block truncate font-medium text-text-strong" title="{{ $row['title'] }}">
                                    {{ $row['title'] }}
                                </span>
                            </td>
                            <td class="px-content-3 text-text-base">
                                <span class="block truncate" title="{{ $row['portal'] }}">{{ $row['portal'] }}</span>
                            </td>
                            <td class="px-content-3">
                                <span class="content-status content-status--{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                            </td>
                            <td class="px-content-3 text-end tabular-nums text-text-base">{{ $int($row['impressions']) }}</td>
                            <td class="px-content-3 text-end tabular-nums text-text-strong">{{ $int($row['clicks']) }}</td>
                            <td class="px-content-3 text-end tabular-nums text-text-base">{{ $dec($row['position'], 1) }}</td>
                            <td class="px-content-3 text-end tabular-nums text-text-base">
                                {{ $row['revenue_usd'] === null ? '—' : $dec($row['revenue_usd'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                {{-- Summenzeile ueber den gesamten Filter, nicht nur ueber die
                     sichtbare Seite — sonst waere sie beim Blaettern wertlos. --}}
                <tfoot>
                    <tr class="h-11 border-t border-line-strong bg-surface-sunken text-text-strong">
                        <td class="px-content-3 font-semibold" colspan="3">
                            {{ trans_choice('{1}Ein Artikel gesamt|[2,*]:count Artikel gesamt', $totals['articles'], ['count' => $int($totals['articles'])]) }}
                        </td>
                        <td class="px-content-3 text-end font-semibold tabular-nums">{{ $int($totals['impressions']) }}</td>
                        <td class="px-content-3 text-end font-semibold tabular-nums">{{ $int($totals['clicks']) }}</td>
                        <td class="px-content-3 text-end font-semibold tabular-nums">{{ $dec($totals['position'], 1) }}</td>
                        <td class="px-content-3 text-end font-semibold tabular-nums">
                            {{ $totals['revenue_usd'] === null ? '—' : $dec($totals['revenue_usd'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Mobil (< 1024 px): Kartenliste statt waagerecht scrollender Tabelle. --}}
        <div class="overflow-hidden rounded-content-lg bg-surface-card lg:hidden">
            @foreach ($list['rows'] as $row)
                <div wire:key="metric-card-{{ $row['tenant_id'] }}-{{ $row['draft_id'] }}" class="border-t border-line-soft p-content-4 first:border-t-0">
                    <p class="flex items-center gap-content-2 text-content-label text-text-muted">
                        <span class="truncate">{{ $row['portal'] }}</span>
                        <span class="ms-auto">{{ $row['region'] }}</span>
                    </p>
                    <p class="mt-content-1 line-clamp-2 text-content-table font-semibold text-text-strong">{{ $row['title'] }}</p>
                    <p class="mt-content-2 flex items-center gap-content-3">
                        <span class="content-status content-status--{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                        <span class="ms-auto text-content-label tabular-nums text-text-base">
                            {{ __(':clicks Klicks · :impressions Impr.', [
                                'clicks' => $int($row['clicks']),
                                'impressions' => $int($row['impressions']),
                            ]) }}
                        </span>
                    </p>
                </div>
            @endforeach
        </div>

        @if ($list['pages'] > 1)
            <div class="flex h-14 flex-wrap items-center gap-content-2 px-content-1">
                <span class="text-content-label text-text-muted">
                    {{ __(':from–:to von :total', ['from' => $list['from'], 'to' => $list['to'], 'total' => $list['total']]) }}
                </span>

                <div class="ms-auto flex items-center gap-content-1">
                    <button
                        type="button"
                        wire:click="goToPage({{ $list['page'] - 1 }})"
                        @disabled($list['page'] <= 1)
                        class="h-11 rounded-content-md border border-line-strong px-content-3 text-content-table font-medium text-text-strong disabled:opacity-40"
                    >
                        {{ __('Zurück') }}
                    </button>

                    @foreach (range($first, $last) as $number)
                        <button
                            type="button"
                            wire:key="metric-page-{{ $number }}"
                            wire:click="goToPage({{ $number }})"
                            aria-current="{{ $number === $list['page'] ? 'page' : 'false' }}"
                            @class([
                                'h-11 min-w-11 rounded-content-md px-content-2 text-content-table',
                                'bg-content-600 font-semibold text-white' => $number === $list['page'],
                                'text-text-base' => $number !== $list['page'],
                            ])
                        >
                            {{ $number }}
                        </button>
                    @endforeach

                    <button
                        type="button"
                        wire:click="goToPage({{ $list['page'] + 1 }})"
                        @disabled($list['page'] >= $list['pages'])
                        class="h-11 rounded-content-md border border-line-strong px-content-3 text-content-table font-medium text-text-strong disabled:opacity-40"
                    >
                        {{ __('Weiter') }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Top 10 und Flop 10 nach Klicks. Beide Listen zeigen dieselbe
             Groesse, damit sie vergleichbar bleiben. --}}
        <div class="grid gap-content-4 lg:grid-cols-2">
            @foreach ([
                ['title' => __('Top 10 nach Klicks'), 'rows' => $snapshot['top']],
                ['title' => __('Flop 10 nach Klicks'), 'rows' => $snapshot['flop']],
            ] as $panel)
                <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                    <h2 class="text-content-h3 font-semibold text-text-strong">{{ $panel['title'] }}</h2>

                    <ol class="mt-content-3 space-y-content-2">
                        @forelse ($panel['rows'] as $index => $row)
                            <li class="flex items-baseline gap-content-3 border-t border-line-soft pt-content-2 first:border-t-0 first:pt-0">
                                <span class="w-6 text-content-label tabular-nums text-text-muted">{{ $index + 1 }}.</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-content-table text-text-strong" title="{{ $row['title'] }}">
                                        {{ $row['title'] }}
                                    </span>
                                    <span class="block truncate text-content-label text-text-muted">{{ $row['portal'] }}</span>
                                </span>
                                <span class="text-content-table tabular-nums text-text-base">
                                    {{ __(':clicks Klicks', ['clicks' => $int($row['clicks'])]) }}
                                </span>
                            </li>
                        @empty
                            <li class="text-content-body text-text-muted">{{ __('Keine Artikel im Zeitraum.') }}</li>
                        @endforelse
                    </ol>
                </section>
            @endforeach
        </div>
    @endif

    {{-- Refresh-Kandidaten (#23/#24): der Grund steht dabei, sonst ist die
         Liste nur eine Behauptung. --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h2 class="text-content-h3 font-semibold text-text-strong">{{ __('Kandidaten für eine Aktualisierung') }}</h2>
        <p class="mt-content-1 text-content-label text-text-muted">
            {{ __('Vom Metrik-Collector markiert: Position gefallen oder CTR eingebrochen.') }}
        </p>

        @if ($snapshot['refresh_candidates'] === [])
            <p class="mt-content-3 text-content-body text-text-muted">
                {{ __('Kein Artikel ist zur Aktualisierung vorgemerkt.') }}
            </p>
        @else
            <ul class="mt-content-3 space-y-content-3">
                @foreach ($snapshot['refresh_candidates'] as $candidate)
                    <li
                        wire:key="refresh-{{ $candidate['tenant_id'] }}-{{ $candidate['draft_id'] }}"
                        class="border-t border-line-soft pt-content-3 first:border-t-0 first:pt-0"
                    >
                        <p class="text-content-table font-medium text-text-strong">{{ $candidate['title'] }}</p>
                        <p class="mt-content-1 text-content-label text-text-muted">
                            {{ $candidate['portal'] }}
                            @if ($candidate['marked_at'])
                                · {{ __('vorgemerkt am :date', [
                                    'date' => \Illuminate\Support\Carbon::parse($candidate['marked_at'])->format('d.m.Y'),
                                ]) }}
                            @endif
                        </p>
                        <p class="mt-content-1 text-content-label text-text-base">
                            {{ implode(' · ', $candidate['reasons']) ?: __('Grund nicht hinterlegt') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
