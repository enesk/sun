{{--
    Produktion (#19, #36): Reiter Board, Kalender und Artikel.

    Die beiden Ansichten sind eigene Livewire-Komponenten. Der Reiterwechsel
    steht in der URL; die Filter der Komponenten ebenfalls, sie ueberleben den
    Wechsel damit (design/content-dashboard.md, §0).
--}}
<x-filament-panels::page>
    <div
        class="mb-content-4 flex items-center gap-content-1 border-b border-line-soft"
        role="tablist"
        aria-label="{{ __('Ansicht') }}"
    >
        @foreach ($this->getTabs() as $value => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $this->tab === $value ? 'true' : 'false' }}"
                wire:click="switchTab('{{ $value }}')"
                @class([
                    'h-11 px-content-4 text-content-table font-medium',
                    'border-b-2 border-content-600 text-content-700' => $this->tab === $value,
                    'text-text-muted' => $this->tab !== $value,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($this->tab === \App\Filament\Content\Pages\Production::TAB_CALENDAR)
        @livewire('content.editorial-calendar')
    @elseif ($this->tab === \App\Filament\Content\Pages\Production::TAB_LIST)
        @livewire('content.article-list')
    @else
        @livewire('content.pipeline-board')
    @endif
</x-filament-panels::page>
