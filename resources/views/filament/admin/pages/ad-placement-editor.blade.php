{{--
    Werbeplätze — Editor (#7, docs/ad-slots.md 6).

    Links die echte Portalseite im iframe (Mobil 390 px, Desktop 1280 px,
    skaliert per transform), rechts die Anker des Seitentyps. Die Brücke zum
    iframe steckt in resources/js/filament/ad-editor-bridge.js — kein
    Inline-Skript.
--}}
<x-filament-panels::page>
    <form wire:submit.prevent class="fi-form">
        {{ $this->form }}
    </form>

    @php
        $hinweis = $this->previewHint();
        $slots = $this->slots();
        $fehlende = $this->missingSlotKeys();
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Vorschau --}}
        <div
            class="order-last w-full lg:order-first lg:flex-1"
            x-data="adEditorPreview"
            x-on:ad-editor-reload.window="neuLaden($event.detail)"
            x-on:ad-editor-placement-updated.window="belegungAktualisiert($event.detail)"
            data-url="{{ $this->previewUrl() }}"
            data-origin="{{ $this->previewOrigin() }}"
            data-device="{{ $this->device()->value }}"
            data-catalog-version="{{ \App\Ads\Support\AdSlotCatalog::version() }}"
            data-frame-width="{{ $this->previewWidth() }}"
            data-frame-height="{{ $this->previewHeight() }}"
        >
            <x-filament::section>
                <x-slot name="heading">{{ __('Vorschau') }}</x-slot>

                <x-slot name="description">
                    @if ($this->previewOrigin())
                        {{ __('Echte Seite von :origin, signierte Adresse, 30 Minuten gültig.', ['origin' => $this->previewOrigin()]) }}
                    @else
                        {{ __('Keine Domain, keine Vorschau.') }}
                    @endif
                </x-slot>

                @if ($hinweis !== null)
                    <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-400" role="status">
                        {{ $hinweis }}
                    </div>
                @else
                    <div x-ref="buehne" class="overflow-hidden" x-bind:style="`height: ${buehnenHoehe()}px`">
                        <iframe
                            x-ref="rahmen"
                            x-bind:src="url"
                            x-on:load="geladen()"
                            x-bind:style="`width: ${frameWidth}px; height: ${frameHeight}px; transform: scale(${scale}); transform-origin: top left;`"
                            class="rounded-xl border border-gray-200 bg-white dark:border-gray-700"
                            title="{{ __('Vorschau der Portalseite') }}"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                        ></iframe>
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Breite :width px · Abgelaufene Vorschau über „Vorschau neu laden" erneuern.', ['width' => $this->previewWidth()]) }}
                    </p>
                @endif
            </x-filament::section>
        </div>

        {{-- Ankerliste --}}
        <div class="w-full lg:w-96 lg:shrink-0">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Anker :count', ['count' => $this->hasExamplePage() ? count($slots) : 0]) }}
                </x-slot>

                <x-slot name="description">
                    {{ $this->device() === \App\Ads\Enums\AdDevice::DESKTOP ? __('Zustand für Desktop') : __('Zustand für Mobil') }}
                    @if ($this->pageType()->hasMultipleRoutes())
                        · {{ \App\Ads\Support\AdSlotCatalog::routeLabel($this->route()) }}
                    @endif
                </x-slot>

                @if ($slots === [] || ! $this->hasExamplePage())
                    <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
                        {{ __('Für dieses Portal gibt es noch keine Seite dieses Typs.') }}
                    </p>
                @else
                    <ul class="flex flex-col gap-2" role="list">
                        @foreach ($slots as $slot)
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectSlot('{{ $slot['key'] }}')"
                                    @class([
                                        'w-full rounded-lg border p-3 text-left transition',
                                        'border-primary-500 ring-1 ring-primary-500' => $this->selectedSlotKey === $slot['key'],
                                        'border-gray-200 hover:border-gray-300 dark:border-gray-700' => $this->selectedSlotKey !== $slot['key'],
                                    ])
                                    @aria-current($this->selectedSlotKey === $slot['key'] ? 'true' : null)
                                >
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                                            {{ $slot['label'] }}
                                        </span>

                                        @if (! $slot['on_route'])
                                            <x-filament::badge color="gray">{{ __('nicht auf dieser Seite') }}</x-filament::badge>
                                        @elseif (! $slot['allows_device'])
                                            <x-filament::badge color="gray">{{ __('anderes Gerät') }}</x-filament::badge>
                                        @elseif ($slot['filled'])
                                            <x-filament::badge color="success">{{ __('belegt') }}</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">{{ __('leer') }}</x-filament::badge>
                                        @endif
                                    </span>

                                    <span class="mt-1 block font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $slot['key'] }}
                                    </span>

                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                        @if (! $slot['on_route'])
                                            {{ __('Steht nur auf: :routes — auf der gezeigten Seite bleibt der Anker stumm.', ['routes' => implode(', ', $slot['route_labels'])]) }}
                                        @elseif (! $slot['allows_device'])
                                            {{ $slot['devices'] === 'desktop' ? __('nur Desktop — im mobilen Ansichtsmodus nicht sichtbar') : __('nur Mobil — in der Desktop-Ansicht nicht sichtbar') }}
                                        @elseif ($slot['filled'])
                                            {{ $slot['unit_name'] }} · {{ __('Priorität :priority', ['priority' => $slot['priority']]) }}
                                        @else
                                            {{ __('mindestens :height px reserviert', ['height' => $slot['min_height']]) }}
                                        @endif
                                    </span>

                                    {{-- Inhaltsbedingung (#22): der Anker steht im Markup
                                         innerhalb seiner Sektion und fehlt mit ihr. --}}
                                    @if ($slot['requires'] !== null)
                                        <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">
                                            {{ __('Erscheint nur, wenn: :bedingung', ['bedingung' => $slot['requires']]) }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($fehlende !== [])
                    <div class="mt-4 rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-950 dark:text-warning-300" role="status">
                        {{ __('Im Katalog, aber nicht in der geladenen Seite:') }}
                        <ul class="mt-1 list-disc space-y-0.5 ps-4">
                            @foreach ($fehlende as $fehlenderKey)
                                @php $fehlenderSlot = collect($slots)->firstWhere('key', $fehlenderKey); @endphp
                                <li>
                                    <span class="font-mono">{{ $fehlenderKey }}</span>
                                    {{-- Grund, wenn der Katalog ihn kennt (#22). Ohne Grund ist
                                         es ein fehlender Anker im Markup und gehoert gemeldet. --}}
                                    @if (($fehlenderSlot['requires'] ?? null) !== null)
                                        — {{ $fehlenderSlot['requires'] }}
                                    @else
                                        — {{ __('kein Grund im Katalog hinterlegt — Anker fehlt im Markup') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            @if ($this->selectedSlotKey !== null && $this->tenant() !== null)
                {{-- Konfigurationspanel des Ankers (#8). Der Schlüssel enthält
                     Portal, Anker und Gerät: ein Wechsel baut das Panel neu
                     auf, statt den Formularstand des vorigen Ankers
                     mitzunehmen. --}}
                <div class="mt-4">
                    @livewire(
                        'admin.ad-placement-panel',
                        [
                            'tenantId' => (int) $this->tenant()->getKey(),
                            'slotKey' => $this->selectedSlotKey,
                            'device' => $this->device()->value,
                        ],
                        key('belegung-'.$this->tenant()->getKey().'-'.$this->selectedSlotKey.'-'.$this->device()->value)
                    )
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
