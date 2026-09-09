{{--
    Filterbalken der Produktionsansicht (#19).

    Feste Reihenfolge nach design/content-dashboard.md, §0:
    Portal · Status · Region · Branche · Zuruecksetzen. Jede Auswahl steht in
    der URL (App\Content\Livewire\Concerns\HasPipelineFilters) und ueberlebt
    den Wechsel zwischen Board und Kalender.

    Mehrfachauswahl bewusst als Klappliste mit Kontrollkaestchen statt als
    Bibliothek: das Panel bringt keine Auswahlkomponente mit, und ein
    natives <select multiple> ist auf 24 Portalen nicht bedienbar.
--}}
@php
    // Die Pruefung (#20) filtert nur nach Portal und Branche — Status und
    // Region haben dort keine Bedeutung, die Queue ist per Definition ein
    // Status. Sie reicht deshalb eine eigene Gruppenliste herein.
    $groups ??= [
        ['key' => 'portals', 'label' => __('Portal'), 'options' => $portalOptions, 'empty' => __('Alle Portale')],
        ['key' => 'statuses', 'label' => __('Status'), 'options' => $statusOptions, 'empty' => __('Alle Status')],
        ['key' => 'regions', 'label' => __('Region'), 'options' => $regionOptions, 'empty' => __('Alle Regionen')],
        ['key' => 'branches', 'label' => __('Branche'), 'options' => $branchOptions, 'empty' => __('Alle Branchen')],
    ];
@endphp

<div class="sticky top-0 z-10 -mx-content-4 mb-content-4 bg-surface-page px-content-4 py-content-2">
    <div class="flex flex-wrap items-center gap-content-2">
        @foreach ($groups as $group)
            @php $selected = $this->{$group['key']}; @endphp

            <details class="relative">
                <summary
                    class="flex h-9 cursor-pointer list-none items-center gap-content-2 rounded-content-md border border-line-strong bg-surface-card px-content-3 text-content-table font-medium text-text-strong"
                >
                    <span>{{ $group['label'] }}</span>
                    <span class="text-text-muted">
                        {{ count($selected) === 0
                            ? $group['empty']
                            : trans_choice('{1}1 ausgewählt|[2,*]:count ausgewählt', count($selected), ['count' => count($selected)]) }}
                    </span>
                </summary>

                <div class="absolute start-0 z-20 mt-content-1 max-h-80 w-64 overflow-y-auto rounded-content-lg border border-line-strong bg-surface-card p-content-3 shadow-content-over">
                    @forelse ($group['options'] as $value => $label)
                        <label class="flex items-center gap-content-2 py-content-1 text-content-table text-text-base">
                            <input
                                type="checkbox"
                                value="{{ $value }}"
                                wire:model.live="{{ $group['key'] }}"
                                class="rounded border-line-strong text-content-600 focus:ring-content-600"
                            >
                            @if ($group['key'] === 'statuses')
                                <span class="content-status content-status--{{ $value }}">{{ $label }}</span>
                            @else
                                <span>{{ $label }}</span>
                            @endif
                        </label>
                    @empty
                        <p class="text-content-label text-text-muted">{{ __('Keine Auswahl vorhanden.') }}</p>
                    @endforelse
                </div>
            </details>
        @endforeach

        @if ($this->hasFilters())
            <button
                type="button"
                wire:click="resetFilters"
                class="text-content-table font-medium text-content-700 underline underline-offset-4"
            >
                {{ __('Zurücksetzen') }}
            </button>
        @endif

        <div class="ms-auto flex items-center gap-content-2 text-content-label text-text-muted">
            {{ $meta ?? '' }}
        </div>
    </div>

    @if ($this->hasFilters())
        <div class="mt-content-2 flex flex-wrap gap-content-2">
            @foreach ($groups as $group)
                @foreach ($this->{$group['key']} as $value)
                    <button
                        type="button"
                        wire:click="removeFilter('{{ $group['key'] }}', '{{ $value }}')"
                        class="flex h-6 items-center gap-content-1 rounded-content-sm border border-line-strong bg-surface-card px-content-2 text-content-label text-text-base"
                        aria-label="{{ __('Filter entfernen') }}"
                    >
                        <span>{{ $group['label'] }}: {{ $group['options'][$value] ?? $value }}</span>
                        <span aria-hidden="true">×</span>
                    </button>
                @endforeach
            @endforeach
        </div>
    @endif
</div>
