{{--
    Pruefung (#20), design/content-dashboard.md, §4.

    Zwei Spalten, kein Listen-Detail-Sprung: links die Warteschlange (laengste
    Wartezeit zuerst), rechts die Pruefflaeche mit Vorschau, Qualitaetsreport,
    Faktencheck und Quellen. Unter 1024 px werden daraus zwei Schritte.

    Tastatur: J/K blaettern, F gibt frei, N erzeugt neu, V verwirft. Die
    Belegung steht dauerhaft in der Entscheidungsleiste.
--}}
<div
    class="pb-content-8"
    x-data
    x-on:keydown.window="
        if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
        if ($event.key === 'j') { $event.preventDefault(); $wire.step(1) }
        if ($event.key === 'k') { $event.preventDefault(); $wire.step(-1) }
    "
>
    @include('content.partials.filter-bar', [
        'groups' => [
            ['key' => 'portals', 'label' => __('Portal'), 'options' => $portalOptions, 'empty' => __('Alle Portale')],
            ['key' => 'branches', 'label' => __('Branche'), 'options' => $branchOptions, 'empty' => __('Alle Branchen')],
        ],
        'meta' => trans_choice('{0}nichts zu prüfen|{1}1 Artikel wartet|[2,*]:count Artikel warten', count($entries), ['count' => count($entries)]),
    ])

    @if ($entries === [])
        {{-- Leer und gut: kein graues Trauerbild, sondern der letzte Erfolg. --}}
        <div class="rounded-content-lg bg-surface-card p-content-8 text-center shadow-content-card">
            <p class="text-content-h2 font-semibold text-text-strong">{{ __('Nichts zu prüfen.') }}</p>
            <p class="mt-content-2 text-content-body text-text-muted">
                @if ($lastDecisionAt)
                    {{ __('Zuletzt entschieden: :time.', ['time' => \Illuminate\Support\Carbon::parse($lastDecisionAt)->translatedFormat('d.m.Y H:i')]) }}
                @else
                    {{ __('Bisher wurde kein Artikel zur Prüfung vorgelegt.') }}
                @endif
            </p>
        </div>
    @else
        <div class="flex flex-col gap-content-4 lg:flex-row lg:items-start">
            {{-- Warteschlange --}}
            <nav
                class="w-full flex-none rounded-content-lg bg-surface-card shadow-content-card lg:w-[320px]"
                aria-label="{{ __('Warteschlange') }}"
            >
                <ul class="divide-y divide-line-soft">
                    @foreach ($entries as $entry)
                        <li wire:key="queue-{{ $entry['key'] }}">
                            <button
                                type="button"
                                wire:click="select('{{ $entry['key'] }}')"
                                @class([
                                    'flex min-h-[72px] w-full flex-col items-start gap-content-1 border-s-[3px] px-content-3 py-content-2 text-start',
                                    'border-content-600 bg-content-50' => $entry['key'] === $selected,
                                    'border-transparent' => $entry['key'] !== $selected,
                                ])
                                aria-current="{{ $entry['key'] === $selected ? 'true' : 'false' }}"
                            >
                                <span class="flex w-full items-center justify-between gap-content-2">
                                    <span class="text-content-label text-text-muted">{{ $entry['tenant'] }}</span>
                                    @if ($entry['score'] !== null)
                                        <span
                                            class="text-content-table font-semibold"
                                            style="color: var(--color-score-{{ $entry['score_level'] ?? 'mid' }})"
                                        >{{ number_format($entry['score'], 0, ',', '.') }}</span>
                                    @endif
                                </span>
                                <span class="line-clamp-2 text-content-table font-medium text-text-strong">{{ $entry['title'] }}</span>
                                <span class="flex items-center gap-content-2 text-content-label text-text-muted">
                                    <span>{{ __('wartet :for', ['for' => $entry['waiting_for'] ?? '—']) }}</span>
                                    @if ($entry['is_refresh'])
                                        <span>· {{ __('Aktualisierung') }}</span>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Pruefflaeche --}}
            <div class="min-w-0 flex-1 lg:max-w-[1120px]">
                @if ($detail === null)
                    <div class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                        <p class="text-content-body text-text-muted">
                            {{ __('Dieser Artikel ist nicht mehr vorhanden. Bitte einen anderen wählen.') }}
                        </p>
                    </div>
                @else
                    @include('content.pages.review-detail', ['detail' => $detail])
                @endif
            </div>
        </div>
    @endif

    <div wire:loading wire:target="select,step,portals,branches" class="mt-content-2 text-content-label text-text-muted">
        {{ __('Prüfblatt wird geladen …') }}
    </div>

    <x-filament-actions::modals />
</div>
