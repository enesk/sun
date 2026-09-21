{{--
    Prüfblatt (#16, design/guide-dashboard.md §8.2), von oben:
    Kopf · Anlass · Stand-Zeile · Changelog · Abschnitte (Diff + Quellen) ·
    FAQ/Kurzantwort/Meta · Qualitätsbericht und Faktenabgleich · Aktionsleiste.
    Diff abschnittsweise anhand der Abschnitts-ids der gesperrten Gliederung.

    Layout (§8.1, #33): ab 1280 px links die Warteschlange (360 px, eigener
    Scrollbereich), 1024–1279 px eine 64-px-Leiste mit Zähler, Pfeilen und
    aufklappbarer Warteschlange, darunter einspaltig.

    Einzeltasten (§8.2, #33): J/K/F/S/V/? klicken die Schaltfläche mit
    passendem data-shortcut — nur im Hauptbereich, nie in Eingabefeldern oder
    Dialogen, nicht bei abgeschaltetem Schalter im Nutzermenü. Vor dem Klick
    bekommt die Schaltfläche den Fokus, damit er nach Esc dorthin zurückgeht.
--}}
@use('App\Guide\Support\Usd')
@php
    $sheet = $this->sheet();
    $tz = config('guide.timezone');
    $report = $sheet['report'];
    $changedSections = collect($sheet['sections'])->where('changed', true);
    $unchangedSections = collect($sheet['sections'])->where('changed', false);
    $factStatus = [
        'belegt' => ['published', __('belegt')],
        'gerundet' => ['scheduled', __('gerundet')],
        'widerspruch' => ['failed', __('Widerspruch')],
        'unbelegt' => ['review', __('unbelegt')],
    ];
@endphp
@php
    $queue = $this->queue();
    $currentKey = $this->currentKey();
    $position = collect($queue)->search(fn (array $row): bool => $row['__key'] === $currentKey);
    $networkWide = app(\App\Guide\Services\TopicDirectory::class)->isNetworkWide();
@endphp
<x-filament-panels::page>
    <div
        x-data="{
            enabled: @js($this->shortcutsEnabled()),
            keys: ['j', 'k', 'f', 's', 'v', '?'],
            handle(event) {
                if (! this.enabled || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
                const key = event.key === '?' ? '?' : String(event.key).toLowerCase();
                if (! this.keys.includes(key) || (key !== '?' && event.shiftKey)) return;
                const target = event.target;
                if (target.closest('input, textarea, select, [contenteditable], .fi-modal, [role=dialog]')) return;
                if (target !== document.body && ! target.closest('.fi-main')) return;
                const button = this.$root.querySelector(`[data-shortcut='${key}']`);
                if (! button || button.disabled || button.getAttribute('aria-disabled') === 'true') return;
                event.preventDefault();
                button.focus();
                button.click();
            },
            focusHeading() {
                this.$nextTick(() => {
                    const heading = document.querySelector('.fi-header-heading');
                    if (! heading) return;
                    heading.setAttribute('tabindex', '-1');
                    heading.focus();
                });
            },
        }"
        x-on:keydown.window="handle($event)"
        x-on:guide-review-entry-shown.window="focusHeading()"
        class="lg:grid lg:grid-cols-[4rem_minmax(0,1fr)] lg:gap-content-6 xl:grid-cols-[360px_minmax(0,1fr)]"
    >
    {{-- Warteschlange (§8.1): ab 1280 px Spalte, darunter bis 1024 px Leiste --}}
    <aside class="hidden lg:block" aria-label="{{ __('Warteschlange') }}">
        <div class="sticky top-20 hidden max-h-[calc(100vh-6rem)] flex-col overflow-hidden rounded-content-lg bg-surface-card shadow-content-card xl:flex">
            <h2 class="border-b border-line-soft px-content-4 py-content-3 text-content-h3 font-semibold text-text-strong">
                {{ __('Warteschlange (:count)', ['count' => count($queue)]) }}
            </h2>
            <div class="min-h-0 overflow-y-auto">
                @if ($queue === [])
                    <p class="px-content-4 py-content-3 text-content-table text-text-base">{{ __('Nichts zu prüfen.') }}</p>
                @else
                    @include('content.guide.partials.review-queue', ['queue' => $queue, 'current' => $currentKey, 'networkWide' => $networkWide])
                @endif
            </div>
        </div>

        <div class="sticky top-20 flex flex-col items-center gap-content-2 xl:hidden" x-data="{ open: false }" x-on:keydown.escape="open = false">
            <span class="text-content-label tabular-nums text-text-base">{{ $position !== false ? ($position + 1).' / '.count($queue) : '– / '.count($queue) }}</span>
            <button type="button" wire:click="mountAction('previous')" class="flex size-11 items-center justify-center rounded-content-sm text-text-base hover:bg-surface-sunken" aria-label="{{ __('Vorheriger Eintrag') }}">
                <x-filament::icon icon="heroicon-m-chevron-up" class="size-5" />
            </button>
            <button type="button" wire:click="mountAction('skip')" class="flex size-11 items-center justify-center rounded-content-sm text-text-base hover:bg-surface-sunken" aria-label="{{ __('Nächster Eintrag') }}">
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-5" />
            </button>
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex size-11 items-center justify-center rounded-content-sm text-text-base hover:bg-surface-sunken" aria-label="{{ __('Warteschlange zeigen') }}">
                <x-filament::icon icon="heroicon-m-queue-list" class="size-5" />
            </button>
            <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute start-full top-0 z-20 ms-content-2 max-h-[calc(100vh-6rem)] w-[360px] overflow-y-auto rounded-content-lg bg-surface-card shadow-content-card">
                <h2 class="border-b border-line-soft px-content-4 py-content-3 text-content-h3 font-semibold text-text-strong">{{ __('Warteschlange (:count)', ['count' => count($queue)]) }}</h2>
                @include('content.guide.partials.review-queue', ['queue' => $queue, 'current' => $currentKey, 'networkWide' => $networkWide])
            </div>
        </div>
    </aside>

    <div class="min-w-0" data-review-sheet>
    <div class="flex flex-col gap-content-6 pb-24">
        {{-- 1. Kopf --}}
        <header class="flex flex-col gap-content-2">
            <div class="flex flex-wrap items-center gap-content-2">
                <span class="content-status content-status--review">{{ __('Zur Prüfung') }}</span>
                @if ($sheet['category'])
                    <span class="text-content-table text-text-muted">{{ $sheet['category'] }}</span>
                @endif
            </div>
            <p class="text-content-table text-text-base">
                {{ collect([
                    $sheet['tenant_name'],
                    $sheet['mode_label'],
                    $sheet['run_at'] ? __('Lauf vom :date', ['date' => \Illuminate\Support\Carbon::parse($sheet['run_at'])->timezone($tz)->format('d.m., H:i')]) : null,
                    filament()->auth()->user()?->canSeeContentCosts() ? Usd::format($sheet['cost']) : null,
                    $sheet['new_version'] ? __('Fassung :new', ['new' => $sheet['new_version']]).($sheet['old_version'] ? ' ↔ '.__('online: Fassung :old', ['old' => $sheet['old_version']]) : '') : null,
                ])->filter()->implode(' · ') }}
            </p>
        </header>

        @if (! $sheet['is_review'] || ($sheet['decision']['decision'] ?? null) === 'approved')
            <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)" role="status">
                {{ filled($sheet['decision']['user_name'] ?? null)
                    ? __('Über diesen Lauf hat :name bereits entschieden.', ['name' => $sheet['decision']['user_name']])
                    : __('Über diesen Lauf wurde bereits entschieden.') }}
                <a href="{{ \App\Guide\Filament\Resources\ReviewRunResource::getUrl() }}" class="ms-content-2 font-medium text-content-700 underline underline-offset-2">{{ __('Zur Warteschlange') }}</a>
            </div>
        @endif

        @if ($sheet['awaits_outline'])
            <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)">
                {{ __('Der Lauf wartet auf die Sperre der Gliederung.') }}
                <a href="{{ \App\Guide\Filament\Resources\TopicResource::detailUrl($sheet['topic_key'], 'gliederung') }}" class="ms-content-2 font-medium text-content-700 underline underline-offset-2">{{ __('Gliederung öffnen') }}</a>
            </div>
        @endif

        {{-- Anlass: ein Band, Quellen-Satz zuerst (§5.7.5 A.1) --}}
        @if ($sheet['reasons'] !== [])
            <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)">
                @unless (\Illuminate\Support\Str::startsWith($sheet['reasons'][0], __('Das Qualitätsgate')))
                    {{ __('Das Qualitätsgate hat nicht freigegeben:') }}
                @endunless
                {{ implode(' ', $sheet['reasons']) }}
            </div>
        @elseif (! $sheet['has_report'] && $sheet['has_version'])
            <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)">
                {{ __('Kein Prüfbericht vorhanden — bitte Quellen selbst sichten.') }}
            </div>
        @endif

        @if ($this->missingChangelog())
            <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot)" role="alert">
                {{ __('Ohne Changelog-Eintrag darf sich das Aktualisiert-Datum nicht ändern. Bitte den Satz unter „Was ist neu?“ ergänzen oder „Zurück zum Schreiben …“.') }}
            </div>
        @endif

        {{-- 2. Stand-Zeile, wie sie nach der Freigabe gälte --}}
        @if ($sheet['has_version'])
            <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                <h2 class="text-content-label font-medium uppercase tracking-wide text-text-muted">{{ __('Stand-Zeile nach Freigabe') }}</h2>
                <p class="mt-content-1 text-content-body text-text-base">
                    @if ($sheet['mode'] === 'create')
                        {{ __('Veröffentlicht am :date', ['date' => now($tz)->translatedFormat('j. F Y')]) }}
                    @elseif ($sheet['changelog']['new'] !== null)
                        {{ __('Aktualisiert am :date · Was ist neu?', ['date' => now($tz)->translatedFormat('j. F Y')]) }}
                    @else
                        {{ __('Geprüft am :date — das Aktualisiert-Datum bleibt stehen.', ['date' => now($tz)->translatedFormat('j. F Y')]) }}
                    @endif
                </p>
            </section>
        @endif

        {{-- 3. Changelog-Vorschlag --}}
        @if ($sheet['changelog']['new'] !== null || $sheet['changelog']['previous'] !== [] || $this->missingChangelog())
            <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                <h2 class="text-content-h3 font-semibold text-text-strong">{{ __('Was ist neu?') }}</h2>
                <ul class="mt-content-2 flex flex-col gap-content-2 text-content-table text-text-base">
                    @if ($sheet['changelog']['new'] !== null)
                        <li class="border-s-[3px] ps-content-3" style="border-color: var(--color-status-review-dot)">
                            <span class="text-content-label text-text-muted">{{ __('Neuer Eintrag') }}</span>
                            <span class="block">{{ $sheet['changelog']['new']['summary'] ?? $sheet['changelog']['new']['text'] ?? '' }}</span>
                        </li>
                    @endif
                    @foreach ($sheet['changelog']['previous'] as $entry)
                        <li class="ps-content-3 text-text-muted">
                            {{ collect([$entry['date'] ?? null, $entry['summary'] ?? $entry['text'] ?? null])->filter()->implode(' — ') }}
                        </li>
                    @endforeach
                </ul>
                <div class="mt-content-3">{{ $this->editChangelogAction }}</div>
            </section>
        @endif

        {{-- 4. Abschnitte: geänderte offen, unveränderte eingeklappt --}}
        @if ($sheet['has_version'])
            <section class="flex flex-col gap-content-4">
                <h2 class="text-content-h2 font-semibold text-text-strong">
                    {{ trans_choice('{0} Keine Abschnitte geändert|{1} Ein Abschnitt geändert|[2,*] :count Abschnitte geändert', $changedSections->count(), ['count' => $changedSections->count()]) }}
                </h2>

                @foreach ($changedSections as $section)
                    <article class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card" wire:key="section-{{ $section['id'] }}">
                        <header class="flex flex-wrap items-baseline justify-between gap-content-2">
                            <h3 @class(['font-semibold text-text-strong', 'text-content-h3' => $section['level'] === 2, 'text-content-body' => $section['level'] !== 2])>
                                <span class="text-content-label text-text-muted">H{{ $section['level'] }}</span>
                                {{ \App\Guide\Services\GuidePageData::replaceYear($section['heading']) }}
                            </h3>
                            @foreach ($section['reasons'] as $reason)
                                <span class="text-[13px] text-text-base">{{ $reason }}</span>
                            @endforeach
                        </header>

                        <div class="mt-content-3 grid grid-cols-1 gap-content-4 2xl:grid-cols-12">
                            <div class="min-w-0 2xl:col-span-8">
                                @include('content.guide.partials.section-diff', ['diff' => $section['diff']])
                            </div>

                            <div class="2xl:col-span-4">
                                <details class="2xl:hidden" @if (collect($section['sources'])->contains('unreachable', true)) open @endif>
                                    <summary class="cursor-pointer text-content-table font-medium text-text-base">{{ __('Quellen (:count)', ['count' => count($section['sources'])]) }}</summary>
                                    @include('content.guide.partials.review-sources', ['sources' => $section['sources']])
                                </details>
                                <div class="hidden 2xl:block">
                                    <h4 class="text-content-label font-medium uppercase tracking-wide text-text-muted">{{ __('Quellen zu diesem Abschnitt') }}</h4>
                                    @include('content.guide.partials.review-sources', ['sources' => $section['sources']])
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach

                @if ($unchangedSections->isNotEmpty())
                    <details class="rounded-content-lg bg-surface-sunken px-content-4 py-content-3">
                        <summary class="cursor-pointer text-content-table text-text-base">
                            {{ trans_choice('{1} Ein Abschnitt unverändert|[2,*] :count Abschnitte unverändert', $unchangedSections->count(), ['count' => $unchangedSections->count()]) }}
                        </summary>
                        <ul class="mt-content-2 flex flex-col gap-content-1 text-content-table text-text-base">
                            @foreach ($unchangedSections as $section)
                                <li @class(['ps-content-4' => $section['level'] !== 2])>{{ \App\Guide\Services\GuidePageData::replaceYear($section['heading']) }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            {{-- 5. Kurzantwort, FAQ, Meta --}}
            @foreach ($sheet['extras'] as $extra)
                <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                    <h3 class="text-content-h3 font-semibold text-text-strong">{{ $extra['label'] }}</h3>
                    <div class="mt-content-3">
                        @include('content.guide.partials.section-diff', ['diff' => $extra['diff']])
                    </div>
                </section>
            @endforeach
        @endif

        {{-- Qualitätsbericht und Faktenabgleich --}}
        @if ($sheet['has_report'])
            <section class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Qualitätsbericht') }}</h2>
                <dl class="mt-content-3 flex flex-wrap gap-x-content-8 gap-y-content-2 text-content-table text-text-base">
                    <div>
                        <dt class="text-content-label text-text-muted">{{ __('Gesamt') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ $report['final_score'] ?? '–' }} <span class="font-normal text-text-muted">/ {{ __('Schwelle :t', ['t' => $report['threshold'] ?? '–']) }}</span></dd>
                    </div>
                    @foreach (['rubric' => __('Rubrik'), 'fact' => __('Fakten'), 'lint' => __('SEO-Regeln'), 'readability' => __('Lesbarkeit')] as $key => $label)
                        @if (isset($report['scores'][$key]))
                            <div>
                                <dt class="text-content-label text-text-muted">{{ $label }}</dt>
                                <dd class="tabular-nums">{{ (int) round((float) $report['scores'][$key]) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div>
                        <dt class="text-content-label text-text-muted">{{ __('Nachbesserungen') }}</dt>
                        <dd class="tabular-nums">{{ $report['fix_runs'] }}</dd>
                    </div>
                </dl>

                @if ($report['lint'] !== [])
                    <h3 class="mt-content-4 text-content-h3 font-semibold text-text-strong">{{ __('Nicht erfüllte Regeln') }}</h3>
                    <ul class="mt-content-2 list-disc ps-content-6 text-content-table text-text-base">
                        @foreach ($report['lint'] as $row)
                            <li>{{ $row['message'] ?? $row['rule'] ?? '' }}@if ($row['blocking'] ?? false) <strong>({{ __('blockiert') }})</strong>@endif</li>
                        @endforeach
                    </ul>
                @endif

                @if ($report['rubric'] !== [])
                    <h3 class="mt-content-4 text-content-h3 font-semibold text-text-strong">{{ __('Rubrik') }}</h3>
                    <ul class="mt-content-2 flex flex-col gap-content-1 text-content-table text-text-base">
                        @foreach ($report['rubric'] as $row)
                            <li><span class="tabular-nums font-medium">{{ $row['score'] ?? '–' }}</span> {{ $row['key'] ?? '' }}@if (filled($row['comment'] ?? null)) — {{ $row['comment'] }}@endif</li>
                        @endforeach
                    </ul>
                @endif

                @if ($report['fact_check'] !== [])
                    <details class="mt-content-4" @if (collect($report['fact_check'])->whereIn('status', ['widerspruch', 'unbelegt'])->isNotEmpty()) open @endif>
                        <summary class="cursor-pointer text-content-h3 font-semibold text-text-strong">{{ __('Faktenabgleich (:count)', ['count' => count($report['fact_check'])]) }}</summary>
                        <table class="mt-content-2 w-full text-content-table">
                            <thead>
                                <tr class="border-b border-line-soft text-content-label text-text-muted">
                                    <th class="py-content-2 text-start font-medium">{{ __('Wert im Text') }}</th>
                                    <th class="py-content-2 text-start font-medium">{{ __('Ergebnis') }}</th>
                                    <th class="py-content-2 text-start font-medium">{{ __('Fakt') }}</th>
                                    <th class="hidden py-content-2 text-start font-medium lg:table-cell">{{ __('Satz') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft align-top text-text-base">
                                @foreach (collect($report['fact_check'])->sortBy(fn ($row) => array_search($row['status'] ?? '', ['widerspruch', 'unbelegt', 'gerundet', 'belegt'], true)) as $row)
                                    @php([$tone, $label] = $factStatus[$row['status'] ?? ''] ?? ['idea', (string) ($row['status'] ?? '–')])
                                    <tr>
                                        <td class="py-content-2 pe-content-3 tabular-nums">{{ $row['wert'] ?? '' }}</td>
                                        <td class="py-content-2 pe-content-3"><span class="content-status content-status--{{ $tone }}">{{ $label }}</span></td>
                                        <td class="py-content-2 pe-content-3">{{ collect([$row['fact_key'] ?? null, $row['fact_value'] ?? null])->filter()->implode(': ') ?: '–' }}</td>
                                        <td class="hidden py-content-2 lg:table-cell">{{ \Illuminate\Support\Str::limit((string) ($row['sentence'] ?? ''), 160) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif

                @if ($report['link_check'] !== [])
                    <h3 class="mt-content-4 text-content-h3 font-semibold text-text-strong">{{ __('Links') }}</h3>
                    <ul class="mt-content-2 flex flex-col gap-content-1 text-content-table text-text-base">
                        @foreach ($report['link_check'] as $row)
                            <li class="break-all">{{ $row['url'] ?? '' }} — {{ __('nicht erreichbar') }}@if (filled($row['code'] ?? null)) ({{ $row['code'] }})@endif</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>

    {{-- 6. Aktionsleiste, klebend unten --}}
    <div class="sticky bottom-0 z-10 flex min-h-16 flex-wrap items-center gap-content-3 rounded-t-content-lg border-t border-line-soft bg-surface-card px-content-4 py-content-3">
        {{ $this->approveAction }}
        {{ $this->rewriteAction }}
        {{ $this->discardAction }}
        <span class="ms-auto flex items-center gap-content-3">
            {{ $this->previousAction }}
            {{ $this->skipAction }}
            {{ $this->shortcutsAction }}
        </span>
        <span class="hidden w-full text-content-label text-text-muted xl:block">
            {{ $this->shortcutsEnabled()
                ? __('Tasten: F freigeben · S neu schreiben · V verwerfen · J nächster · K vorheriger · ? Übersicht')
                : __('Einzeltasten sind abgeschaltet (Nutzermenü).') }}
        </span>
    </div>
    </div>
    </div>
</x-filament-panels::page>
