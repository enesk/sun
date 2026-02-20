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
                           placeholder="Name oder E-Mail suchen..."
                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);">
                </div>
            </div>

            {{-- Role Filter --}}
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterRole"
                        class="text-sm border border-base-200 rounded-lg px-3 py-2.5 bg-base-100 text-base-content/70 focus:outline-none focus:ring-1"
                        aria-label="Rolle filtern">
                    <option value="">Alle Rollen</option>
                    @foreach($availableRoles as $role)
                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                    @endforeach
                </select>

                @if($search || $filterRole)
                    <button wire:click="$set('search', ''); $set('filterRole', '');"
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
                        <th class="p-3">
                            <button wire:click="sort('name')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                Name
                                @if($sortBy === 'name')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3">
                            <button wire:click="sort('email')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                E-Mail
                                @if($sortBy === 'email')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3 hidden md:table-cell">Rolle</th>
                        <th class="p-3 hidden lg:table-cell">
                            <button wire:click="sort('last_seen_at')" class="flex items-center gap-1 font-semibold text-base-content/70 hover:text-base-content">
                                Zuletzt aktiv
                                @if($sortBy === 'last_seen_at')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDir === 'asc' ? 'M4.5 15.75l7.5-7.5 7.5 7.5' : 'M19.5 8.25l-7.5 7.5-7.5-7.5' }}"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="p-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-base-200/50 hover:bg-base-200/30 transition-colors {{ $user->is_current_user ? 'bg-blue-50/30' : '' }}" wire:key="user-{{ $user->id }}">
                            {{-- Name + Avatar --}}
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                         style="background-color: var(--portal-primary, #3b82f6);">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-base-content">
                                            {{ $user->name }}
                                            @if($user->is_current_user)
                                                <span class="text-xs text-base-content/40">(Du)</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="p-3">
                                <span class="text-sm text-base-content/70">{{ $user->email }}</span>
                            </td>

                            {{-- Role --}}
                            <td class="p-3 hidden md:table-cell">
                                @if(!$user->is_current_user)
                                    <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                            class="text-xs border border-base-200 rounded-lg px-2 py-1.5 bg-base-100 text-base-content/70 focus:outline-none focus:ring-1"
                                            aria-label="Rolle für {{ $user->name }}">
                                        @foreach($availableRoles as $role)
                                            <option value="{{ $role }}" {{ $user->primary_role === $role ? 'selected' : '' }}>
                                                {{ ucfirst($role) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-base-200 text-base-content/70">
                                        {{ ucfirst($user->primary_role) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Last Seen --}}
                            <td class="p-3 hidden lg:table-cell">
                                @if($user->last_seen_at)
                                    <span class="text-xs text-base-content/50" title="{{ $user->last_seen_at->format('d.m.Y H:i') }}">
                                        {{ $user->last_seen_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-xs text-base-content/30">Nie</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right">
                                @if(!$user->is_current_user)
                                    <button wire:click="confirmRemove({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                            class="p-1.5 rounded-lg text-base-content/50 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Aus Workspace entfernen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-xs text-base-content/30">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-base-content/15 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                                </svg>
                                @if($search || $filterRole)
                                    <p class="text-sm text-base-content/50 mb-2">Keine Benutzer gefunden</p>
                                    <button wire:click="$set('search', ''); $set('filterRole', '');" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Filter zurücksetzen
                                    </button>
                                @else
                                    <p class="text-sm text-base-content/50">Noch keine Benutzer im Workspace</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="px-4 py-3 border-t border-base-200">
                {{ $users->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="px-4 py-2 border-t border-base-200 text-xs text-base-content/40">
            {{ $users->total() }} {{ $users->total() === 1 ? 'Benutzer' : 'Benutzer' }} gefunden
        </div>
    </div>

    {{-- Remove User Modal --}}
    @if($showRemoveModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="cancelRemove">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Benutzer entfernen</h3>
                        <p class="text-sm text-base-content/60">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70 mb-6">
                    Möchtest du <strong>{{ $removingUserName }}</strong> wirklich aus dem Workspace entfernen? Der Benutzer verliert sofort den Zugriff.
                </p>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelRemove"
                            class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="removeUser"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Entfernen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
