<div>
    {{-- Filter Bar --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            {{-- Search --}}
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Firmenname, E-Mail, Telefon oder PLZ suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            {{-- Filters --}}
            <div class="dash-filter-actions">
                <select wire:model.live="filterCity"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Stadt filtern">
                    <option value="">Alle Städte</option>
                    @foreach($cities as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterCategory"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Kategorie filtern">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterStatus"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Status filtern">
                    <option value="">Alle Status</option>
                    <option value="active">Aktiv</option>
                    <option value="inactive">Inaktiv</option>
                </select>

                @if($isAdmin)
                    <select wire:model.live="filterPremium"
                            class="dash-select dash-btn-sm"
                            style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                            aria-label="Premium filtern">
                        <option value="">Alle</option>
                        <option value="yes">Premium</option>
                        <option value="no">Standard</option>
                    </select>
                @endif

                @if($search || $filterCity || $filterCategory || $filterStatus || $filterPremium)
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
        @if(count($selected) > 0)
            <div class="dash-table-bulk">
                <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkToggleActive(true)"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-success-light); color: var(--dash-success); border-color: var(--dash-success-border);">
                    Aktivieren
                </button>
                <button wire:click="bulkToggleActive(false)"
                        class="dash-btn dash-btn-sm dash-btn-secondary">
                    Deaktivieren
                </button>
            </div>
        @endif
    </div>

    {{-- Table (Desktop) --}}
    <div class="dash-card overflow-hidden">
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th scope="col" class="w-10">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   class="rounded border-base-300"
                                   style="accent-color: var(--portal-primary, #3b82f6);"
                                   aria-label="Alle auswählen">
                        </th>
                        <th scope="col">
                            <button wire:click="sort('name')" class="dash-table-sort {{ $sortBy === 'name' ? 'dash-table-sort-active' : '' }}">
                                Firma
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden md:table-cell">
                            <button wire:click="sort('zipcode')" class="dash-table-sort {{ $sortBy === 'zipcode' ? 'dash-table-sort-active' : '' }}">
                                Ort
                                @if($sortBy === 'zipcode')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="hidden lg:table-cell">Kategorien</th>
                        <th scope="col" class="hidden md:table-cell text-center">
                            <button wire:click="sort('rating')" class="dash-table-sort {{ $sortBy === 'rating' ? 'dash-table-sort-active' : '' }}">
                                Bewertung
                                @if($sortBy === 'rating')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="text-center">Status</th>
                        @if($isAdmin)
                            <th scope="col" class="text-center hidden lg:table-cell">Premium</th>
                        @endif
                        <th scope="col" class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr class="{{ in_array($company->id, $selected) ? 'dash-table-row-selected' : '' }}" wire:key="company-{{ $company->id }}">
                            {{-- Checkbox --}}
                            <td>
                                <input type="checkbox"
                                       wire:model.live="selected"
                                       value="{{ $company->id }}"
                                       class="rounded border-base-300"
                                       style="accent-color: var(--portal-primary, #3b82f6);"
                                       aria-label="Firma {{ $company->name }} auswählen">
                            </td>

                            {{-- Firma (Name + Logo) --}}
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($company->logo_url)
                                        <img src="{{ $company->logo_thumb_url ?? $company->logo_url }}"
                                             alt="{{ $company->name }}"
                                             class="w-9 h-9 rounded-lg object-cover shrink-0" style="border: 1px solid var(--dash-border);">
                                    @else
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold text-white shrink-0"
                                             style="background-color: var(--portal-primary, #3b82f6);">
                                            {{ strtoupper(substr($company->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('verwaltung.companies.edit', $company->id) }}"
                                           class="text-sm font-medium hover:underline block truncate"
                                           style="color: var(--dash-text-primary);">
                                            {{ $company->name }}
                                        </a>
                                        @if($isAdmin && $company->owner)
                                            <span class="text-xs" style="color: var(--dash-text-muted);">{{ $company->owner->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Ort --}}
                            <td class="hidden md:table-cell">
                                <span class="text-sm" style="color: var(--dash-text-secondary);">
                                    {{ $company->zipcode }}
                                    @if($company->city)
                                        {{ $company->city->name }}
                                    @endif
                                </span>
                            </td>

                            {{-- Kategorien --}}
                            <td class="hidden lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($company->categories->take(2) as $category)
                                        <span class="dash-badge dash-badge-neutral">
                                            {{ $category->name }}
                                        </span>
                                    @endforeach
                                    @if($company->categories->count() > 2)
                                        <span class="text-xs" style="color: var(--dash-text-muted);">+{{ $company->categories->count() - 2 }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Rating --}}
                            <td class="hidden md:table-cell text-center">
                                @if($company->rating_count > 0)
                                    <div class="flex items-center justify-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        <span class="text-sm font-medium">{{ number_format($company->rating, 1) }}</span>
                                        <span class="text-xs" style="color: var(--dash-text-muted);">({{ $company->rating_count }})</span>
                                    </div>
                                @else
                                    <span class="text-xs" style="color: var(--dash-text-muted);">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="text-center">
                                <button wire:click="toggleActive({{ $company->id }})"
                                        class="dash-badge dash-badge-clickable {{ $company->is_active ? 'dash-badge-success' : 'dash-badge-neutral' }}"
                                        title="{{ $company->is_active ? 'Klicken zum Deaktivieren' : 'Klicken zum Aktivieren' }}">
                                    <span class="dash-badge-dot {{ $company->is_active ? 'dash-badge-dot-pulse' : '' }}"></span>
                                    {{ $company->is_active ? 'Aktiv' : 'Inaktiv' }}
                                </button>
                            </td>

                            {{-- Premium (admin only) --}}
                            @if($isAdmin)
                                <td class="text-center hidden lg:table-cell">
                                    <button wire:click="togglePremium({{ $company->id }})"
                                            class="dash-badge dash-badge-clickable {{ $company->is_premium ? 'dash-badge-premium' : 'dash-badge-neutral' }}"
                                            title="{{ $company->is_premium ? 'Premium deaktivieren' : 'Premium aktivieren' }}">
                                        @if($company->is_premium)
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd"/>
                                            </svg>
                                            Premium
                                        @else
                                            Standard
                                        @endif
                                    </button>
                                </td>
                            @endif

                            {{-- Aktionen --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.companies.edit', $company->id) }}"
                                       class="dash-btn-icon"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('portal.companies.show', $company->url_slug) }}"
                                       target="_blank"
                                       class="dash-btn-icon"
                                       title="Im Portal ansehen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                        </svg>
                                    </a>
                                    <button wire:click="deleteCompany({{ $company->id }})"
                                            wire:confirm="Möchten Sie &quot;{{ $company->name }}&quot; wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden."
                                            class="dash-btn-icon dash-btn-danger"
                                            title="Löschen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 8 : 7 }}">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                    </svg>
                                    @if($search || $filterCity || $filterCategory || $filterStatus)
                                        <p class="dash-empty-title">Keine Firmen gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Firmen eingetragen</p>
                                        <p class="dash-empty-description">Erstellen Sie den ersten Eintrag für dieses Portal.</p>
                                        <a href="{{ route('verwaltung.companies.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erste Firma erstellen
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Card-List --}}
        <div class="dash-mobile-cards">
            @forelse($companies as $company)
                <div class="dash-mobile-card" wire:key="company-mobile-{{ $company->id }}">
                    <div class="dash-mobile-card-header">
                        <div class="flex items-center gap-3">
                            @if($company->logo_url)
                                <img src="{{ $company->logo_thumb_url ?? $company->logo_url }}" alt="{{ $company->name }}" class="w-10 h-10 rounded-lg object-cover" style="border: 1px solid var(--dash-border);">
                            @else
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold text-white" style="background-color: var(--portal-primary, #3b82f6);">{{ strtoupper(substr($company->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <div class="dash-mobile-card-title truncate">{{ $company->name }}</div>
                                @if($isAdmin && $company->owner)
                                    <span class="text-xs" style="color: var(--dash-text-muted);">{{ $company->owner->name }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="dash-badge {{ $company->is_active ? 'dash-badge-success' : 'dash-badge-neutral' }}">
                            {{ $company->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </div>
                    <div class="dash-mobile-card-meta">
                        @if($company->zipcode || $company->city)
                            <span>{{ $company->zipcode }} {{ $company->city?->name }}</span>
                        @endif
                        @if($company->rating_count > 0)
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                {{ number_format($company->rating, 1) }} ({{ $company->rating_count }})
                            </span>
                        @endif
                    </div>
                    @if($company->categories->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach($company->categories->take(3) as $category)
                                <span class="dash-badge dash-badge-neutral" style="font-size: 0.65rem;">{{ $category->name }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="dash-mobile-card-actions">
                        <a href="{{ route('verwaltung.companies.edit', $company->id) }}" class="dash-btn dash-btn-sm dash-btn-primary" style="flex: 1; text-align: center;">Bearbeiten</a>
                        <a href="{{ route('portal.companies.show', $company->url_slug) }}" target="_blank" class="dash-btn dash-btn-sm dash-btn-secondary" style="flex: 1; text-align: center;">Ansehen</a>
                    </div>
                </div>
            @empty
                <div class="dash-empty">
                    <p class="dash-empty-title">Keine Firmen gefunden</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($companies->hasPages())
            <div class="dash-table-footer">
                {{ $companies->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-table-footer">
            {{ $companies->total() }} {{ Str::plural('Firma', $companies->total(), 'Firmen') }} gefunden
        </div>
    </div>
</div>
