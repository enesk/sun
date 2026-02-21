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

    @if($isGlobalRole)
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-warning">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span>Dies ist eine System-Rolle. Der Name kann nicht geändert werden, aber du kannst die Berechtigungen anpassen.</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Rolle</h2>

            {{-- Role Name --}}
            <div>
                <label for="name" class="dash-label dash-label-required">Rollenname</label>
                <input type="text"
                       id="name"
                       wire:model="name"
                       placeholder="z.B. Editor, Moderator, Buchhalter..."
                       {{ $isGlobalRole ? 'disabled' : '' }}
                       class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }} {{ $isGlobalRole ? 'dash-input-disabled' : '' }}">
                @error('name')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Permissions --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Berechtigungen</h2>
            <p class="dash-input-hint mb-4">
                Wähle aus, welche Aktionen Benutzer mit dieser Rolle ausführen dürfen.
            </p>

            @if($permissionGroups->count() > 0)
                <div class="space-y-4">
                    @foreach($permissionGroups as $group => $permissions)
                        <div class="dash-card overflow-hidden">
                            <div class="px-4 py-2.5" style="background-color: var(--dash-bg-secondary); border-bottom: 1px solid var(--dash-border);">
                                <h4 class="text-sm font-semibold" style="color: var(--dash-text-secondary);">{{ $group }}</h4>
                            </div>
                            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($permissions as $permission)
                                    <label class="dash-checkbox-card">
                                        <input type="checkbox"
                                               wire:model="selectedPermissions"
                                               value="{{ $permission->id }}"
                                               class="dash-checkbox">
                                        <span class="text-sm" style="color: var(--dash-text-secondary);">{{ $permission->display_name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm italic" style="color: var(--dash-text-muted);">Keine Berechtigungen verfügbar.</p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.roles.index') }}"
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
                    {{ $roleId ? 'Aktualisieren' : 'Rolle erstellen' }}
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
