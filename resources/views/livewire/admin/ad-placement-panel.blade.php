{{--
    Konfigurationspanel eines Ankers (#8, docs/ad-slots.md 6).

    Liegt im Werbeplatz-Editor (#7) unter der Ankerliste: oben die Belegungen
    dieses Ankers für das gezeigte Portal, darunter das Formular. Die Liste
    zeigt Gerät, Werbemittel, Portale und Status — die Reihenfolge ist die der
    Auflösung, oben läuft also, was gewinnt.
--}}
<div>
    <x-filament::section>
        <x-slot name="heading">{{ __('Belegung') }}</x-slot>

        <x-slot name="description">
            {{ $this->slotLabel() }}
        </x-slot>

        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $slotKey }}</p>

        @if ($placements->isEmpty())
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400" role="status">
                {{ __('Für dieses Portal ist dieser Anker noch nicht belegt.') }}
            </p>
        @else
            <ul class="mt-3 flex flex-col gap-2" role="list">
                @foreach ($placements as $placement)
                    @php
                        $abgelaufen = $placement->hasExpired();
                        $status = $abgelaufen ? \App\Ads\Enums\AdPlacementStatus::EXPIRED : $placement->status;
                        $portale = $placement->tenants->pluck('name')->implode(', ');
                    @endphp

                    <li
                        @class([
                            'rounded-lg border p-3',
                            'border-primary-500 ring-1 ring-primary-500' => $editingId === $placement->id,
                            'border-gray-200 dark:border-gray-700' => $editingId !== $placement->id,
                        ])
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $placement->adUnit?->name ?? __('Werbemittel fehlt') }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $placement->device->label() }}
                                    ·
                                    @if ($placement->applies_to_all_tenants)
                                        {{ __('Alle') }}
                                    @else
                                        <span title="{{ $portale }}">
                                            {{ trans_choice(':count Portal|:count Portale', $placement->tenants->count(), ['count' => $placement->tenants->count()]) }}
                                        </span>
                                    @endif
                                    · {{ __('Priorität :priority', ['priority' => $placement->priority]) }}
                                </p>
                            </div>

                            <x-filament::badge :color="$status->color()">{{ $status->label() }}</x-filament::badge>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="bearbeiten({{ $placement->id }})"
                                wire:loading.attr="disabled"
                            >
                                {{ __('Bearbeiten') }}
                            </x-filament::button>

                            @if (! $abgelaufen && $placement->status->canTransitionTo(\App\Ads\Enums\AdPlacementStatus::ACTIVE))
                                <x-filament::button
                                    size="xs"
                                    color="success"
                                    wire:click="statusWechseln({{ $placement->id }}, 'active')"
                                    wire:loading.attr="disabled"
                                >
                                    {{ __('Aktivieren') }}
                                </x-filament::button>
                            @endif

                            @if (! $abgelaufen && $placement->status->canTransitionTo(\App\Ads\Enums\AdPlacementStatus::PAUSED))
                                <x-filament::button
                                    size="xs"
                                    color="warning"
                                    wire:click="statusWechseln({{ $placement->id }}, 'paused')"
                                    wire:loading.attr="disabled"
                                >
                                    {{ __('Pausieren') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($fremde > 0)
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice('Außerdem :count Belegung dieses Ankers für andere Portale.|Außerdem :count Belegungen dieses Ankers für andere Portale.', $fremde, ['count' => $fremde]) }}
            </p>
        @endif

        @unless ($formOpen)
            <div class="mt-4">
                <x-filament::button wire:click="neu" icon="heroicon-m-plus" wire:loading.attr="disabled">
                    {{ __('Neues Placement') }}
                </x-filament::button>
            </div>
        @endunless
    </x-filament::section>

    @if ($formOpen)
        <x-filament::section class="mt-4">
            <x-slot name="heading">
                {{ $editingId === null ? __('Neues Placement') : __('Placement bearbeiten') }}
            </x-slot>

            <form wire:submit="speichern">
                {{ $this->form }}

                @if ($formatHinweis !== null)
                    <div class="mt-4 rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-950 dark:text-warning-300" role="status">
                        {{ $formatHinweis }}
                    </div>
                @endif

                @if ($konflikte->isNotEmpty())
                    {{-- Hinweis, keine Sperre: über die Ausspielung entscheidet
                         die Priorität, bei Gleichstand die neuere Belegung. --}}
                    <div class="mt-4 rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-950 dark:text-warning-300" role="status">
                        <p class="font-medium">
                            {{ trans_choice(
                                'Es gibt bereits :count aktive Belegung mit derselben Priorität für diesen Anker.|Es gibt bereits :count aktive Belegungen mit derselben Priorität für diesen Anker.',
                                $konflikte->count(),
                                ['count' => $konflikte->count()],
                            ) }}
                        </p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($konflikte as $konflikt)
                                <li>
                                    {{ $konflikt->adUnit?->name ?? __('Werbemittel fehlt') }}
                                    · {{ $konflikt->device->label() }}
                                    · {{ $konflikt->applies_to_all_tenants ? __('Alle') : trans_choice(':count Portal|:count Portale', $konflikt->tenants->count(), ['count' => $konflikt->tenants->count()]) }}
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-1">
                            {{ __('Gespeichert wird trotzdem — ausgespielt wird dann die neuere Belegung. Eine andere Priorität macht die Entscheidung sichtbar.') }}
                        </p>
                    </div>
                @endif

                <div class="mt-4 flex gap-3">
                    <x-filament::button type="submit">
                        <x-filament::loading-indicator class="h-5 w-5 inline" wire:loading wire:target="speichern" />
                        {{ __('Speichern') }}
                    </x-filament::button>

                    <x-filament::button type="button" color="gray" wire:click="abbrechen">
                        {{ __('Abbrechen') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
