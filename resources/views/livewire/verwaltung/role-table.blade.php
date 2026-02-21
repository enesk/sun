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
                       placeholder="Rollenname suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            @if($search)
                <div class="dash-filter-actions">
                    <button wire:click="$set('search', '')"
                            class="dash-btn-icon"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-success">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-error">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="dash-card overflow-hidden">
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Rollenname</th>
                        <th class="hidden md:table-cell">Typ</th>
                        <th class="text-center">Benutzer</th>
                        <th class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            {{-- Name --}}
                            <td>
                                <a href="{{ route('verwaltung.roles.edit', $role->id) }}"
                                   class="text-sm font-medium" style="color: var(--dash-text-primary); text-decoration: none;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    {{ $role->name }}
                                </a>
                            </td>

                            {{-- Type --}}
                            <td class="hidden md:table-cell">
                                @if($role->is_global)
                                    <span class="dash-badge dash-badge-premium">System</span>
                                @else
                                    <span class="dash-badge dash-badge-info">Custom</span>
                                @endif
                            </td>

                            {{-- Users Count --}}
                            <td class="text-center">
                                <span class="dash-badge {{ $role->users_count > 0 ? 'dash-badge-success' : 'dash-badge-neutral' }}">
                                    {{ $role->users_count }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.roles.edit', $role->id) }}"
                                       class="dash-btn-icon"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    @if($role->can_delete)
                                        <button wire:click="confirmDelete({{ $role->id }}, '{{ addslashes($role->name) }}')"
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
                            <td colspan="4">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                    </svg>
                                    @if($search)
                                        <p class="dash-empty-title">Keine Rollen gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit einem anderen Suchbegriff.</p>
                                        <button wire:click="$set('search', '')" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine eigenen Rollen vorhanden</p>
                                        <a href="{{ route('verwaltung.roles.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erste Rolle erstellen
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
        @if($roles->hasPages())
            <div class="dash-pagination">
                {{ $roles->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $roles->total() }} {{ $roles->total() === 1 ? 'Rolle' : 'Rollen' }} gefunden
        </div>
    </div>

    {{-- Delete Role Modal --}}
    @if($showDeleteModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true">
            <div class="dash-modal-backdrop" wire:click="cancelDelete"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--dash-danger-light);">
                            <svg class="w-5 h-5" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="dash-modal-title">Rolle löschen</h3>
                            <p class="text-sm" style="color: var(--dash-text-muted);">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                        </div>
                    </div>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        Möchtest du die Rolle <strong>{{ $deletingRoleName }}</strong> wirklich löschen?
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button wire:click="cancelDelete" class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button wire:click="deleteRole" class="dash-btn dash-btn-danger">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
