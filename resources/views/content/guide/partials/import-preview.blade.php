{{--
    Vorschau des Imports (design/guide-dashboard.md §4.3). Zählerkarten sind
    Filter auf die Tabelle (Alpine, ohne Serveranfrage); rechts die
    Kategorienverteilung mit Hinweisen.
    Erwartet: $preview aus App\Guide\Import\ImportPreview::build()
--}}
@php
    $cards = [
        'new' => ['label' => __('Neu'), 'pill' => 'published'],
        'duplicate' => ['label' => __('Dublette'), 'pill' => 'review'],
        'new_category' => ['label' => __('Kategorie wird neu angelegt'), 'pill' => 'scheduled'],
        'outline' => ['label' => __('Überschriften vorgegeben'), 'pill' => 'idea'],
    ];
    $marks = [
        'new' => ['label' => __('neu'), 'pill' => 'published'],
        'duplicate' => ['label' => __('Dublette'), 'pill' => 'review'],
        'new_category' => ['label' => __('neue Kategorie'), 'pill' => 'scheduled'],
        'outline' => ['label' => __('Gliederung vorgegeben'), 'pill' => 'idea'],
        'error' => ['label' => __('wird übersprungen'), 'pill' => 'failed'],
    ];
    $maxCategory = max([1, ...array_values($preview['categories'])]);
@endphp
<div x-data="{ filter: null }" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="flex flex-col gap-4 lg:col-span-8">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($cards as $mark => $card)
                <button
                    type="button"
                    x-on:click="filter = filter === '{{ $mark }}' ? null : '{{ $mark }}'"
                    x-bind:aria-pressed="filter === '{{ $mark }}'"
                    x-bind:class="filter === '{{ $mark }}' ? 'border-content-600' : 'border-line-strong'"
                    class="flex flex-col items-start gap-1 rounded-xl border bg-surface-card p-4 text-left"
                >
                    <span class="text-2xl font-semibold tabular-nums text-text-strong">{{ $preview['counts'][$mark] }}</span>
                    <span class="content-status content-status--{{ $card['pill'] }}">{{ $card['label'] }}</span>
                </button>
            @endforeach
        </div>

        @if ($preview['counts']['error'] > 0)
            <p class="text-sm text-status-failed-fg">
                {{ trans_choice('{1} Eine Zeile wird übersprungen (oben in der Tabelle).|[2,*] :count Zeilen werden übersprungen (oben in der Tabelle).', $preview['counts']['error'], ['count' => $preview['counts']['error']]) }}
            </p>
        @endif

        <div class="max-h-[36rem] overflow-auto rounded-xl border border-line-strong bg-surface-card">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 border-b border-line-soft bg-surface-card text-xs uppercase tracking-wide text-text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Zeile') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Thema') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Kategorie') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Überschriften') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Marken') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($preview['rows'] as $row)
                        <tr x-show="filter === null || {{ \Illuminate\Support\Js::from($row['marks']) }}.includes(filter)">
                            <td class="px-3 py-2 align-top tabular-nums text-text-muted">{{ $row['line'] }}</td>
                            <td class="px-3 py-2 align-top text-text-strong">
                                {{ $row['resolved'] !== '' ? $row['resolved'] : '–' }}
                                @if ($row['error'])
                                    <span class="block text-xs text-status-failed-fg">{{ $row['error'] }}</span>
                                @endif
                                @if ($row['warning'])
                                    <span class="block text-xs text-status-review-fg">{{ $row['warning'] }}</span>
                                @endif
                                @if ($row['duplicate_of'])
                                    <span class="block text-xs text-text-muted">
                                        @if (isset($row['duplicate_of']['key']))
                                            {{ __('Im Portal vorhanden:') }}
                                            <a href="{{ \App\Guide\Filament\Resources\TopicResource::detailUrl($row['duplicate_of']['key']) }}" target="_blank" class="text-content-700 underline underline-offset-2">{{ \Illuminate\Support\Str::limit($row['duplicate_of']['question'], 60) }}</a>
                                        @else
                                            {{ __('Gleiche Frage wie Zeile :line — wird übersprungen.', ['line' => $row['duplicate_of']['line']]) }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top text-text-base">{{ $row['category'] ?? '–' }}</td>
                            <td class="px-3 py-2 align-top text-text-base">
                                @if ($row['outline'] !== [])
                                    <details>
                                        <summary class="cursor-pointer">{{ count($row['outline']) }}</summary>
                                        <ol class="mt-1 flex flex-col gap-0.5 text-xs">
                                            @foreach ($row['outline'] as $entry)
                                                <li @class(['pl-4' => $entry['level'] === 3])>H{{ $entry['level'] }} · {{ $entry['text'] }}</li>
                                            @endforeach
                                        </ol>
                                    </details>
                                @else
                                    –
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($row['marks'] as $mark)
                                        <span class="content-status content-status--{{ $marks[$mark]['pill'] }}">{{ $marks[$mark]['label'] }}</span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-text-base">{{ __('Keine Zeilen gefunden.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <aside class="flex flex-col gap-4 lg:col-span-4 lg:self-start lg:sticky lg:top-4">
        @if ($preview['hints'] !== [])
            @include('content.guide.partials.hints', ['hints' => $preview['hints']])
        @endif

        <section class="rounded-xl border border-line-strong bg-surface-card p-4">
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Kategorienverteilung') }}</h3>
            @if ($preview['categories'] === [])
                <p class="mt-2 text-sm text-text-base">{{ __('Keine Kategorien in der Liste. Themen ohne Kategorie erscheinen im Ratgeber nur in der Übersicht.') }}</p>
            @else
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @foreach ($preview['categories'] as $name => $count)
                        <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1">
                            <span class="truncate text-text-base">{{ $name }}</span>
                            <span class="tabular-nums font-semibold text-text-strong">{{ $count }}</span>
                            <span class="col-span-2 h-2 rounded-full bg-surface-sunken">
                                <span class="block h-2 rounded-full" style="width: {{ max(4, round($count / $maxCategory * 100)) }}%; background: var(--color-status-scheduled-fill)"></span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </aside>
</div>
