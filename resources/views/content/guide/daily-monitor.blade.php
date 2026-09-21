{{--
    Heute — Tageslauf-Monitor (#16, design/guide-dashboard.md §3).
    Reihenfolge: Zustandszeile · Störungsbänder · Prüfstand + Kosten ·
    Portale · Heute geändert · Offene Alarme · Altartikel · Außerhalb des Plans.
    Polling alle 15 s nur im Laufzeitfenster bzw. solange Läufe unterwegs sind.
--}}
@use('App\Guide\Filament\Pages\DailyRunMonitor')
@use('App\Guide\Support\Usd')
@use('App\Guide\Support\CheckedQuote')
@php
    $overview = $this->getOverview();
    $totals = $overview['totals'];
    $counts = $totals['counts'];
    $labels = DailyRunMonitor::segmentLabels();
    $tz = config('guide.timezone');
    $asOf = \Illuminate\Support\Carbon::parse($overview['generated_at'])->timezone($tz)->format('H:i');
    $bands = $this->getBands();
    $portals = collect($overview['portals']);
    $active = $portals->where('is_active', true)
        ->sortBy([
            fn (array $a, array $b): int => (int) (($b['counts']['fehlgeschlagen'] + $b['counts']['pruefung']) > 0) <=> (int) (($a['counts']['fehlgeschlagen'] + $a['counts']['pruefung']) > 0),
            fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
        ]);
    $inactive = $portals->where('is_active', false)->sortBy('name');
    $changed = $portals->flatMap(fn (array $portal): array => array_map(fn (array $item): array => [...$item, 'portal' => $portal['name']], $portal['changed']))
        ->sortByDesc('published_at')
        ->values();
    $bandStyle = fn (string $tone): string => "background: var(--color-status-{$tone}-bg); border-color: var(--color-status-{$tone}-dot)";
    $costs = $overview['costs'];
    $costRatio = $costs['budget'] > 0 ? $costs['today'] / $costs['budget'] : 0;
    $costFill = match (true) {
        $costRatio >= 1 => 'var(--color-status-failed-fill)',
        $costRatio >= (float) config('guide.budget.warn_threshold', 0.8) => 'var(--color-status-review-fill)',
        default => 'var(--color-status-published-fill)',
    };
    $kinds = [
        'neu' => __('Neuanlagen'),
        'aktualisiert' => __('Aktualisierungen'),
        'unveraendert' => __('Prüfungen ohne Änderung'),
    ];
@endphp
<x-filament-panels::page>
    <div @if ($this->isPolling()) wire:poll.15s.visible @endif class="flex flex-col gap-content-6">
        <p class="text-content-body text-text-base">
            {{ $this->getStateLine() }}
            @if ($stateLink = $this->getStateLink())
                · <a href="{{ $stateLink['url'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ $stateLink['label'] }}</a>
            @endif
        </p>

        {{-- Störungsbänder: höchstens drei sichtbar, der Rest aufklappbar --}}
        @if ($bands !== [])
            <div class="flex flex-col gap-content-2">
                @foreach (array_slice($bands, 0, 3) as $band)
                    <div class="flex flex-wrap items-center justify-between gap-content-3 rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="{{ $bandStyle($band['tone']) }}" role="status">
                        <span>{{ $band['text'] }}</span>
                        @if ($band['link'])
                            <a href="{{ $band['link'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ $band['label'] }}</a>
                        @endif
                    </div>
                @endforeach
                @if (count($bands) > 3)
                    <details>
                        <summary class="cursor-pointer text-content-table text-text-base">
                            {{ trans_choice('{1} + 1 weiterer Hinweis|[2,*] + :count weitere Hinweise', count($bands) - 3, ['count' => count($bands) - 3]) }}
                        </summary>
                        <div class="mt-content-2 flex flex-col gap-content-2">
                            @foreach (array_slice($bands, 3) as $band)
                                <div class="flex flex-wrap items-center justify-between gap-content-3 rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="{{ $bandStyle($band['tone']) }}">
                                    <span>{{ $band['text'] }}</span>
                                    @if ($band['link'])
                                        <a href="{{ $band['link'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ $band['label'] }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        @endif

        @if ($totals['imported_inactive'])
            {{-- Vor dem ersten Lauf (§3.5): Kacheln entfallen --}}
            <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Themen importiert, noch nicht aktiviert') }}</h2>
                <p class="mt-content-2 text-content-body text-text-base">
                    {{ trans_choice('{1} :topics Themen in einem Portal warten auf die Aktivierung. Erst dann entstehen Kosten.|[2,*] :topics Themen in :count Portalen warten auf die Aktivierung. Erst dann entstehen Kosten.', $totals['portals_with_topics'], ['topics' => $totals['topics_total'], 'count' => $totals['portals_with_topics']]) }}
                </p>
                @if ($this->canManageSettings())
                    <div class="mt-content-4">
                        <x-filament::button tag="a" :href="\App\Guide\Filament\Pages\TenantGuideSettings::getUrl()">
                            {{ __('Themen aktivieren …') }}
                        </x-filament::button>
                    </div>
                @endif
            </section>
        @else
            <div class="grid grid-cols-1 gap-content-4 lg:grid-cols-12">
                {{-- Kachel „Prüfstand heute“ (§3.2) --}}
                <section @class(['rounded-content-lg bg-surface-card p-content-6 shadow-content-card', 'lg:col-span-8' => $this->canSeeCosts(), 'lg:col-span-12' => ! $this->canSeeCosts()])>
                    <div class="flex items-baseline justify-between gap-content-3">
                        <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Prüfstand heute') }}</h2>
                        <span class="content-asof">{{ __('Stand :time', ['time' => $asOf]) }}</span>
                    </div>

                    @php($totalPercent = CheckedQuote::percent($totals['checked'], $totals['due']))
                    @if ($totals['due'] > 0 && $totals['checked'] >= $totals['due'])
                        <p class="mt-content-3 flex items-center gap-content-2 text-content-body text-text-base">
                            <x-filament::icon icon="heroicon-m-check-circle" class="size-5" style="color: var(--color-status-published-dot)" aria-hidden="true" />
                            {{ __('Alle fälligen Themen sind geprüft.') }}
                        </p>
                    @endif

                    <p class="mt-content-3">
                        <span class="text-[2.25rem] font-semibold tabular-nums text-text-strong lg:text-content-metric">
                            {{ __(':checked von :total', ['checked' => $totals['checked'], 'total' => $totals['due']]) }}
                        </span>
                        <span class="block text-content-body text-text-base">
                            {{ __('fälligen Themen geprüft') }} ·
                            @include('content.guide.partials.checked-quote', ['percent' => $totalPercent, 'warn' => $this->quoteMayWarn(), 'variant' => 'tile'])
                        </span>
                    </p>

                    <div class="mt-content-4">
                        @include('content.guide.partials.run-bar', [
                            'counts' => $counts,
                            'label' => DailyRunMonitor::barLabel($counts, $totals['checked'], $totals['due']),
                            'height' => 'h-4',
                        ])
                    </div>

                    <ul class="mt-content-3 grid grid-cols-2 gap-x-content-4 gap-y-content-2 sm:flex sm:flex-wrap">
                        @foreach ($labels as $segment => $label)
                            <li class="flex items-center gap-content-2 text-content-table text-text-base">
                                <span
                                    @class(['inline-block size-3 shrink-0 rounded-[2px]', 'content-run-segment--new' => $segment === 'neu'])
                                    @if ($segment !== 'neu') style="background: var(--color-run-{{ ['aktualisiert' => 'updated', 'unveraendert' => 'unchanged', 'pruefung' => 'review', 'fehlgeschlagen' => 'failed', 'in-arbeit' => 'active', 'offen' => 'open'][$segment] }}-fill)" @endif
                                    aria-hidden="true"
                                ></span>
                                @if ($counts[$segment] > 0 && $segment !== 'offen')
                                    <a href="{{ $this->topicsUrl($segment) }}" class="hover:underline">
                                        {{ $label }} <strong class="tabular-nums">{{ $counts[$segment] }}</strong>
                                    </a>
                                @else
                                    <span>{{ $label }} <strong @class(['tabular-nums', 'text-text-muted' => $counts[$segment] === 0])>{{ $counts[$segment] }}</strong></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($totals['deferred'] > 0)
                        <p class="mt-content-2 text-content-table text-text-muted">
                            {{ trans_choice('{1} Davon offen: ein Thema auf morgen verschoben.|[2,*] Davon offen: :count Themen auf morgen verschoben.', $totals['deferred'], ['count' => $totals['deferred']]) }}
                        </p>
                    @endif

                    @php($checkedRuns = $counts['neu'] + $counts['aktualisiert'] + $counts['unveraendert'] + $counts['pruefung'])
                    @if ($checkedRuns > 0)
                        <p class="mt-content-2 text-content-table text-text-muted">
                            {{ __('Änderungsquote heute :rate % (Annahme im Kostenmodell: 10 %)', ['rate' => (int) round(($counts['neu'] + $counts['aktualisiert'] + $counts['pruefung']) / $checkedRuns * 100)]) }}
                        </p>
                    @endif
                </section>

                {{-- Kachel „Kosten heute“ (§3.3), nur Inhaber --}}
                @if ($this->canSeeCosts())
                    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card lg:col-span-4">
                        <div class="flex items-baseline justify-between gap-content-3">
                            <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Kosten heute') }}</h2>
                            <span class="content-asof">{{ __('Stand :time', ['time' => $asOf]) }}</span>
                        </div>
                        <p class="mt-content-3">
                            <span class="text-[1.75rem] font-semibold tabular-nums text-text-strong">{{ Usd::format($costs['today']) }}</span>
                            @if ($costs['budget'] > 0)
                                <span class="text-content-body text-text-base">{{ __('von :budget', ['budget' => Usd::format($costs['budget'])]) }}</span>
                            @endif
                        </p>
                        @if ($costs['budget'] > 0)
                            <div class="relative mt-content-3 h-2 w-full overflow-hidden rounded-content-sm" style="background: var(--color-run-open-fill)" role="img" aria-label="{{ __(':percent % des Tagesbudgets verbraucht', ['percent' => (int) floor($costRatio * 100)]) }}">
                                <span class="block h-full" style="width: {{ min(100, round($costRatio * 100, 1)) }}%; background: {{ $costFill }}"></span>
                                <span class="absolute inset-y-0 w-px" style="left: {{ (float) config('guide.budget.warn_threshold', 0.8) * 100 }}%; background: var(--color-text-base)" aria-hidden="true"></span>
                            </div>
                        @endif
                        <dl class="mt-content-4 flex flex-col gap-content-1 text-content-table text-text-base">
                            @foreach ($kinds as $display => $kindLabel)
                                @php($amount = (float) ($costs['by_display'][$display] ?? 0))
                                <div class="flex justify-between gap-content-3">
                                    <dt>{{ $kindLabel }}</dt>
                                    <dd class="tabular-nums">
                                        {{ $counts[$display] }} × ⌀ {{ $counts[$display] > 0 ? Usd::format($amount / $counts[$display]) : '–' }}
                                    </dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between gap-content-3">
                                <dt>{{ __('Web-Suchen') }}</dt>
                                <dd class="tabular-nums">{{ number_format($costs['searches'], 0, ',', '.') }}</dd>
                            </div>
                        </dl>
                        <a href="{{ \App\Guide\Filament\Pages\Costs::getUrl() }}" class="mt-content-4 inline-block text-content-table font-medium text-content-700 underline underline-offset-2">
                            {{ __('Kostenverlauf') }}
                        </a>
                    </section>
                @endif
            </div>

            {{-- Tabelle „Portale“ (§3.4); mobil als Karten (§3.6) --}}
            <section class="rounded-content-lg bg-surface-card shadow-content-card">
                <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">{{ __('Portale') }}</h2>

                @if ($active->isEmpty())
                    <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('Kein Portal ist für den Tageslauf freigeschaltet.') }}</p>
                @else
                    <div class="hidden lg:block">
                        <table class="w-full text-content-table">
                            <thead>
                                <tr class="border-b border-line-soft text-start text-content-label text-text-muted">
                                    <th class="w-[22%] px-content-6 py-content-2 text-start font-medium">Portal</th>
                                    <th class="w-[26%] py-content-2 text-start font-medium">{{ __('Tagesbalken') }}</th>
                                    <th class="w-[10%] py-content-2 text-end font-medium">{{ __('Geprüft') }}</th>
                                    <th class="w-[8%] py-content-2 text-end font-medium">{{ __('Neu') }}</th>
                                    <th class="w-[8%] py-content-2 text-end font-medium">{{ __('Aktualisiert') }}</th>
                                    <th class="w-[8%] py-content-2 text-end font-medium">{{ __('Zur Prüfung') }}</th>
                                    <th class="w-[8%] py-content-2 text-end font-medium">{{ __('Fehlgeschlagen') }}</th>
                                    @if ($this->canSeeCosts())
                                        <th class="w-[10%] px-content-6 py-content-2 text-end font-medium">{{ __('Kosten') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach ($active as $portal)
                                    <tr class="h-14 cursor-pointer hover:bg-surface-sunken" wire:key="portal-{{ $portal['tenant_id'] }}" wire:click="selectPortal({{ $portal['tenant_id'] }})">
                                        <td class="px-content-6">
                                            <span class="font-medium text-text-strong">{{ $portal['name'] }}</span>
                                            <span class="block text-content-label text-text-muted">{{ $portal['domain'] }}</span>
                                        </td>
                                        <td class="pe-content-4">
                                            @include('content.guide.partials.run-bar', [
                                                'counts' => $portal['counts'],
                                                'label' => $portal['name'].': '.DailyRunMonitor::barLabel($portal['counts'], $portal['checked'], $portal['due']),
                                                'height' => 'h-2',
                                            ])
                                        </td>
                                        <td class="text-end tabular-nums text-text-base">
                                            {{ $portal['checked'] }} / {{ $portal['due'] }}
                                            <span class="block text-content-label">
                                                @include('content.guide.partials.checked-quote', ['percent' => CheckedQuote::percent($portal['checked'], $portal['due']), 'warn' => $this->quoteMayWarn(), 'variant' => 'cell'])
                                            </span>
                                        </td>
                                        @foreach (['neu', 'aktualisiert', 'pruefung', 'fehlgeschlagen'] as $segment)
                                            <td class="text-end tabular-nums">
                                                @if ($portal['counts'][$segment] > 0)
                                                    <button type="button" class="font-medium text-content-700 underline underline-offset-2" wire:click.stop="openTopics({{ $portal['tenant_id'] }}, '{{ $segment }}')">
                                                        {{ $portal['counts'][$segment] }}
                                                    </button>
                                                @else
                                                    <span class="text-text-muted">–</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($this->canSeeCosts())
                                            @php($overBudget = $portal['budget'] > 0 && $portal['cost'] >= $portal['budget'] * (float) config('guide.budget.warn_threshold', 0.8))
                                            <td class="px-content-6 text-end tabular-nums" @if ($overBudget) style="color: var(--color-status-review-fg)" @endif>
                                                @if ($overBudget)
                                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="inline size-4" aria-hidden="true" />
                                                    <span class="sr-only">{{ __('Portalbudget zu mindestens 80 % verbraucht:') }}</span>
                                                @endif
                                                {{ Usd::format($portal['cost']) }}
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <ul class="flex flex-col divide-y divide-line-soft lg:hidden">
                        @foreach ($active as $portal)
                            <li class="p-content-4" wire:key="portal-card-{{ $portal['tenant_id'] }}">
                                <button type="button" class="flex min-h-11 w-full items-center justify-between gap-content-3 text-start" wire:click="selectPortal({{ $portal['tenant_id'] }})">
                                    <span class="font-semibold text-text-strong">{{ $portal['name'] }}</span>
                                    <span class="flex items-center gap-content-2 tabular-nums text-text-base">
                                        {{ $portal['checked'] }} / {{ $portal['due'] }}
                                        @include('content.guide.partials.checked-quote', ['percent' => CheckedQuote::percent($portal['checked'], $portal['due']), 'warn' => $this->quoteMayWarn(), 'variant' => 'cell'])
                                    </span>
                                </button>
                                <div class="mt-content-2">
                                    @include('content.guide.partials.run-bar', [
                                        'counts' => $portal['counts'],
                                        'label' => $portal['name'].': '.DailyRunMonitor::barLabel($portal['counts'], $portal['checked'], $portal['due']),
                                        'height' => 'h-2',
                                    ])
                                </div>
                                @php($problems = array_filter(['pruefung' => __(':count zur Prüfung', ['count' => $portal['counts']['pruefung']]), 'fehlgeschlagen' => __(':count fehlgeschlagen', ['count' => $portal['counts']['fehlgeschlagen']])], fn ($text, $segment) => $portal['counts'][$segment] > 0, ARRAY_FILTER_USE_BOTH))
                                @if ($problems !== [])
                                    <p class="mt-content-2 flex flex-wrap gap-x-content-2 text-content-table">
                                        @foreach ($problems as $segment => $text)
                                            <button type="button" class="font-medium text-content-700 underline underline-offset-2" wire:click="openTopics({{ $portal['tenant_id'] }}, '{{ $segment }}')">{{ $text }}</button>
                                        @endforeach
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($inactive->isNotEmpty())
                    <details class="border-t border-line-soft px-content-6 py-content-3">
                        <summary class="cursor-pointer text-content-table text-text-base">
                            {{ __('Nicht freigeschaltet (:count)', ['count' => $inactive->count()]) }}
                        </summary>
                        <ul class="mt-content-2 flex flex-col gap-content-1 text-content-table text-text-base">
                            @foreach ($inactive as $portal)
                                <li>
                                    {{ $portal['name'] }}
                                    <span class="text-text-muted">· {{ trans_choice('{0} keine Themen|{1} ein Thema|[2,*] :count Themen', $portal['topics_total'], ['count' => $portal['topics_total']]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @endif

        {{-- Heute geändert: Changelog-Satz und Verweis auf den Artikel --}}
        <section class="rounded-content-lg bg-surface-card shadow-content-card">
            <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">
                {{ __('Heute geändert') }}
                <span class="text-content-body font-normal text-text-muted">({{ $changed->count() }})</span>
            </h2>
            @if ($changed->isEmpty())
                <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('Heute ist noch kein Artikel neu erschienen oder aktualisiert worden.') }}</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($changed as $item)
                        <li class="flex flex-col gap-content-1 px-content-6 py-content-3 sm:flex-row sm:items-start sm:justify-between sm:gap-content-4">
                            <div class="min-w-0">
                                <span class="content-status content-status--published">{{ $item['mode'] === 'neu' ? __('Neu erschienen') : __('Aktualisiert') }}</span>
                                <span class="ms-content-2 font-medium text-text-strong">{{ $item['question'] }}</span>
                                <p class="mt-content-1 text-content-table text-text-base">
                                    {{ $item['summary'] ?: ($item['mode'] === 'neu' ? __('Artikel neu erschienen.') : __('Ohne Changelog-Satz.')) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-content-3 text-content-table text-text-muted">
                                @if ($this->isNetworkWide())
                                    <span>{{ $item['portal'] }}</span>
                                @endif
                                <span class="tabular-nums">{{ $item['published_at'] ? \Illuminate\Support\Carbon::parse($item['published_at'])->timezone($tz)->format('H:i') : '' }}</span>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="font-medium text-content-700 underline underline-offset-2">{{ __('Artikel öffnen') }}</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Offene Alarme --}}
        @if ($overview['alerts'] !== [])
            <section class="rounded-content-lg bg-surface-card shadow-content-card">
                <details>
                    <summary class="cursor-pointer px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">
                        {{ __('Offene Alarme (:count)', ['count' => count($overview['alerts'])]) }}
                    </summary>
                    <ul class="divide-y divide-line-soft border-t border-line-soft">
                        @foreach ($overview['alerts'] as $alert)
                            <li class="flex flex-col gap-content-1 px-content-6 py-content-3 text-content-table sm:flex-row sm:justify-between sm:gap-content-4">
                                <div class="min-w-0">
                                    <span @class([
                                        'content-status',
                                        'content-status--failed' => $alert['level'] === 'critical',
                                        'content-status--review' => $alert['level'] === 'warning',
                                        'content-status--idea' => $alert['level'] === 'info',
                                    ])>{{ match ($alert['level']) { 'critical' => __('Kritisch'), 'warning' => __('Warnung'), default => __('Hinweis') } }}</span>
                                    <span class="ms-content-2 text-text-base">{{ $alert['message'] }}</span>
                                </div>
                                <span class="shrink-0 text-text-muted">
                                    {{ collect([
                                        $alert['tenant_name'] ?? __('Alle Portale'),
                                        $alert['occurrences'] > 1 ? __(':count ×', ['count' => $alert['occurrences']]) : null,
                                        $alert['last_seen_at'] ? \Illuminate\Support\Carbon::parse($alert['last_seen_at'])->timezone($tz)->format('d.m., H:i') : null,
                                    ])->filter()->implode(' · ') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </section>
        @endif

        @if ($overlapHint = $this->getLegacyOverlapHint())
            <div class="flex flex-wrap items-center justify-between gap-content-3 rounded-content-lg border border-line-strong bg-surface-card px-content-6 py-content-4 text-content-body text-text-base">
                <span>{{ $overlapHint['text'] }}</span>
                <a href="{{ $overlapHint['url'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ __('Entscheiden') }}</a>
            </div>
        @endif

        {{-- Außerhalb des Plans (§3.1 Punkt 6): länger als Prüfabstand + 2 Tage nicht geprüft --}}
        @if ($outOfPlan = $this->getOutOfPlanHint())
            <div class="flex flex-wrap items-center justify-between gap-content-3 rounded-content-lg border border-line-strong bg-surface-card px-content-6 py-content-4 text-content-body text-text-base">
                <span>{{ __('Außerhalb des Plans:') }} {{ $outOfPlan['text'] }}</span>
                <a href="{{ $outOfPlan['url'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ __('Themen ansehen') }}</a>
            </div>
        @endif

        @if ($legacyLinks = $this->getLegacyPipelineLinks())
            <div class="rounded-content-lg border border-line-strong bg-surface-card p-content-6">
                <h2 class="text-content-h3 font-semibold text-text-strong">{{ __('Weitere Seiten') }}</h2>
                <p class="mt-content-2 text-content-table text-text-base">{{ __('Leistung und Portal-Einstellungen bleiben hier erreichbar, bis das neue Dashboard sie übernimmt.') }}</p>
                <ul class="mt-content-4 flex flex-wrap gap-x-content-6 gap-y-content-2 text-content-table">
                    @foreach ($legacyLinks as $link)
                        <li><a href="{{ $link['url'] }}" class="font-medium text-content-700 underline underline-offset-2">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
