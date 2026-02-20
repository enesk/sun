<div>
    {{-- Filter Bar --}}
    <div class="card-portal mb-4">
        <div class="flex flex-col lg:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Stadtname, PLZ oder Gemeinde suchen..."
                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);">
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterState"
                        class="text-sm border border-base-200 rounded-lg px-3 py-2.5 bg-base-100 text-base-content/70 focus:outline-none focus:ring-1"
                        aria-label="Bundesland filtern">
                    <option value="">Alle Bundesländer</option>
                    @foreach($states as $state)
                        <option value="{{ $state }}">{{ $state }}</option>
                    @endforeach
                </select>

                @if($search || $filterState)
                    <button wire:click="resetFilters"
                            class="text-sm px-3 py-2.5 text-base-content/60 hover:text-base-content transition-colors"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Bulk Actions --}}
        @if(count($selected) > 0 && $isAdmin)
            <div class="mt-3 pt-3 border-t border-base-200 flex items-center gap-3">
                <span class="text-sm text-base-content/60">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkDelete"
                        wire:confirm="Möchten Sie die ausgewählten Städte wirklich löschen? Städte mit zugeordneten Firmen werden übersprungen."
                        class="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors font-medium">
                    Löschen
                </button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="card-portal overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-base-200 text-left">
                        @if($isAdmin)
                            <th class="p-3 w-10">
                                <input type="checkbox"
                                       wire:model.live="selectAll"
                                       class="rounded border-base-300 text-blue-600 focus:ring-blue-500"
                                       aria-label="Alle auswählen">
                            </th>
                        @endif
                        <th class="p-3">
                            <button wire:click="sort('name')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                Stadt
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3">
                            <button wire:click="sort('zipcode')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                PLZ
                                @if($sortBy === 'zipcode')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3 hidden md:table-cell">
                            <button wire:click="sort('administrative_area_level_1')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                Bundesland
                                @if($sortBy === 'administrative_area_level_1')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3 hidden lg:table-cell">Gemeinde</th>
                        <th class="p-3 text-center">
                            <button wire:click="sort('companies_count')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content mx-auto">
                                Firmen
                                @if($sortBy === 'companies_count')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3 hidden lg:table-cell text-center">Geodaten</th>
                        <th class="p-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cities as $city)
                        <tr class="border-b border-base-200/50 hover:bg-base-200/30 transition-colors" wire:key="city-{{ $city->id }}">
                            @if($isAdmin)
                                <td class="p-3">
                                    <input type="checkbox"
                                           wire:model.live="selected"
                                           value="{{ $city->id }}"
                                           class="rounded border-base-300 text-blue-600 focus:ring-blue-500"
                                           aria-label="{{ $city->name }} auswählen">
                                </td>
                            @endif

                            {{-- Name --}}
                            <td class="p-3">
                                <a href="{{ route('verwaltung.cities.edit', $city->id) }}"
                                   class="text-sm font-medium text-base-content hover:underline">
                                    {{ $city->name }}
                                </a>
                            </td>

                            {{-- PLZ --}}
                            <td class="p-3">
                                <span class="text-sm text-base-content/70">{{ $city->zipcode ?: '—' }}</span>
                            </td>

                            {{-- Bundesland --}}
                            <td class="p-3 hidden md:table-cell">
                                @if($city->administrative_area_level_1)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-base-200 text-base-content/70">
                                        {{ $city->administrative_area_level_1 }}
                                    </span>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>

                            {{-- Community --}}
                            <td class="p-3 hidden lg:table-cell">
                                <span class="text-sm text-base-content/60">{{ $city->community ?: '—' }}</span>
                            </td>

                            {{-- Companies Count --}}
                            <td class="p-3 text-center">
                                <span class="text-sm {{ $city->companies_count > 0 ? 'font-medium text-base-content' : 'text-base-content/30' }}">
                                    {{ $city->companies_count }}
                                </span>
                            </td>

                            {{-- Geodata indicator --}}
                            <td class="p-3 hidden lg:table-cell text-center">
                                @if($city->latitude && $city->longitude)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700" title="{{ $city->latitude }}, {{ $city->longitude }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        </svg>
                                        Ja
                                    </span>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.cities.edit', $city->id) }}"
                                       class="p-1.5 rounded-lg text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    @if($isAdmin)
                                        <button wire:click="deleteCity({{ $city->id }})"
                                                wire:confirm="Möchten Sie &quot;{{ $city->name }}&quot; wirklich löschen?"
                                                class="p-1.5 rounded-lg text-base-content/50 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                title="Löschen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 8 : 7 }}" class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-base-content/15 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                </svg>
                                @if($search || $filterState)
                                    <p class="text-sm text-base-content/50 mb-2">Keine Städte gefunden</p>
                                    <button wire:click="resetFilters" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Filter zurücksetzen
                                    </button>
                                @else
                                    <p class="text-sm text-base-content/50 mb-2">Noch keine Städte vorhanden</p>
                                    <a href="{{ route('verwaltung.cities.create') }}" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Erste Stadt erstellen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($cities->hasPages())
            <div class="px-4 py-3 border-t border-base-200">
                {{ $cities->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="px-4 py-2 border-t border-base-200 text-xs text-base-content/40">
            {{ $cities->total() }} {{ $cities->total() === 1 ? 'Stadt' : 'Städte' }} gefunden
        </div>
    </div>
</div>
