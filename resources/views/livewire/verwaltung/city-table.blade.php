<div>
    {{-- Filter Bar --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Stadtname, PLZ oder Gemeinde suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            <div class="dash-filter-actions">
                <select wire:model.live="filterState"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Bundesland filtern">
                    <option value="">Alle Bundesländer</option>
                    @foreach($states as $state)
                        <option value="{{ $state }}">{{ $state }}</option>
                    @endforeach
                </select>

                @if($search || $filterState)
                    <button wire:click="resetFilters"
                            class="dash-btn-icon"
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
            <div class="dash-table-bulk">
                <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkDelete"
                        wire:confirm="Möchten Sie die ausgewählten Städte wirklich löschen? Städte mit zugeordneten Firmen werden übersprungen."
                        class="dash-btn dash-btn-danger dash-btn-sm">
                    Löschen
                </button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="dash-card overflow-hidden">
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        @if($isAdmin)
                            <th scope="col" style="width: 2.5rem;">
                                <input type="checkbox"
                                       wire:model.live="selectAll"
                                       style="accent-color: var(--portal-primary);"
                                       aria-label="Alle auswählen">
                            </th>
                        @endif
                        <th scope="col">
                            <button wire:click="sort('name')" class="dash-table-sort {{ $sortBy === 'name' ? 'dash-table-sort-active' : '' }}">
                                Stadt
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col">
                            <button wire:click="sort('zipcode')" class="dash-table-sort {{ $sortBy === 'zipcode' ? 'dash-table-sort-active' : '' }}">
                                PLZ
                                @if($sortBy === 'zipcode')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden md:table-cell">
                            <button wire:click="sort('administrative_area_level_1')" class="dash-table-sort {{ $sortBy === 'administrative_area_level_1' ? 'dash-table-sort-active' : '' }}">
                                Bundesland
                                @if($sortBy === 'administrative_area_level_1')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden lg:table-cell">Gemeinde</th>
                        <th scope="col" class="text-center">
                            <button wire:click="sort('companies_count')" class="dash-table-sort {{ $sortBy === 'companies_count' ? 'dash-table-sort-active' : '' }}">
                                Firmen
                                @if($sortBy === 'companies_count')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden lg:table-cell text-center">Geodaten</th>
                        <th scope="col" class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cities as $city)
                        <tr wire:key="city-{{ $city->id }}" class="{{ in_array($city->id, $selected) ? 'dash-table-row-selected' : '' }}">
                            @if($isAdmin)
                                <td>
                                    <input type="checkbox"
                                           wire:model.live="selected"
                                           value="{{ $city->id }}"
                                           style="accent-color: var(--portal-primary);"
                                           aria-label="{{ $city->name }} auswählen">
                                </td>
                            @endif

                            {{-- Name --}}
                            <td>
                                <a href="{{ route('verwaltung.cities.edit', $city->id) }}"
                                   class="text-sm font-medium" style="color: var(--dash-text-primary); text-decoration: none;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    {{ $city->name }}
                                </a>
                            </td>

                            {{-- PLZ --}}
                            <td>
                                <span style="color: var(--dash-text-secondary);">{{ $city->zipcode ?: '—' }}</span>
                            </td>

                            {{-- Bundesland --}}
                            <td class="hidden md:table-cell">
                                @if($city->administrative_area_level_1)
                                    <span class="dash-badge dash-badge-neutral">{{ $city->administrative_area_level_1 }}</span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Community --}}
                            <td class="hidden lg:table-cell">
                                <span style="color: var(--dash-text-secondary);">{{ $city->community ?: '—' }}</span>
                            </td>

                            {{-- Companies Count --}}
                            <td class="text-center">
                                <span class="{{ $city->companies_count > 0 ? 'font-medium' : '' }}" style="color: {{ $city->companies_count > 0 ? 'var(--dash-text-primary)' : 'var(--dash-text-muted)' }};">
                                    {{ $city->companies_count }}
                                </span>
                            </td>

                            {{-- Geodata indicator --}}
                            <td class="hidden lg:table-cell text-center">
                                @if($city->latitude && $city->longitude)
                                    <span class="dash-badge dash-badge-success" title="{{ $city->latitude }}, {{ $city->longitude }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        </svg>
                                        Ja
                                    </span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.cities.edit', $city->id) }}"
                                       class="dash-btn-icon"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    @if($isAdmin)
                                        <button wire:click="deleteCity({{ $city->id }})"
                                                wire:confirm="Möchten Sie &quot;{{ $city->name }}&quot; wirklich löschen?"
                                                class="dash-btn-icon dash-btn-danger"
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
                            <td colspan="{{ $isAdmin ? 8 : 7 }}">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                    </svg>
                                    @if($search || $filterState)
                                        <p class="dash-empty-title">Keine Städte gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen.</p>
                                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Städte vorhanden</p>
                                        <a href="{{ route('verwaltung.cities.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erste Stadt erstellen
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($cities->hasPages())
            <div class="dash-pagination">
                {{ $cities->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $cities->total() }} {{ $cities->total() === 1 ? 'Stadt' : 'Städte' }} gefunden
        </div>
    </div>
</div>
