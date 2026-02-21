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
                       placeholder="Name oder E-Mail suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            {{-- Role Filter --}}
            <div class="dash-filter-actions">
                <select wire:model.live="filterRole"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Rolle filtern">
                    <option value="">Alle Rollen</option>
                    @foreach($availableRoles as $role)
                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                    @endforeach
                </select>

                @if($search || $filterRole)
                    <button wire:click="$set('search', ''); $set('filterRole', '');"
                            class="dash-btn-icon"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
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
                        <th>
                            <button wire:click="sort('name')" class="dash-table-sort {{ $sortBy === 'name' ? 'dash-table-sort-active' : '' }}">
                                Name
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th>
                            <button wire:click="sort('email')" class="dash-table-sort {{ $sortBy === 'email' ? 'dash-table-sort-active' : '' }}">
                                E-Mail
                                @if($sortBy === 'email')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="hidden md:table-cell">Rolle</th>
                        <th class="hidden lg:table-cell">
                            <button wire:click="sort('last_seen_at')" class="dash-table-sort {{ $sortBy === 'last_seen_at' ? 'dash-table-sort-active' : '' }}">
                                Zuletzt aktiv
                                @if($sortBy === 'last_seen_at')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="{{ $user->is_current_user ? 'dash-table-row-selected' : '' }}" wire:key="user-{{ $user->id }}">
                            {{-- Name + Avatar --}}
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="dash-header-avatar shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium" style="color: var(--dash-text-primary);">
                                            {{ $user->name }}
                                            @if($user->is_current_user)
                                                <span class="text-xs" style="color: var(--dash-text-muted);">(Du)</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td>
                                <span class="text-sm" style="color: var(--dash-text-secondary);">{{ $user->email }}</span>
                            </td>

                            {{-- Role --}}
                            <td class="hidden md:table-cell">
                                @if(!$user->is_current_user)
                                    <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                            class="dash-select dash-btn-sm"
                                            style="width: auto; min-height: auto; padding: 0.375rem 2rem 0.375rem 0.5rem; font-size: 0.75rem;"
                                            aria-label="Rolle für {{ $user->name }}">
                                        @foreach($availableRoles as $role)
                                            <option value="{{ $role }}" {{ $user->primary_role === $role ? 'selected' : '' }}>
                                                {{ ucfirst($role) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="dash-badge dash-badge-neutral">
                                        {{ ucfirst($user->primary_role) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Last Seen --}}
                            <td class="hidden lg:table-cell">
                                @if($user->last_seen_at)
                                    <span class="text-xs" style="color: var(--dash-text-muted);" title="{{ $user->last_seen_at->format('d.m.Y H:i') }}">
                                        {{ $user->last_seen_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-xs" style="color: var(--dash-text-muted); opacity: 0.5;">Nie</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                @if(!$user->is_current_user)
                                    <button wire:click="confirmRemove({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                            class="dash-btn-icon dash-btn-danger"
                                            title="Aus Workspace entfernen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-xs" style="color: var(--dash-text-muted);">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                                    </svg>
                                    @if($search || $filterRole)
                                        <p class="dash-empty-title">Keine Benutzer gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen.</p>
                                        <button wire:click="$set('search', ''); $set('filterRole', '');" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Benutzer im Workspace</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="dash-pagination">
                {{ $users->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $users->total() }} {{ $users->total() === 1 ? 'Benutzer' : 'Benutzer' }} gefunden
        </div>
    </div>

    {{-- Remove User Modal --}}
    @if($showRemoveModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true">
            <div class="dash-modal-backdrop" wire:click="cancelRemove"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--dash-danger-light);">
                            <svg class="w-5 h-5" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="dash-modal-title">Benutzer entfernen</h3>
                            <p class="text-sm" style="color: var(--dash-text-muted);">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                        </div>
                    </div>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        Möchtest du <strong>{{ $removingUserName }}</strong> wirklich aus dem Workspace entfernen? Der Benutzer verliert sofort den Zugriff.
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button wire:click="cancelRemove" class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button wire:click="removeUser" class="dash-btn dash-btn-danger">
                        Entfernen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
