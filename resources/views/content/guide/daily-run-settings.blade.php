{{--
    Einstellungen › Tageslauf (#33, design/guide-dashboard.md §9.1).
    Globaler Schalter in eigener Karte · Laufzeitfenster (Konfiguration) ·
    Prüfabstand je Kategorie mit Kostenfolge · aktive Pausen mit „Fortsetzen“.
--}}
@use('App\Guide\Services\DailyRunSettings')
@php
    $tz = config('guide.timezone');
    $state = $this->state();
    $categories = $this->categories();
    $pauses = $this->pauses();
    $date = fn (?string $value): string => $value !== null ? \Illuminate\Support\Carbon::parse($value)->timezone($tz)->format('d.m.Y, H:i') : '–';
    $default = DailyRunSettings::defaultInterval();
    $intervalLabel = fn (int $days): string => trans_choice('{1} 1 Tag|[2,*] :count Tage', $days, ['count' => $days]);
@endphp
<x-filament-panels::page>
    {{-- Globaler Schalter --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <div class="flex flex-wrap items-center justify-between gap-content-4">
            <div class="min-w-0">
                <h2 id="daily-run-switch-label" class="text-content-h3 font-semibold text-text-strong">{{ __('Tageslauf aktiv') }}</h2>
                <p class="mt-content-1 text-content-table text-text-base">
                    @if ($state['paused'])
                        {{ __('Global pausiert seit :at', ['at' => $date($state['at'])]) }}@if ($state['by']) ({{ $state['by'] }})@endif.
                        {{ __('Kein Portal startet einen neuen Lauf.') }}
                    @else
                        {{ __('Fällige Themen aller freigeschalteten Portale laufen im Laufzeitfenster.') }}
                    @endif
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked="{{ $state['paused'] ? 'false' : 'true' }}"
                aria-labelledby="daily-run-switch-label"
                wire:click="mountAction('toggleRun')"
                @class([
                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-content-600',
                    'bg-content-600' => ! $state['paused'],
                    'bg-line-strong' => $state['paused'],
                ])
            >
                <span @class([
                    'inline-block size-5 rounded-full bg-white shadow transition-transform',
                    'translate-x-5' => ! $state['paused'],
                    'translate-x-0.5' => $state['paused'],
                ])></span>
            </button>
        </div>
    </section>

    {{-- Laufzeitfenster, schreibgeschützt --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <dl class="grid grid-cols-1 gap-content-3 text-content-table sm:grid-cols-2">
            <div>
                <dt class="flex items-center gap-content-2 text-text-muted">
                    {{ __('Laufzeitfenster') }}
                    <span class="content-origin-pill">{{ __('aus der Serverkonfiguration') }}</span>
                </dt>
                <dd class="mt-content-1 font-medium tabular-nums text-text-strong">{{ __(':start bis :end', ['start' => config('guide.run_window_start'), 'end' => config('guide.run_window_end')]) }}</dd>
            </div>
            <div>
                <dt class="flex items-center gap-content-2 text-text-muted">
                    {{ __('Prüfabstand (Standard)') }}
                    <span class="content-origin-pill">{{ __('aus der Serverkonfiguration') }}</span>
                </dt>
                <dd class="mt-content-1 font-medium tabular-nums text-text-strong">{{ $intervalLabel($default) }}</dd>
            </div>
        </dl>
    </section>

    {{-- Prüfabstand je Kategorie --}}
    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <div class="border-b border-line-soft px-content-6 py-content-4">
            <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Prüfabstand je Kategorie') }}</h2>
            <p class="mt-content-1 text-content-table text-text-base">
                {{ __('Wie oft die Themen einer Kategorie auf Aktualität geprüft werden. Themen mit eigenem Prüfabstand behalten ihn. Kostenfolge gegenüber dem Standard, geschätzt mit :cost je Prüfung.', ['cost' => \App\Guide\Support\Usd::format(\App\Guide\Support\Usd::estimate('check'))]) }}
                @if ($this->isNetworkWide())
                    {{ __('Die Auswahl gilt für alle Portale mit dieser Kategorie.') }}
                @endif
            </p>
        </div>

        @if ($categories === [])
            <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('Noch keine Kategorien vorhanden.') }}</p>
        @else
            <form wire:submit="saveIntervals">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-soft text-content-label text-text-muted">
                            <th class="px-content-6 py-content-2 text-start font-medium">{{ __('Kategorie') }}</th>
                            <th class="py-content-2 pe-content-3 text-end font-medium">{{ __('Aktive Themen') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Prüfabstand') }}</th>
                            <th class="px-content-6 py-content-2 text-end font-medium">{{ __('Kostenfolge') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft text-text-base">
                        @foreach ($categories as $category)
                            @php($selected = $this->selectedInterval($category['slug']))
                            <tr wire:key="interval-{{ $category['slug'] }}">
                                <td class="px-content-6 py-content-3">
                                    <span class="font-medium text-text-strong">{{ $category['name'] }}</span>
                                    @if ($this->isNetworkWide())
                                        <span class="block text-content-label text-text-muted">{{ trans_choice('{1} 1 Portal|[2,*] :count Portale', $category['portals'], ['count' => $category['portals']]) }}</span>
                                    @endif
                                </td>
                                <td class="py-content-3 pe-content-3 text-end tabular-nums">{{ $category['topics'] }}</td>
                                <td class="py-content-3 pe-content-3">
                                    <label class="sr-only" for="interval-{{ $category['slug'] }}">{{ __('Prüfabstand :category', ['category' => $category['name']]) }}</label>
                                    <select id="interval-{{ $category['slug'] }}" wire:model.live="intervals.{{ $category['slug'] }}" class="rounded-content-sm border-line-strong text-content-table text-text-base">
                                        @if ($category['interval'] === 'gemischt')
                                            <option value="gemischt">{{ __('unterschiedlich je Portal') }}</option>
                                        @endif
                                        <option value="">{{ __('Standard (:days)', ['days' => $intervalLabel($default)]) }}</option>
                                        @foreach (DailyRunSettings::INTERVALS as $days)
                                            <option value="{{ $days }}">{{ $intervalLabel($days) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-content-6 py-content-3 text-end tabular-nums">
                                    {{ $selected === false ? '–' : DailyRunSettings::formatDelta(DailyRunSettings::dailyCostDelta($category['topics'], $selected)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-line-soft px-content-6 py-content-4">
                    <x-filament::button type="submit">
                        <x-filament::loading-indicator class="inline h-5 w-5" wire:loading wire:target="saveIntervals" />
                        {{ __('Prüfabstand speichern') }}
                    </x-filament::button>
                </div>
            </form>
        @endif
    </section>

    {{-- Aktive Pausen --}}
    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <h2 class="border-b border-line-soft px-content-6 py-content-4 text-content-h2 font-semibold text-text-strong">
            {{ __('Pausen') }}
            <span class="text-content-body font-normal text-text-muted">({{ count($pauses) }})</span>
        </h2>
        @if ($pauses === [])
            <p class="px-content-6 py-content-4 text-content-body text-text-base">{{ __('Nichts pausiert.') }}</p>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($pauses as $pause)
                    <li class="flex flex-col gap-content-1 px-content-6 py-content-3 sm:flex-row sm:items-center sm:justify-between sm:gap-content-4" wire:key="pause-{{ $pause['kind'] }}-{{ $pause['key'] }}">
                        <div class="min-w-0">
                            <span class="content-status content-status--paused">{{ $pause['kind'] === 'global' ? __('Global') : __('Thema') }}</span>
                            <span class="ms-content-2 font-medium text-text-strong">{{ $pause['label'] }}</span>
                            <p class="mt-content-1 text-content-table text-text-muted">
                                {{ collect([
                                    $this->isNetworkWide() ? $pause['portal'] : null,
                                    $pause['kind'] === 'global' ? __('seit :at', ['at' => $date($pause['at'])]) : __('zuletzt geändert :at', ['at' => $date($pause['at'])]),
                                    $pause['by'],
                                ])->filter()->implode(' · ') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            @if ($pause['kind'] === 'global')
                                <button type="button" wire:click="mountAction('toggleRun')" class="text-content-table font-medium text-content-700 underline underline-offset-2">{{ __('Fortsetzen') }}</button>
                            @else
                                {{ ($this->resumeTopicAction)(['key' => $pause['key']]) }}
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-filament-panels::page>
