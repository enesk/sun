<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-error">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Team</h2>

            {{-- Team Name --}}
            <div>
                <label for="name" class="dash-label dash-label-required">Teamname</label>
                <input type="text"
                       id="name"
                       wire:model="name"
                       placeholder="z.B. Marketing, Vertrieb, Support..."
                       class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }}">
                @error('name')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Roles --}}
            <div class="mt-6">
                <label class="dash-label">Rollen zuweisen</label>
                <p class="dash-input-hint mb-3">
                    Mitglieder dieses Teams erben automatisch die hier zugewiesenen Rollen.
                </p>

                @if($availableRoles->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($availableRoles as $role)
                            <label class="dash-checkbox-card {{ in_array((string) $role->id, $selectedRoles) ? 'dash-checkbox-card-active' : '' }}">
                                <input type="checkbox"
                                       wire:model="selectedRoles"
                                       value="{{ $role->id }}"
                                       class="dash-checkbox">
                                <div>
                                    <span class="text-sm font-medium" style="color: var(--dash-text-primary);">{{ $role->name }}</span>
                                    @if($role->tenant_id === null)
                                        <span class="text-xs" style="color: var(--dash-text-muted);">(System)</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm italic" style="color: var(--dash-text-muted);">Keine Rollen verfügbar.</p>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.teams.index') }}"
               class="dash-btn dash-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Abbrechen
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="dash-btn dash-btn-primary relative overflow-hidden"
                    wire:target="save">
                <span wire:loading.class="opacity-0" wire:target="save" class="transition-opacity duration-200">
                    {{ $teamUuid ? 'Aktualisieren' : 'Team erstellen' }}
                </span>
                <span wire:loading wire:target="save" class="absolute inset-0 flex items-center justify-center">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                </span>
            </button>
        </div>
    </form>
</div>
