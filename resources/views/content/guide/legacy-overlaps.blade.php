{{--
    Themen › Altartikel (#25, design/guide-dashboard.md §5.6).
    Gruppen je Thema mit h3-Kopf; eine Zeile je Altartikel. Ab 1024 px als
    Tabelle mit vier Spalten (44/12/12/32 %), darunter als Karte (§5.6.8).
    Nach einer Entscheidung setzt die Seite den Fokus über das Ereignis
    guide-overlap-focus (§5.6.9) — nie auf body.
--}}
@php
    $groups = $this->groups();
    $ids = $this->alertIds();
    $allSelected = $ids !== [] && array_diff($ids, array_map('intval', $selected)) === [];
@endphp
<x-filament-panels::page>
    <div
        x-data
        x-on:guide-overlap-focus.window="
            const target = $event.detail.target;
            setTimeout(() => {
                const el = document.getElementById(target)
                    ?? $root.querySelector('[data-overlap-heading]')
                    ?? document.getElementById('overlap-empty');
                el?.focus();
            }, 250);
        "
        class="flex flex-col gap-4"
    >
        @if ($groups === [])
            <div id="overlap-empty" tabindex="-1" class="flex items-start gap-3 rounded-xl border border-line-strong bg-surface-card p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-content-600">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-status-published-dot" />
                <div>
                    <p class="text-[15px] font-medium text-text-strong">{{ __('Keine offenen Überschneidungen mit Altartikeln.') }}</p>
                    <p class="mt-1 text-sm text-text-muted">{{ __('Neue Überschneidungen erscheinen hier nach dem nächsten Abgleich.') }}</p>
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4 text-sm text-text-base">
                    <label class="flex min-h-11 items-center gap-2 lg:min-h-0">
                        <x-filament::input.checkbox :checked="$allSelected" wire:click="toggleAll" />
                        {{ __('Alle auswählen') }}
                    </label>
                    <span class="flex items-center gap-2" role="group" aria-label="{{ __('Sortierung') }}">
                        {{ __('Sortiert nach') }}
                        <button type="button" wire:click="sortBy('similarity')" aria-pressed="{{ $sort !== 'reported' ? 'true' : 'false' }}" @class(['underline underline-offset-2', 'font-semibold text-text-strong' => $sort !== 'reported', 'text-content-700' => $sort === 'reported'])>{{ __('Ähnlichkeit') }}</button>
                        ·
                        <button type="button" wire:click="sortBy('reported')" aria-pressed="{{ $sort === 'reported' ? 'true' : 'false' }}" @class(['underline underline-offset-2', 'font-semibold text-text-strong' => $sort === 'reported', 'text-content-700' => $sort !== 'reported'])>{{ __('Gemeldet') }}</button>
                    </span>
                </div>
                {{ $this->dismissSelectedAction }}
            </div>

            @foreach ($groups as $group)
                <section class="rounded-xl border border-line-strong bg-surface-card" wire:key="overlap-group-{{ $group['key'] }}" aria-labelledby="{{ $group['heading_id'] }}">
                    <header class="flex flex-col gap-1 border-b border-line-soft px-4 py-3 lg:flex-row lg:items-baseline lg:justify-between lg:gap-4">
                        <div class="min-w-0">
                            <h3 id="{{ $group['heading_id'] }}" tabindex="-1" data-overlap-heading class="text-[15px] font-semibold text-text-strong focus:outline-none focus-visible:ring-2 focus-visible:ring-content-600">
                                @if ($group['detail_url'])
                                    <a href="{{ $group['detail_url'] }}" class="hover:underline">{{ $group['question'] }}</a>
                                @else
                                    {{ $group['question'] }}
                                @endif
                            </h3>
                            @if ($group['show_portal'])
                                <p class="text-xs text-text-muted">{{ $group['tenant_name'] }}</p>
                            @endif
                        </div>
                        <p class="text-sm text-text-base lg:shrink-0 lg:text-right">
                            @if ($group['target_path'])
                                {{ __('Veröffentlicht unter') }}
                                @if ($group['target_url'])
                                    <a href="{{ $group['target_url'] }}" target="_blank" rel="noopener" class="font-mono text-[13px] text-content-700 underline underline-offset-2 [overflow-wrap:anywhere]">{{ $group['target_path'] }}</a>
                                @else
                                    <span class="font-mono text-[13px] [overflow-wrap:anywhere]">{{ $group['target_path'] }}</span>
                                @endif
                            @elseif ($group['topic_exists'])
                                {{ __('Noch kein veröffentlichter Artikel') }}
                            @endif
                        </p>
                    </header>

                    <div class="hidden px-4 pt-2 text-xs font-medium text-text-muted lg:grid lg:grid-cols-[2rem_44fr_12fr_12fr_32fr] lg:gap-4" aria-hidden="true">
                        <span></span>
                        <span>{{ __('Altartikel') }}</span>
                        <span class="text-right">{{ __('Ähnlichkeit') }}</span>
                        <span>{{ __('Gemeldet') }}</span>
                        <span class="text-right">{{ __('Entscheidung') }}</span>
                    </div>

                    <ul class="divide-y divide-line-soft">
                        @foreach ($group['rows'] as $row)
                            @php $arguments = ['alert' => $row['id']]; @endphp
                            <li
                                id="{{ $row['row_id'] }}"
                                tabindex="-1"
                                wire:key="{{ $row['row_id'] }}"
                                class="grid grid-cols-[2rem_1fr] gap-x-2 gap-y-3 p-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-content-600 lg:min-h-14 lg:grid-cols-[2rem_44fr_12fr_12fr_32fr] lg:items-center lg:gap-4 lg:py-2"
                            >
                                <div class="pt-1 lg:pt-0">
                                    <x-filament::input.checkbox wire:model.live="selected" value="{{ $row['id'] }}" :aria-label="__('Auswählen: :title', ['title' => $row['post_title']])" />
                                </div>

                                <div class="min-w-0">
                                    <p class="line-clamp-2 text-base font-semibold text-text-strong lg:text-[15px] lg:font-normal">{{ $row['post_title'] }}</p>
                                    @if ($row['post_url'])
                                        <a href="{{ $row['post_url'] }}" target="_blank" rel="noopener" aria-label="{{ __('Altartikel „:title“ in neuem Fenster öffnen', ['title' => $row['post_title']]) }}" class="mt-0.5 inline-flex items-start gap-1 font-mono text-xs text-text-muted hover:underline [overflow-wrap:anywhere]">
                                            <span class="min-w-0">{{ $row['post_path'] }}</span>
                                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-4 w-4 shrink-0" />
                                        </a>
                                    @else
                                        <p class="mt-0.5 font-mono text-xs text-text-muted [overflow-wrap:anywhere]">{{ $row['post_path'] }}</p>
                                    @endif
                                </div>

                                {{-- Mobil: eine Zeile „87 % Titelähnlichkeit · 21.09.2026“ --}}
                                <p class="col-start-2 text-sm text-text-base lg:hidden">
                                    {{ __(':percent % Titelähnlichkeit', ['percent' => round($row['similarity'])]) }}@if ($row['similarity'] < \App\Guide\Filament\Pages\LegacyOverlaps::WEAK_SIMILARITY) ({{ __('schwach') }})@endif
                                    · {{ $row['last_seen'] ?? '–' }}
                                    @if ($row['occurrences'] > 1)
                                        · {{ __(':count × gemeldet', ['count' => $row['occurrences']]) }}
                                    @endif
                                </p>

                                <div class="hidden text-right lg:block">
                                    <p class="text-[15px] tabular-nums text-text-strong">{{ round($row['similarity']) }} %</p>
                                    <p class="text-xs text-text-muted">{{ __('Titel') }}</p>
                                    @if ($row['similarity'] < \App\Guide\Filament\Pages\LegacyOverlaps::WEAK_SIMILARITY)
                                        <p class="text-xs text-text-base">{{ __('schwach') }}</p>
                                    @endif
                                </div>

                                <div class="hidden lg:block">
                                    <p class="text-sm text-text-base">{{ $row['last_seen'] ?? '–' }}</p>
                                    @if ($row['occurrences'] > 1)
                                        <p class="text-xs text-text-muted">{{ __(':count × gemeldet', ['count' => $row['occurrences']]) }}</p>
                                    @endif
                                </div>

                                <div class="col-start-2 flex flex-col items-stretch gap-1 max-lg:[&_.fi-btn]:min-h-11 max-lg:[&_.fi-btn]:w-full max-lg:[&_.fi-link]:min-h-11 lg:col-start-auto lg:flex-row lg:flex-wrap lg:items-center lg:justify-end lg:gap-3">
                                    @switch ($row['state'])
                                        @case (\App\Guide\Filament\Pages\LegacyOverlaps::STATE_ADOPT)
                                            {{ ($this->adoptAction)($arguments) }}
                                            {{ ($this->dismissAction)($arguments) }}
                                            @break
                                        @case (\App\Guide\Filament\Pages\LegacyOverlaps::STATE_REDIRECT)
                                            {{ ($this->forwardAction)($arguments) }}
                                            {{ ($this->dismissAction)($arguments) }}
                                            @break
                                        @default
                                            <p class="text-sm text-text-base lg:text-right">{{ $row['obsolete_reason'] }}</p>
                                            {{ ($this->obsoleteAction)($arguments) }}
                                    @endswitch
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
