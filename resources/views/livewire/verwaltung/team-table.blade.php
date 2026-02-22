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
                       placeholder="Teamname suchen..."
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
                        <th scope="col">Teamname</th>
                        <th scope="col" class="text-center">Mitglieder</th>
                        <th scope="col" class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                        <tr wire:key="team-{{ $team->id }}">
                            {{-- Name --}}
                            <td>
                                <a href="{{ route('verwaltung.teams.edit', $team->uuid) }}"
                                   class="text-sm font-medium" style="color: var(--dash-text-primary); text-decoration: none;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    {{ $team->name }}
                                </a>
                            </td>

                            {{-- Members Count --}}
                            <td class="text-center">
                                <span class="dash-badge {{ $team->tenant_users_count > 0 ? 'dash-badge-info' : 'dash-badge-neutral' }}">
                                    {{ $team->tenant_users_count }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.teams.edit', $team->uuid) }}"
                                       class="dash-btn-icon"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    <button wire:click="confirmDelete('{{ $team->uuid }}', '{{ addslashes($team->name) }}')"
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
                            <td colspan="3">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                    </svg>
                                    @if($search)
                                        <p class="dash-empty-title">Keine Teams gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit einem anderen Suchbegriff.</p>
                                        <button wire:click="$set('search', '')" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Teams vorhanden</p>
                                        <a href="{{ route('verwaltung.teams.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erstes Team erstellen
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
            @forelse($teams as $team)
                <div class="dash-mobile-card" wire:key="team-mobile-{{ $team->id }}">
                    <div class="dash-mobile-card-header">
                        <div class="dash-mobile-card-title">{{ $team->name }}</div>
                        <span class="dash-badge {{ $team->tenant_users_count > 0 ? 'dash-badge-info' : 'dash-badge-neutral' }}">{{ $team->tenant_users_count }} Mitglieder</span>
                    </div>
                    <div class="dash-mobile-card-actions">
                        <a href="{{ route('verwaltung.teams.edit', $team->uuid) }}" class="dash-btn dash-btn-sm dash-btn-primary" style="flex: 1; text-align: center;">Bearbeiten</a>
                        <button wire:click="confirmDelete('{{ $team->uuid }}', '{{ addslashes($team->name) }}')" class="dash-btn dash-btn-sm dash-btn-danger">Löschen</button>
                    </div>
                </div>
            @empty
                <div class="dash-empty">
                    <p class="dash-empty-title">Keine Teams gefunden</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($teams->hasPages())
            <div class="dash-pagination">
                {{ $teams->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $teams->total() }} {{ $teams->total() === 1 ? 'Team' : 'Teams' }} gefunden
        </div>
    </div>

    {{-- Delete Team Modal --}}
    @if($showDeleteModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="delete-team-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.cancelDelete()">
            <div class="dash-modal-backdrop" wire:click="cancelDelete"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--dash-danger-light);">
                            <svg class="w-5 h-5" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="delete-team-title" class="dash-modal-title">Team löschen</h3>
                            <p class="text-sm" style="color: var(--dash-text-muted);">Alle Mitglieder werden aus dem Team entfernt.</p>
                        </div>
                    </div>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        Möchtest du das Team <strong>{{ $deletingTeamName }}</strong> wirklich löschen?
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button wire:click="cancelDelete" class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button wire:click="deleteTeam" class="dash-btn dash-btn-danger">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
