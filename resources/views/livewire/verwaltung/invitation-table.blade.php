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
                       placeholder="E-Mail-Adresse suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;">
            </div>

            <div class="dash-filter-actions">
                <select wire:model.live="filterStatus"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Status filtern">
                    <option value="">Alle Status</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if($search || $filterStatus)
                    <button wire:click="$set('search', ''); $set('filterStatus', '');"
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
                        <th>E-Mail</th>
                        <th class="hidden md:table-cell">Rolle</th>
                        <th class="hidden lg:table-cell">Team</th>
                        <th>Status</th>
                        <th class="hidden md:table-cell">Gesendet</th>
                        <th class="hidden lg:table-cell">Läuft ab</th>
                        <th class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        <tr wire:key="invitation-{{ $invitation->id }}">
                            {{-- Email --}}
                            <td>
                                <span class="text-sm font-medium" style="color: var(--dash-text-primary);">{{ $invitation->email }}</span>
                                @if($invitation->user)
                                    <p class="text-xs" style="color: var(--dash-text-muted);">von {{ $invitation->user->name }}</p>
                                @endif
                            </td>

                            {{-- Role --}}
                            <td class="hidden md:table-cell">
                                <span class="dash-badge dash-badge-neutral">{{ ucfirst($invitation->role ?? 'user') }}</span>
                            </td>

                            {{-- Team --}}
                            <td class="hidden lg:table-cell">
                                @if($invitation->team)
                                    <span style="color: var(--dash-text-secondary);">{{ $invitation->team->name }}</span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td>
                                @php
                                    $statusMap = match($invitation->status_color) {
                                        'success' => 'success',
                                        'error' => 'danger',
                                        'warning' => 'warning',
                                        'info' => 'info',
                                        default => 'neutral',
                                    };
                                @endphp
                                <span class="dash-badge dash-badge-{{ $statusMap }}">
                                    {{ $invitation->status_label }}
                                </span>
                            </td>

                            {{-- Created At --}}
                            <td class="hidden md:table-cell">
                                <span class="text-xs" style="color: var(--dash-text-muted);" title="{{ $invitation->created_at->format('d.m.Y H:i') }}">
                                    {{ $invitation->created_at->diffForHumans() }}
                                </span>
                            </td>

                            {{-- Expires At --}}
                            <td class="hidden lg:table-cell">
                                @if($invitation->expires_at)
                                    <span class="text-xs" style="color: {{ $invitation->is_expired ? 'var(--dash-danger)' : 'var(--dash-text-muted)' }};" title="{{ $invitation->expires_at->format('d.m.Y H:i') }}">
                                        {{ $invitation->expires_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                @if($invitation->can_revoke)
                                    <button wire:click="confirmRevoke({{ $invitation->id }}, '{{ addslashes($invitation->email) }}')"
                                            class="dash-btn-icon dash-btn-danger"
                                            title="Einladung widerrufen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @else
                                    <span style="color: var(--dash-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="dash-empty">
                                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                    </svg>
                                    @if($search || $filterStatus)
                                        <p class="dash-empty-title">Keine Einladungen gefunden</p>
                                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen.</p>
                                        <button wire:click="$set('search', ''); $set('filterStatus', '');" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Filter zurücksetzen
                                        </button>
                                    @else
                                        <p class="dash-empty-title">Noch keine Einladungen verschickt</p>
                                        <a href="{{ route('verwaltung.invitations.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                                            Erste Einladung versenden
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
        @if($invitations->hasPages())
            <div class="dash-pagination">
                {{ $invitations->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="dash-result-count">
            {{ $invitations->total() }} {{ $invitations->total() === 1 ? 'Einladung' : 'Einladungen' }} gefunden
        </div>
    </div>

    {{-- Revoke Invitation Modal --}}
    @if($showRevokeModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true">
            <div class="dash-modal-backdrop" wire:click="cancelRevoke"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--dash-danger-light);">
                            <svg class="w-5 h-5" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="dash-modal-title">Einladung widerrufen</h3>
                            <p class="text-sm" style="color: var(--dash-text-muted);">Der Einladungslink wird sofort ungültig.</p>
                        </div>
                    </div>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        Möchtest du die Einladung an <strong>{{ $revokingEmail }}</strong> wirklich widerrufen?
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button wire:click="cancelRevoke" class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button wire:click="revokeInvitation" class="dash-btn dash-btn-danger">
                        Widerrufen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
