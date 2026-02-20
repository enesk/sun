<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="save">
        <div class="card-portal">
            <div class="space-y-6 p-6">
                {{-- Team Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-base-content mb-1.5">
                        Teamname <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           wire:model="name"
                           placeholder="z.B. Marketing, Vertrieb, Support..."
                           class="w-full px-4 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('name') ? 'border-red-300 focus:ring-red-500' : 'border-base-200' }}"
                           style="{{ !$errors->has('name') ? 'focus:ring-color: var(--portal-primary, #3b82f6)' : '' }}">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Roles --}}
                <div>
                    <label class="block text-sm font-medium text-base-content mb-1.5">
                        Rollen zuweisen
                    </label>
                    <p class="text-xs text-base-content/50 mb-3">
                        Mitglieder dieses Teams erben automatisch die hier zugewiesenen Rollen.
                    </p>

                    @if($availableRoles->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-3 p-3 rounded-lg border border-base-200 hover:border-base-300 cursor-pointer transition-colors {{ in_array((string) $role->id, $selectedRoles) ? 'bg-blue-50/50 border-blue-200' : '' }}">
                                    <input type="checkbox"
                                           wire:model="selectedRoles"
                                           value="{{ $role->id }}"
                                           class="rounded border-base-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-base-content">{{ $role->name }}</span>
                                        @if($role->tenant_id === null)
                                            <span class="text-xs text-base-content/40 ml-1">(System)</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-base-content/50 italic">Keine Rollen verfügbar.</p>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-base-200 bg-base-100/50">
                <a href="{{ route('verwaltung.teams.index') }}"
                   class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                    Abbrechen
                </a>
                <button type="submit"
                        class="px-6 py-2.5 text-sm font-medium text-white rounded-lg transition-all hover:opacity-90 shadow-sm"
                        style="background-color: var(--portal-primary, #3b82f6);">
                    {{ $teamUuid ? 'Aktualisieren' : 'Team erstellen' }}
                </button>
            </div>
        </div>
    </form>
</div>
