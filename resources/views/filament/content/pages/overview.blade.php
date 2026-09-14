{{--
    Uebersicht des Content-Panels (#19), Aufbau nach
    design/content-dashboard.md, §1: Stoerungsband, Tagesziel, Portalraster,
    Kosten und Quellenlage, letzte Ereignisse.

    Farben ausschliesslich ueber die Token aus resources/css/content/theme.css.
--}}
@php
    use App\Content\Enums\DisplayStatus;

    $snapshot = $this->getSnapshot();
    $target = $snapshot['target'];
    $counts = $snapshot['counts'];
    $cost = $snapshot['cost'];
    $barOrder = DisplayStatus::board();
    $barTotal = max(1, array_sum(array_map(fn (DisplayStatus $s) => $counts[$s->value] ?? 0, $barOrder)));
    $expectedShare = $target['total'] > 0 ? $target['expected_now'] / $target['total'] : 0;

    $verdict = match (true) {
        $cost['today_share'] >= 1.0 => __('Produktion gestoppt (Budget)'),
        $target['published'] >= $target['total'] && $target['total'] > 0 => __('Tagesziel erreicht'),
        $target['published'] >= $target['expected_now'] => __('Auf Kurs'),
        default => __('Rückstand von :count Artikeln', ['count' => $target['expected_now'] - $target['published']]),
    };
@endphp

<x-filament-panels::page>
    <div wire:poll.60s="refreshOverview" class="space-y-content-8">
        {{-- A. Stoerungsstapel: hoechstens drei Baender, weitere Ursachen
             werden darunter gezaehlt (§1a). --}}
        @if ($snapshot['alarms'] !== [])
            {{-- Ein Kind des Aussenabstands, damit `space-y` den Abstand der
                 Zaehlzeile zum Stapel nicht ueberschreibt. --}}
            <div>
                <div class="flex flex-col gap-content-2" role="status">
                    @foreach (array_slice($snapshot['alarms'], 0, 3) as $index => $alarm)
                        <div
                            class="flex flex-wrap items-center gap-content-3 rounded-content-lg border-s-[3px] p-content-4"
                            style="background: var(--color-status-{{ $alarm['level'] }}-bg); border-color: var(--color-status-{{ $alarm['level'] }}-dot)"
                        >
                            <p class="text-content-body font-medium" style="color: var(--color-status-{{ $alarm['level'] }}-fg)">
                                {{ $alarm['message'] }}
                            </p>

                            {{-- Nur das oberste Band traegt die Schaltflaeche: alle
                                 Baender fuehren auf dieselbe Produktionsansicht. --}}
                            @if ($index === 0)
                                <a
                                    href="{{ \App\Filament\Content\Pages\Production::getUrl() }}"
                                    class="ms-auto h-9 rounded-content-md bg-content-600 px-content-4 py-2 text-content-table font-medium text-white"
                                >
                                    {{ __('Betroffene Portale ansehen') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (count($snapshot['alarms']) > 3)
                    {{-- Zaehlzeile ohne Rahmen und Flaeche, buendig unter dem Bandtext. --}}
                    <p
                        class="mt-content-2 text-content-label text-text-base"
                        style="padding-inline-start: calc(3px + var(--spacing-content-4))"
                    >
                        {{ trans_choice('{1}und eine weitere Ursache|[2,*]und :count weitere Ursachen', count($snapshot['alarms']) - 3, ['count' => count($snapshot['alarms']) - 3]) }}
                    </p>
                @endif
            </div>
        @endif

        {{-- B. Tagesziel — die wichtigste Flaeche des Panels. --}}
        <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
            <div class="grid gap-content-6 lg:grid-cols-12">
                <div class="lg:col-span-3">
                    <p class="text-content-metric font-semibold leading-content-tight text-text-strong">
                        {{ $target['published'] }} / {{ $target['total'] }}
                    </p>
                    <p class="mt-content-1 text-content-label text-text-muted">
                        {{ __('veröffentlichte Artikel heute') }}
                    </p>
                    @if (($target['refreshed'] ?? 0) > 0)
                        {{-- Nebenaussage (§1b): Aktualisierungen zaehlen nicht
                             gegen das Tagesziel und bekommen deshalb keine
                             zweite grosse Zahl, nur diese Zeile. --}}
                        <p class="text-content-label text-text-muted">
                            {{ trans_choice('{1}und eine Aktualisierung|[2,*]und :count Aktualisierungen', $target['refreshed'], ['count' => $target['refreshed']]) }}
                        </p>
                    @endif
                </div>

                <div class="lg:col-span-6">
                    <div class="relative h-3 w-full overflow-hidden rounded-content-sm bg-surface-sunken">
                        <div class="flex h-3 w-full">
                            @foreach ($barOrder as $status)
                                @php $count = $counts[$status->value] ?? 0; @endphp

                                @if ($count > 0)
                                    <span
                                        class="block h-3"
                                        style="width: {{ round(($count / $barTotal) * 100, 2) }}%; background: var(--color-status-{{ $status->value }}-fill)"
                                    ></span>
                                @endif
                            @endforeach
                        </div>

                        {{-- Sollwert der aktuellen Uhrzeit: vor Mittag ist ein
                             Rueckstand normal und darf nicht als Stoerung gelesen werden. --}}
                        <span
                            class="absolute top-0 h-3 w-px bg-text-strong"
                            style="inset-inline-start: {{ round($expectedShare * 100, 2) }}%"
                            title="{{ __('Sollwert dieser Uhrzeit: :count', ['count' => $target['expected_now']]) }}"
                        ></span>
                    </div>

                    <ul class="mt-content-3 flex flex-wrap gap-content-4">
                        @foreach ($barOrder as $status)
                            <li class="flex items-center gap-content-2 text-content-label text-text-base">
                                <span
                                    class="inline-block h-2 w-2 rounded-full"
                                    style="background: var(--color-status-{{ $status->value }}-dot)"
                                    aria-hidden="true"
                                ></span>
                                {{ $status->label() }}: {{ $counts[$status->value] ?? 0 }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="lg:col-span-3">
                    <p class="text-content-h2 font-semibold text-text-strong">{{ $verdict }}</p>
                    <p class="content-asof mt-content-1">
                        {{ __('Stand: :time Uhr', ['time' => $snapshot['generated_at']->format('H:i')]) }}
                    </p>
                </div>
            </div>
        </section>

        {{-- C. Portalraster: Portale mit Problemen zuerst. --}}
        <section>
            <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Portale') }}</h2>

            @if ($snapshot['portals'] === [])
                <p class="mt-content-3 rounded-content-lg bg-surface-card p-content-6 text-content-body text-text-muted shadow-content-card">
                    {{ __('Für diesen Zugang ist kein Portal freigeschaltet.') }}
                </p>
            @else
                <div class="mt-content-3 grid gap-content-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    @foreach ($snapshot['portals'] as $portal)
                        @php
                            $atRisk = ($portal['at_risk'] ?? 0) > 0;
                            $refreshed = $portal['refreshed'] ?? 0;
                            $riskTitle = __(':name — Tagesziel gefährdet', ['name' => $portal['name']]);

                            // Zugaengliche Beschriftung (§1a/§1b): erst die
                            // Stoerung, dann der Tagesstand, die Aktualisierung
                            // zuletzt. Das Zeichen ↻ wird nicht angesagt.
                            $tileLabel = $atRisk
                                ? __(':name, Tagesziel gefährdet, :published von :target veröffentlicht', ['name' => $portal['name'], 'published' => $portal['published'], 'target' => $portal['target']])
                                : __(':name, :published von :target veröffentlicht', ['name' => $portal['name'], 'published' => $portal['published'], 'target' => $portal['target']]);

                            if ($refreshed > 0) {
                                $tileLabel .= ', '.trans_choice('{1}ein Artikel aktualisiert|[2,*]:count Artikel aktualisiert', $refreshed, ['count' => $refreshed]);
                            }
                        @endphp

                        <a
                            href="{{ \App\Filament\Content\Pages\Production::getUrl(['portal' => [$portal['id']]]) }}"
                            class="flex h-[72px] flex-col justify-between rounded-content-lg bg-surface-card p-content-3 shadow-content-card"
                            @if ($atRisk || $refreshed > 0)
                                aria-label="{{ $tileLabel }}"
                                title="{{ $atRisk ? $riskTitle : $tileLabel }}"
                            @endif
                        >
                            <span class="flex items-center gap-content-2 min-w-0">
                                {{-- Marke fuer ein gefaehrdetes Tagesziel (§1a):
                                     nicht betroffene Kacheln bekommen keinen Platzhalter. --}}
                                @if ($atRisk)
                                    <span
                                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                                        style="background: var(--color-status-failed-dot)"
                                        aria-hidden="true"
                                    ></span>
                                @endif

                                <span class="min-w-0 truncate text-content-table font-semibold text-text-strong" title="{{ $atRisk ? $riskTitle : $portal['name'] }}">
                                    {{ $portal['name'] }}
                                </span>
                            </span>

                            <span class="flex items-center gap-content-1">
                                @foreach ($portal['dots'] as $dot)
                                    <span
                                        @class(['inline-block h-2.5 w-2.5 rounded-full', 'border border-line-strong' => $dot === null])
                                        @if ($dot !== null)
                                            style="background: var(--color-status-{{ $dot }}-dot)"
                                        @endif
                                        title="{{ $dot === null ? __('noch offen') : DisplayStatus::from($dot)->label() }}"
                                    ></span>
                                @endforeach

                                {{-- Zaehler und Aktualisierungsmarke brechen nie
                                     um; reicht der Platz nicht, wird die
                                     Punktreihe gekuerzt (§1b). --}}
                                <span class="ms-auto shrink-0 whitespace-nowrap text-content-label text-text-muted">
                                    {{ $portal['published'] }}/{{ $portal['target'] }}@if ($refreshed > 0)<span style="margin-inline: 4px" aria-hidden="true">·</span><span aria-hidden="true">↻</span> {{ $refreshed }}@endif
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- D. Kosten (nur owner) und Quellenlage. --}}
        <div class="grid gap-content-4 lg:grid-cols-2">
            @if ($this->canSeeCosts())
                <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                    <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Kosten heute') }}</h2>

                    @php
                        $costLevel = match (true) {
                            $cost['today_share'] >= 1.0 => 'failed',
                            $cost['today_share'] >= (float) config('content.budget.warn_threshold', 0.8) => 'review',
                            default => 'published',
                        };
                    @endphp

                    <p class="mt-content-2 text-content-metric font-semibold text-text-strong">
                        {{ number_format($cost['today'], 2, ',', '.') }} $
                    </p>

                    <div class="mt-content-3 h-3 w-full rounded-content-sm bg-surface-sunken">
                        <div
                            class="h-3 rounded-content-sm"
                            style="width: {{ min(100, round($cost['today_share'] * 100, 2)) }}%; background: var(--color-status-{{ $costLevel }}-dot)"
                        ></div>
                    </div>

                    <p class="mt-content-2 text-content-label text-text-muted">
                        {{ __(':spent $ von :budget $ · Monat: :month $ von :monthly $', [
                            'spent' => number_format($cost['today'], 2, ',', '.'),
                            'budget' => number_format($cost['daily_budget'], 2, ',', '.'),
                            'month' => number_format($cost['month'], 2, ',', '.'),
                            'monthly' => number_format($cost['monthly_budget'], 2, ',', '.'),
                        ]) }}
                    </p>

                    @if ($cost['today_share'] >= 1.0)
                        <p class="mt-content-2 text-content-body font-medium" style="color: var(--color-status-failed-fg)">
                            {{ __('Produktion angehalten') }}
                        </p>
                    @endif
                </section>
            @endif

            <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Quellenlage') }}</h2>

                @forelse ($snapshot['sources'] as $source)
                    <div class="mt-content-2 flex items-center gap-content-2 border-t border-line-soft pt-content-2 text-content-table">
                        <span
                            class="inline-block h-2 w-2 flex-none rounded-full"
                            style="background: var(--color-status-{{ $source['display_status'] }}-dot)"
                            aria-hidden="true"
                        ></span>
                        <span class="text-text-strong">{{ $source['provider'] }}</span>
                        <span class="text-text-muted">{{ $source['label'] }}</span>
                        <span class="content-asof ms-auto">
                            {{ $source['as_of'] ? __('Stand: :time', ['time' => $source['as_of']]) : __('noch kein Abruf') }}
                        </span>
                    </div>
                @empty
                    <p class="mt-content-2 text-content-body text-text-muted">
                        {{ __('Es ist noch keine Quelle gelaufen. Der erste Abruf startet mit dem Tageslauf.') }}
                    </p>
                @endforelse
            </section>
        </div>

        {{-- E. Tagesbericht des letzten Laufs (#22): Bilanz je Portal. --}}
        @php $report = $this->getDailyReport(); @endphp

        @if ($report !== null)
            <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                <h2 class="text-content-h2 font-semibold text-text-strong">
                    {{ __('Tagesbericht vom :date', ['date' => \Illuminate\Support\Carbon::parse($report['date'])->format('d.m.Y')]) }}
                </h2>

                <p class="mt-content-1 text-content-body text-text-muted">
                    {{ __(':published von :target Artikeln, :failed offene Slots.', [
                        'published' => $report['totals']['published'],
                        'target' => $report['totals']['target'],
                        'failed' => $report['totals']['failed_slots'],
                    ]) }}
                    @if ($this->canSeeCosts())
                        {{ __('Kosten Tag :day USD, Monat :month USD.', [
                            'day' => number_format((float) $report['cost']['today'], 2),
                            'month' => number_format((float) $report['cost']['month'], 2),
                        ]) }}
                    @endif
                </p>

                @foreach ($report['portals'] as $portal)
                    <div class="mt-content-2 flex items-start gap-content-3 border-t border-line-soft pt-content-2 text-content-table">
                        <span class="w-40 flex-none truncate text-text-strong">{{ $portal['name'] }}</span>
                        <span class="w-20 flex-none text-text-muted">{{ $portal['published'] }}/{{ $portal['target'] }}</span>
                        <span class="text-text-base">
                            @forelse ($portal['failed_slots'] as $slot)
                                {{ $slot['title'] }}: {{ $slot['reason'] }}@if (! $loop->last), @endif
                            @empty
                                {{ __('ohne Beanstandung') }}
                            @endforelse
                        </span>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- F. Letzte Ereignisse: nur Ausnahmen und Meilensteine. --}}
        <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
            <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Letzte Ereignisse') }}</h2>

            @forelse ($snapshot['events'] as $event)
                <div class="mt-content-2 flex items-start gap-content-3 border-t border-line-soft pt-content-2 text-content-table">
                    <span class="w-12 flex-none text-text-muted">{{ $event['time'] }}</span>
                    <span class="w-40 flex-none truncate text-text-muted">{{ $event['portal'] }}</span>
                    <span class="text-text-base">{{ $event['message'] }}</span>
                </div>
            @empty
                <p class="mt-content-2 text-content-body text-text-muted">
                    {{ __('Heute ist noch nichts erschienen. Der Tageslauf startet um :time Uhr.', [
                        'time' => config('content.pipeline.schedule.generate_at', '05:00'),
                    ]) }}
                </p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
