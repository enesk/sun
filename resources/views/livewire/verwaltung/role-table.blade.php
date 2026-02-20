<div>
    {{-- Filter Bar --}}
    <div class="card-portal mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Rollenname suchen..."
                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);">
                </div>
            </div>

            @if($search)
                <button wire:click="$set('search', '')"
                        class="text-sm px-3 py-2.5 text-base-content/60 hover:text-base-content transition-colors"
                        title="Filter zurücksetzen">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
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
                        <th class="p-3 font-semibold text-base-content/70">Rollenname</th>
                        <th class="p-3 hidden md:table-cell font-semibold text-base-content/70">Typ</th>
                        <th class="p-3 text-center font-semibold text-base-content/70">Benutzer</th>
                        <th class="p-3 text-right font-semibold text-base-content/70">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr class="border-b border-base-200/50 hover:bg-base-200/30 transition-colors" wire:key="role-{{ $role->id }}">
                            {{-- Name --}}
                            <td class="p-3">
                                <a href="{{ route('verwaltung.roles.edit', $role->id) }}"
                                   class="text-sm font-medium text-base-content hover:underline">
                                    {{ $role->name }}
                                </a>
                            </td>

                            {{-- Type --}}
                            <td class="p-3 hidden md:table-cell">
                                @if($role->is_global)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                        System
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        Custom
                                    </span>
                                @endif
                            </td>

                            {{-- Users Count --}}
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $role->users_count > 0 ? 'bg-green-100 text-green-700' : 'bg-base-200 text-base-content/50' }}">
                                    {{ $role->users_count }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.roles.edit', $role->id) }}"
                                       class="p-1.5 rounded-lg text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    @if($role->can_delete)
                                        <button wire:click="confirmDelete({{ $role->id }}, '{{ addslashes($role->name) }}')"
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
                            <td colspan="4" class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-base-content/15 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                </svg>
                                @if($search)
                                    <p class="text-sm text-base-content/50 mb-2">Keine Rollen gefunden</p>
                                    <button wire:click="$set('search', '')" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Filter zurücksetzen
                                    </button>
                                @else
                                    <p class="text-sm text-base-content/50 mb-2">Noch keine eigenen Rollen vorhanden</p>
                                    <a href="{{ route('verwaltung.roles.create') }}" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Erste Rolle erstellen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($roles->hasPages())
            <div class="px-4 py-3 border-t border-base-200">
                {{ $roles->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="px-4 py-2 border-t border-base-200 text-xs text-base-content/40">
            {{ $roles->total() }} {{ $roles->total() === 1 ? 'Rolle' : 'Rollen' }} gefunden
        </div>
    </div>

    {{-- Delete Role Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="cancelDelete">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Rolle löschen</h3>
                        <p class="text-sm text-base-content/60">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70 mb-6">
                    Möchtest du die Rolle <strong>{{ $deletingRoleName }}</strong> wirklich löschen?
                </p>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete"
                            class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="deleteRole"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
