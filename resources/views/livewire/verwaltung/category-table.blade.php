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
                       placeholder="Kategoriename oder Beschreibung suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            <div class="dash-filter-actions">
                <select wire:model.live="filterParent"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Ebene filtern">
                    <option value="">Alle Ebenen</option>
                    <option value="roots">Nur Hauptkategorien</option>
                    @foreach($parentCategories as $id => $name)
                        <option value="{{ $id }}">Unter: {{ $name }}</option>
                    @endforeach
                </select>

                @if($search || $filterParent)
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
                        wire:confirm="Möchten Sie die ausgewählten Kategorien wirklich löschen? Kategorien mit Unterkategorien werden übersprungen."
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
                                       class="dash-checkbox-input"
                                       aria-label="Alle auswählen"
                                       style="accent-color: var(--portal-primary);">
                            </th>
                        @endif
                        <th scope="col">
                            <button wire:click="sort('name')" class="dash-table-sort {{ $sortBy === 'name' ? 'dash-table-sort-active' : '' }}">
                                Name
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden md:table-cell">Oberkategorie</th>
                        <th scope="col" class="hidden lg:table-cell">Icon</th>
                        <th scope="col" class="text-center">
                            <button wire:click="sort('sort_order')" class="dash-table-sort {{ $sortBy === 'sort_order' ? 'dash-table-sort-active' : '' }}">
                                Sortierung
                                @if($sortBy === 'sort_order')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="text-center hidden md:table-cell">
                            <button wire:click="sort('companies_count')" class="dash-table-sort {{ $sortBy === 'companies_count' ? 'dash-table-sort-active' : '' }}">
                                Firmen
                                @if($sortBy === 'companies_count')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="text-center hidden lg:table-cell">Unterkategorien</th>
                        <th scope="col" class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" class="{{ in_array($category->id, $selected) ? 'dash-table-row-selected' : '' }}">
                            @if($isAdmin)
                                <td>
                                    <input type="checkbox"
                                           wire:model.live="selected"
                                           value="{{ $category->id }}"
                                           style="accent-color: var(--portal-primary);"
                                           aria-label="{{ $category->name }} auswählen">
                                </td>
                            @endif

                            {{-- Name --}}
                            <td>
                                <div class="flex items-center gap-2">
                                    @if($category->parent_id)
                                        <span style="color: var(--dash-text-muted); font-size: 0.75rem;">└</span>
                                    @endif
                                    <a href="{{ route('verwaltung.categories.edit', $category->id) }}"
                                       class="text-sm font-medium" style="color: var(--dash-text-primary); text-decoration: none;"
                                       onmouseover="this.style.textDecoration='underline'"
                                       onmouseout="this.style.textDecoration='none'">
                                        {{ $category->name }}
                                    </a>
                                </div>
                                @if($category->description)
                                    <p class="text-xs mt-0.5 truncate max-w-xs" style="color: var(--dash-text-muted);">{{ $category->description }}</p>
                                @endif
                            </td>

                            {{-- Parent --}}
                            <td class="hidden md:table-cell">
                                @if($category->parent)
                                    <span class="dash-badge dash-badge-neutral">{{ $category->parent->name }}</span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Icon --}}
                            <td class="hidden lg:table-cell">
                                @if($category->icon)
                                    <span style="color: var(--dash-text-secondary);">{{ $category->icon }}</span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Sort Order --}}
                            <td class="text-center">
                                <span style="color: var(--dash-text-secondary);">{{ $category->sort_order }}</span>
                            </td>

                            {{-- Companies Count --}}
                            <td class="text-center hidden md:table-cell">
                                <span class="{{ $category->companies_count > 0 ? 'font-medium' : '' }}" style="color: {{ $category->companies_count > 0 ? 'var(--dash-text-primary)' : 'var(--dash-text-muted)' }};">
                                    {{ $category->companies_count }}
                                </span>
                            </td>

                            {{-- Children Count --}}
                            <td class="text-center hidden lg:table-cell">
                                <span class="{{ $category->children_count > 0 ? 'font-medium' : '' }}" style="color: {{ $category->children_count > 0 ? 'var(--dash-text-primary)' : 'var(--dash-text-muted)' }};">
                                    {{ $category->children_count }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.categories.edit', $category->id) }}"
                                       class="dash-btn-icon"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    @if($isAdmin)
                                        <button wire:click="deleteCategory({{ $category->id }})"
                                                wire:confirm="Möchten Sie &quot;{{ $category->name }}&quot; wirklich löschen?"
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
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                                    </svg>
                                    @if($search || $filterParent)
                                        <p class="dash-empty-title">Keine Kategorien gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen.</p>
                                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Kategorien vorhanden</p>
                                        <a href="{{ route('verwaltung.categories.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erste Kategorie erstellen
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
        @if($categories->hasPages())
            <div class="dash-pagination">
                {{ $categories->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $categories->total() }} {{ $categories->total() === 1 ? 'Kategorie' : 'Kategorien' }} gefunden
        </div>
    </div>
</div>
