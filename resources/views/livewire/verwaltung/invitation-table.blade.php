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
                           placeholder="E-Mail-Adresse suchen..."
                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);">
                </div>
            </div>

            {{-- Status Filter --}}
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus"
                        class="text-sm border border-base-200 rounded-lg px-3 py-2.5 bg-base-100 text-base-content/70 focus:outline-none focus:ring-1"
                        aria-label="Status filtern">
                    <option value="">Alle Status</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if($search || $filterStatus)
                    <button wire:click="$set('search', ''); $set('filterStatus', '');"
                            class="text-sm px-3 py-2.5 text-base-content/60 hover:text-base-content transition-colors"
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
        <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Table --}}
    <div class="card-portal overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-base-200 text-left">
                        <th class="p-3 font-semibold text-base-content/70">E-Mail</th>
                        <th class="p-3 hidden md:table-cell font-semibold text-base-content/70">Rolle</th>
                        <th class="p-3 hidden lg:table-cell font-semibold text-base-content/70">Team</th>
                        <th class="p-3 font-semibold text-base-content/70">Status</th>
                        <th class="p-3 hidden md:table-cell font-semibold text-base-content/70">Gesendet</th>
                        <th class="p-3 hidden lg:table-cell font-semibold text-base-content/70">Läuft ab</th>
                        <th class="p-3 text-right font-semibold text-base-content/70">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        <tr class="border-b border-base-200/50 hover:bg-base-200/30 transition-colors" wire:key="invitation-{{ $invitation->id }}">
                            {{-- Email --}}
                            <td class="p-3">
                                <span class="text-sm font-medium text-base-content">{{ $invitation->email }}</span>
                                @if($invitation->user)
                                    <p class="text-xs text-base-content/40">von {{ $invitation->user->name }}</p>
                                @endif
                            </td>

                            {{-- Role --}}
                            <td class="p-3 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-base-200 text-base-content/70">
                                    {{ ucfirst($invitation->role ?? 'user') }}
                                </span>
                            </td>

                            {{-- Team --}}
                            <td class="p-3 hidden lg:table-cell">
                                @if($invitation->team)
                                    <span class="text-sm text-base-content/70">{{ $invitation->team->name }}</span>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="p-3">
                                @php
                                    $statusClasses = match($invitation->status_color) {
                                        'success' => 'bg-green-100 text-green-700',
                                        'error' => 'bg-red-100 text-red-700',
                                        'warning' => 'bg-amber-100 text-amber-700',
                                        'info' => 'bg-blue-100 text-blue-700',
                                        default => 'bg-base-200 text-base-content/70',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                    {{ $invitation->status_label }}
                                </span>
                            </td>

                            {{-- Created At --}}
                            <td class="p-3 hidden md:table-cell">
                                <span class="text-xs text-base-content/50" title="{{ $invitation->created_at->format('d.m.Y H:i') }}">
                                    {{ $invitation->created_at->diffForHumans() }}
                                </span>
                            </td>

                            {{-- Expires At --}}
                            <td class="p-3 hidden lg:table-cell">
                                @if($invitation->expires_at)
                                    <span class="text-xs {{ $invitation->is_expired ? 'text-red-500' : 'text-base-content/50' }}" title="{{ $invitation->expires_at->format('d.m.Y H:i') }}">
                                        {{ $invitation->expires_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right">
                                @if($invitation->can_revoke)
                                    <button wire:click="confirmRevoke({{ $invitation->id }}, '{{ addslashes($invitation->email) }}')"
                                            class="p-1.5 rounded-lg text-base-content/50 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Einladung widerrufen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-base-content/15 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                </svg>
                                @if($search || $filterStatus)
                                    <p class="text-sm text-base-content/50 mb-2">Keine Einladungen gefunden</p>
                                    <button wire:click="$set('search', ''); $set('filterStatus', '');" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Filter zurücksetzen
                                    </button>
                                @else
                                    <p class="text-sm text-base-content/50 mb-2">Noch keine Einladungen verschickt</p>
                                    <a href="{{ route('verwaltung.invitations.create') }}" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Erste Einladung versenden
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($invitations->hasPages())
            <div class="px-4 py-3 border-t border-base-200">
                {{ $invitations->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="px-4 py-2 border-t border-base-200 text-xs text-base-content/40">
            {{ $invitations->total() }} {{ $invitations->total() === 1 ? 'Einladung' : 'Einladungen' }} gefunden
        </div>
    </div>

    {{-- Revoke Invitation Modal --}}
    @if($showRevokeModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="cancelRevoke">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Einladung widerrufen</h3>
                        <p class="text-sm text-base-content/60">Der Einladungslink wird sofort ungültig.</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70 mb-6">
                    Möchtest du die Einladung an <strong>{{ $revokingEmail }}</strong> wirklich widerrufen?
                </p>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelRevoke"
                            class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="revokeInvitation"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Widerrufen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
