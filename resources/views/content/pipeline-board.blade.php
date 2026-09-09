{{--
    Pipeline-Board (#19), design/content-dashboard.md, §2.

    Spalten in der festen Reihenfolge aus DisplayStatus::board(). Kein Ziehen
    und Ablegen: die Karten bewegt die Pipeline, Handlungen stehen benannt im
    Detailblatt. Polling alle 15 Sekunden zeigt Statuswechsel live.
--}}
@php
    // Ziel-URL der uebervollen Spalte: aktueller Filterstand plus Reiter,
    // Spaltenstatus und Sortierung (design/content-dashboard.md, §3a).
    $listUrl = fn (string $status): string => url()->current().'?'.http_build_query([
        'reiter' => \App\Filament\Content\Pages\Production::TAB_LIST,
        'portal' => $this->portals,
        'region' => $this->regions,
        'branche' => $this->branches,
        'status' => [$status],
        'sortieren' => 'score',
        'richtung' => \App\Content\Services\ContentPipelineService::DIRECTION_DESC,
    ]);
@endphp

<div wire:poll.15s="refreshBoard" class="pb-content-8">
    @include('content.partials.filter-bar', [
        'meta' => __('aktualisiert um :time Uhr', ['time' => $refreshedAt]),
    ])

    <div class="flex gap-content-4 overflow-x-auto pb-content-4" role="list">
        @foreach ($columns as $column)
            <section
                wire:key="column-{{ $column['status'] }}"
                class="w-[280px] flex-none rounded-content-lg bg-surface-sunken p-content-3"
                role="listitem"
                aria-label="{{ $column['label'] }}"
            >
                <header class="sticky top-0 z-[1] flex h-11 items-center gap-content-2 bg-surface-sunken">
                    <h3 class="text-content-h3 font-semibold text-text-strong">{{ $column['label'] }}</h3>
                    <span class="rounded-content-sm bg-surface-card px-content-2 py-content-1 text-content-label font-semibold text-text-base">
                        {{ $column['total'] }}
                    </span>
                </header>

                <div class="mt-content-2 space-y-content-2">
                    @forelse ($column['cards'] as $card)
                        @include('content.partials.board-card', ['card' => $card])
                    @empty
                        {{-- Leerzustand liegt auf der surface-sunken-Spalte,
                             deshalb text-base statt text-muted (#64). --}}
                        <p class="rounded-content-lg border border-dashed border-line-strong p-content-3 text-content-label text-text-base">
                            @if ($column['status'] === \App\Content\Enums\DisplayStatus::PUBLISHED->value)
                                {{ __('Heute ist hier noch nichts erschienen.') }}
                            @else
                                {{ __('Nichts in diesem Status.') }}
                            @endif
                        </p>
                    @endforelse

                    @if ($column['overflow'] > 0)
                        {{-- Fortsetzung genau dieser Spalte: der Spaltenstatus
                             ersetzt den Statusfilter des Boards, sortiert nach
                             Score absteigend (§3a). --}}
                        <a
                            href="{{ $listUrl($column['status']) }}"
                            wire:navigate
                            class="flex h-11 items-center text-content-label font-medium text-content-700 underline underline-offset-4"
                        >
                            {{ __('+ :count weitere in der Liste ansehen', ['count' => $column['overflow']]) }}
                        </a>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    <div
        wire:loading
        wire:target="portals,statuses,regions,branches"
        class="mt-content-2 text-content-label text-text-muted"
    >
        {{ __('Karten werden geladen …') }}
    </div>

    <x-filament-actions::modals />
</div>
