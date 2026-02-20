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
                           placeholder="Teamname suchen..."
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
                        <th class="p-3 font-semibold text-base-content/70">Teamname</th>
                        <th class="p-3 text-center font-semibold text-base-content/70">Mitglieder</th>
                        <th class="p-3 text-right font-semibold text-base-content/70">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                        <tr class="border-b border-base-200/50 hover:bg-base-200/30 transition-colors" wire:key="team-{{ $team->id }}">
                            {{-- Name --}}
                            <td class="p-3">
                                <a href="{{ route('verwaltung.teams.edit', $team->uuid) }}"
                                   class="text-sm font-medium text-base-content hover:underline">
                                    {{ $team->name }}
                                </a>
                            </td>

                            {{-- Members Count --}}
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $team->tenant_users_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-base-200 text-base-content/50' }}">
                                    {{ $team->tenant_users_count }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('verwaltung.teams.edit', $team->uuid) }}"
                                       class="p-1.5 rounded-lg text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors"
                                       title="Bearbeiten">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </a>
                                    <button wire:click="confirmDelete('{{ $team->uuid }}', '{{ addslashes($team->name) }}')"
                                            class="p-1.5 rounded-lg text-base-content/50 hover:text-red-600 hover:bg-red-50 transition-colors"
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
                            <td colspan="3" class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-base-content/15 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                </svg>
                                @if($search)
                                    <p class="text-sm text-base-content/50 mb-2">Keine Teams gefunden</p>
                                    <button wire:click="$set('search', '')" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Filter zurücksetzen
                                    </button>
                                @else
                                    <p class="text-sm text-base-content/50 mb-2">Noch keine Teams vorhanden</p>
                                    <a href="{{ route('verwaltung.teams.create') }}" class="text-sm font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
                                        Erstes Team erstellen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($teams->hasPages())
            <div class="px-4 py-3 border-t border-base-200">
                {{ $teams->links() }}
            </div>
        @endif

        {{-- Result count --}}
        <div class="px-4 py-2 border-t border-base-200 text-xs text-base-content/40">
            {{ $teams->total() }} {{ $teams->total() === 1 ? 'Team' : 'Teams' }} gefunden
        </div>
    </div>

    {{-- Delete Team Modal --}}
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
                        <h3 class="text-lg font-semibold text-base-content">Team löschen</h3>
                        <p class="text-sm text-base-content/60">Alle Mitglieder werden aus dem Team entfernt.</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70 mb-6">
                    Möchtest du das Team <strong>{{ $deletingTeamName }}</strong> wirklich löschen?
                </p>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete"
                            class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="deleteTeam"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
