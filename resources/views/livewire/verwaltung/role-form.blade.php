<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if($isGlobalRole)
        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <span>Dies ist eine System-Rolle. Der Name kann nicht geändert werden, aber du kannst die Berechtigungen anpassen.</span>
        </div>
    @endif

    <form wire:submit="save">
        <div class="card-portal">
            <div class="space-y-6 p-6">
                {{-- Role Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-base-content mb-1.5">
                        Rollenname <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           wire:model="name"
                           placeholder="z.B. Editor, Moderator, Buchhalter..."
                           {{ $isGlobalRole ? 'disabled' : '' }}
                           class="w-full px-4 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('name') ? 'border-red-300 focus:ring-red-500' : 'border-base-200' }} {{ $isGlobalRole ? 'bg-base-200/50 cursor-not-allowed' : '' }}"
                           style="{{ !$errors->has('name') ? 'focus:ring-color: var(--portal-primary, #3b82f6)' : '' }}">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Permissions --}}
                <div>
                    <label class="block text-sm font-medium text-base-content mb-1.5">
                        Berechtigungen
                    </label>
                    <p class="text-xs text-base-content/50 mb-4">
                        Wähle aus, welche Aktionen Benutzer mit dieser Rolle ausführen dürfen.
                    </p>

                    @if($permissionGroups->count() > 0)
                        <div class="space-y-4">
                            @foreach($permissionGroups as $group => $permissions)
                                <div class="border border-base-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-2.5 bg-base-200/30 border-b border-base-200">
                                        <h4 class="text-sm font-semibold text-base-content/70">{{ $group }}</h4>
                                    </div>
                                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($permissions as $permission)
                                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200/30 cursor-pointer transition-colors">
                                                <input type="checkbox"
                                                       wire:model="selectedPermissions"
                                                       value="{{ $permission->id }}"
                                                       class="rounded border-base-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm text-base-content/80">{{ $permission->display_name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-base-content/50 italic">Keine Berechtigungen verfügbar.</p>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-base-200 bg-base-100/50">
                <a href="{{ route('verwaltung.roles.index') }}"
                   class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                    Abbrechen
                </a>
                <button type="submit"
                        class="px-6 py-2.5 text-sm font-medium text-white rounded-lg transition-all hover:opacity-90 shadow-sm"
                        style="background-color: var(--portal-primary, #3b82f6);">
                    {{ $roleId ? 'Aktualisieren' : 'Rolle erstellen' }}
                </button>
            </div>
        </div>
    </form>
</div>
