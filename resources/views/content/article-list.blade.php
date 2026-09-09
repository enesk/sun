{{--
    Artikelliste (#36), design/content-dashboard.md, §3a.

    Reiter 3 der Produktion. Sieben Spalten, 44 px Zeilenhoehe, 50 Zeilen je
    Seite. Kein Polling — der Stand steht im Filterbalken, daneben
    "Aktualisieren". Unter 1024 px wird jede Zeile eine Karte; eine
    waagerecht scrollende Tabelle ist auf dem Telefon unbedienbar.
--}}
@php
    use App\Content\Services\ContentPipelineService;

    $sortLabels = $this->sortOptions();
    $columns = [
        ['key' => 'titel', 'label' => __('Titel'), 'class' => 'min-w-[320px]', 'align' => 'text-start'],
        ['key' => 'portal', 'label' => __('Portal'), 'class' => 'w-[180px]', 'align' => 'text-start'],
        ['key' => 'status', 'label' => __('Status'), 'class' => 'w-[160px]', 'align' => 'text-start'],
        ['key' => null, 'label' => __('Region'), 'class' => 'w-[160px]', 'align' => 'text-start'],
        // Aktualisierungsstand (#99): feste Breite, kein Umbruch, leer wenn
        // kein Zustand zutrifft. Nicht sortierbar — der Zustand ist eine
        // Auskunft, keine Ordnung.
        ['key' => null, 'label' => __('Aktualisierung'), 'class' => 'w-[152px]', 'align' => 'text-start'],
        ['key' => 'score', 'label' => __('Score'), 'class' => 'w-[88px]', 'align' => 'text-end'],
        ['key' => 'termin', 'label' => __('Termin'), 'class' => 'w-[132px]', 'align' => 'text-end'],
    ];

    // aria-sort nur an sortierbaren Spalten; die Region traegt es gar nicht.
    $ariaSort = fn (?string $key): ?string => $key === null
        ? null
        : ($key !== $result['sort']
            ? 'none'
            : ($result['direction'] === ContentPipelineService::DIRECTION_ASC ? 'ascending' : 'descending'));

    $arrow = $result['direction'] === ContentPipelineService::DIRECTION_ASC ? '↑' : '↓';

    $termin = function (array $card): ?string {
        if (! $card['date']) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($card['date'])->format('d.m.');

        return $card['time'] ? "{$date} {$card['time']}" : $date;
    };

    $busy = 'portals,statuses,regions,branches,sortBy,applySort,goToPage,resetFilters,refreshList';

    // Fenster von hoechstens sieben Seitenzahlen um die aktuelle Seite —
    // bei 40 Seiten ist eine vollstaendige Zahlenreihe keine Navigation mehr.
    $first = max(1, min($result['page'] - 3, $result['pages'] - 6));
    $last = min($result['pages'], max($result['page'] + 3, 7));
@endphp

<div class="pb-content-8">
    @include('content.partials.filter-bar', [
        'meta' => view('content.partials.list-meta', [
            'result' => $result,
            'refreshedAt' => $refreshedAt,
        ]),
    ])

    @if ($result['unreadable'] > 0)
        <p
            class="mb-content-4 rounded-content-lg border-s-[3px] p-content-3 text-content-body"
            style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)"
        >
            {{ __(':failed von :total Portalen konnten nicht gelesen werden.', [
                'failed' => $result['unreadable'],
                'total' => $result['portals'],
            ]) }}
        </p>
    @endif

    {{-- Laedt: zehn Zeilenskelette in exakt 44 px Hoehe. --}}
    <div wire:loading.flex wire:target="{{ $busy }}" class="flex-col gap-content-1" aria-hidden="true">
        @for ($i = 0; $i < 10; $i++)
            <div class="content-skeleton h-11 w-full"></div>
        @endfor
    </div>

    <div wire:loading.remove wire:target="{{ $busy }}">
        @if ($result['total'] === 0)
            <div class="rounded-content-lg border border-dashed border-line-strong p-content-8 text-center">
                @if ($this->hasFilters())
                    <p class="text-content-body text-text-base">{{ __('Kein Artikel passt zu diesen Filtern.') }}</p>
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="mt-content-4 h-11 rounded-content-md bg-content-600 px-content-4 text-content-table font-medium text-white"
                    >
                        {{ __('Filter zurücksetzen') }}
                    </button>
                @else
                    <p class="text-content-h3 font-semibold text-text-strong">{{ __('Noch keine Artikel.') }}</p>
                    <p class="mt-content-1 text-content-body text-text-muted">
                        {{ __('Der heutige Lauf startet um :time.', ['time' => config('content.pipeline.schedule.generate_at', '05:00')]) }}
                    </p>
                    <a
                        href="{{ \App\Filament\Content\Pages\Overview::getUrl() }}"
                        class="mt-content-4 inline-flex h-11 items-center text-content-table font-medium text-content-700 underline underline-offset-4"
                    >
                        {{ __('Zur Übersicht') }}
                    </a>
                @endif
            </div>
        @else
            {{-- Desktop: Tabelle --}}
            <div class="hidden overflow-hidden rounded-content-lg bg-surface-card lg:block">
                <table class="w-full table-fixed border-collapse text-content-table">
                    <thead>
                        <tr class="sticky top-14 z-[5] bg-surface-sunken">
                            @foreach ($columns as $column)
                                <th
                                    scope="col"
                                    @if ($ariaSort($column['key'])) aria-sort="{{ $ariaSort($column['key']) }}" @endif
                                    {{-- text-base statt text-muted: die Kopfzeile
                                         steht auf surface-sunken, dort traegt
                                         text-muted nur 4,45:1 (#64). --}}
                                    @class(['h-10 p-0 text-content-label font-medium text-text-base', $column['class']])
                                >
                                    @if ($column['key'])
                                        <button
                                            type="button"
                                            wire:click="sortBy('{{ $column['key'] }}')"
                                            @class([
                                                'flex h-10 w-full items-center gap-content-1 px-content-3',
                                                'justify-end' => $column['align'] === 'text-end',
                                            ])
                                        >
                                            <span>{{ $column['label'] }}</span>
                                            @if ($column['key'] === $result['sort'])
                                                <span class="text-[12px] text-text-strong" aria-hidden="true">{{ $arrow }}</span>
                                            @endif
                                        </button>
                                    @else
                                        <span @class(['flex h-10 items-center px-content-3', 'justify-end' => $column['align'] === 'text-end'])>
                                            {{ $column['label'] }}
                                        </span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($result['rows'] as $card)
                            <tr
                                wire:key="row-{{ $card['key'] }}"
                                wire:click="mountAction('cardDetails', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                                class="group h-11 cursor-pointer border-t border-line-soft hover:bg-surface-sunken"
                            >
                                <td class="max-w-0 px-content-3">
                                    {{-- Einziger Tabstopp der Zeile; die Zeile selbst bekommt
                                         weder tabindex noch Rolle. --}}
                                    <button
                                        type="button"
                                        aria-haspopup="dialog"
                                        title="{{ $card['title'] }}"
                                        wire:click.stop="mountAction('cardDetails', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                                        class="block w-full truncate text-start font-medium text-text-strong"
                                    >
                                        {{ $card['title'] }}
                                    </button>
                                </td>
                                <td class="px-content-3 text-text-base">
                                    <span class="block truncate" title="{{ $card['tenant'] }}">{{ $card['tenant'] }}</span>
                                </td>
                                <td class="px-content-3">
                                    <span class="content-status content-status--{{ $card['status'] }}">{{ $card['status_label'] }}</span>
                                </td>
                                <td class="px-content-3 text-text-base">
                                    <span class="block truncate" title="{{ $card['region'] }}">{{ $card['region'] }}</span>
                                </td>
                                <td class="px-content-3">
                                    @if ($card['refresh'] ?? null)
                                        <span class="content-status content-refresh--{{ $card['refresh']['tone'] }}">{{ $card['refresh']['pill'] }}</span>
                                    @endif
                                </td>
                                <td class="px-content-3 text-end tabular-nums">
                                    @if ($card['score'] !== null)
                                        <span
                                            class="font-semibold"
                                            @if ($card['score_level'])
                                                style="color: var(--color-score-{{ $card['score_level'] }})"
                                            @endif
                                        >{{ number_format((float) $card['score'], 0, ',', '.') }}</span>
                                    @else
                                        {{-- Beim Hover liegt die Zeile auf surface-sunken; dort traegt
                                             text-muted nur 4,45:1 (#64). --}}
                                        <span class="text-text-muted group-hover:text-text-base">—</span>
                                    @endif
                                </td>
                                <td class="px-content-3 text-end tabular-nums">
                                    @if ($termin($card))
                                        <span class="text-text-base">{{ $termin($card) }}</span>
                                    @else
                                        <span class="text-text-muted group-hover:text-text-base">{{ __('ohne Termin') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobil (< 1024 px): Kartenliste statt waagerecht scrollender Tabelle. --}}
            <div class="lg:hidden">
                <label class="mb-content-2 flex items-center gap-content-2 text-content-label text-text-muted">
                    <span>{{ __('Sortieren') }}</span>
                    <select
                        wire:change="applySort($event.target.value)"
                        class="h-11 flex-1 rounded-content-md border border-line-strong bg-surface-card px-content-3 text-content-table text-text-strong"
                    >
                        @foreach ($sortLabels as $value => $label)
                            @foreach ([ContentPipelineService::DIRECTION_ASC, ContentPipelineService::DIRECTION_DESC] as $option)
                                <option
                                    value="{{ $value }}:{{ $option }}"
                                    @selected($result['sort'] === $value && $result['direction'] === $option)
                                >
                                    {{ $label }} {{ $option === ContentPipelineService::DIRECTION_ASC ? '↑' : '↓' }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </label>

                <div class="overflow-hidden rounded-content-lg bg-surface-card">
                    @foreach ($result['rows'] as $card)
                        <button
                            type="button"
                            wire:key="card-{{ $card['key'] }}"
                            aria-haspopup="dialog"
                            wire:click="mountAction('cardDetails', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                            class="block min-h-[72px] w-full border-t border-line-soft p-content-4 text-start first:border-t-0"
                        >
                            <span class="flex items-center gap-content-2 text-content-label text-text-muted">
                                <span class="truncate">{{ $card['tenant'] }}</span>
                                <span class="ms-auto tabular-nums">
                                    @if ($card['score'] !== null)
                                        <span
                                            class="font-semibold"
                                            @if ($card['score_level'])
                                                style="color: var(--color-score-{{ $card['score_level'] }})"
                                            @endif
                                        >{{ number_format((float) $card['score'], 0, ',', '.') }}</span>
                                    @else
                                        —
                                    @endif
                                </span>
                            </span>

                            <span class="mt-content-1 line-clamp-2 block text-content-table font-semibold text-text-strong">
                                {{ $card['title'] }}
                            </span>

                            <span class="mt-content-2 flex flex-wrap items-center gap-content-2">
                                <span class="content-status content-status--{{ $card['status'] }}">{{ $card['status_label'] }}</span>
                                @if ($card['refresh'] ?? null)
                                    <span class="content-status content-refresh--{{ $card['refresh']['tone'] }}">{{ $card['refresh']['pill'] }}</span>
                                @endif
                                <span class="ms-auto text-content-label text-text-muted">
                                    {{ $termin($card) ?? __('ohne Termin') }}
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Fuss nur, wenn es etwas zu blaettern gibt — eine tote Leiste
                 unter einer einzelnen Seite hilft niemandem. --}}
            @if ($result['pages'] > 1)
                <div class="mt-content-2 flex h-14 flex-wrap items-center gap-content-2 px-content-1">
                    <span class="text-content-label text-text-muted">
                        {{ __(':from–:to von :total', [
                            'from' => $result['from'],
                            'to' => $result['to'],
                            'total' => $result['total'],
                        ]) }}
                    </span>

                    <div class="ms-auto flex items-center gap-content-1">
                        <button
                            type="button"
                            wire:click="goToPage({{ $result['page'] - 1 }})"
                            @disabled($result['page'] <= 1)
                            class="h-11 rounded-content-md border border-line-strong px-content-3 text-content-table font-medium text-text-strong disabled:opacity-40"
                        >
                            {{ __('Zurück') }}
                        </button>

                        @foreach (range($first, $last) as $number)
                            <button
                                type="button"
                                wire:key="page-{{ $number }}"
                                wire:click="goToPage({{ $number }})"
                                aria-current="{{ $number === $result['page'] ? 'page' : 'false' }}"
                                @class([
                                    'h-11 min-w-11 rounded-content-md px-content-2 text-content-table',
                                    'bg-content-600 font-semibold text-white' => $number === $result['page'],
                                    'text-text-base' => $number !== $result['page'],
                                ])
                            >
                                {{ $number }}
                            </button>
                        @endforeach

                        <button
                            type="button"
                            wire:click="goToPage({{ $result['page'] + 1 }})"
                            @disabled($result['page'] >= $result['pages'])
                            class="h-11 rounded-content-md border border-line-strong px-content-3 text-content-table font-medium text-text-strong disabled:opacity-40"
                        >
                            {{ __('Weiter') }}
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <x-filament-actions::modals />
</div>
