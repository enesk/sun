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
            <h2 class="dash-form-section-title">Einladung</h2>

            {{-- Email Addresses --}}
            <div>
                <label for="emails" class="dash-label dash-label-required">E-Mail-Adressen</label>
                <p class="dash-input-hint mb-2">
                    Mehrere Adressen mit Komma oder Zeilenumbruch trennen.
                </p>
                <textarea id="emails"
                          wire:model="emails"
                          rows="4"
                          placeholder="max@beispiel.de, anna@beispiel.de&#10;peter@beispiel.de"
                          class="dash-textarea {{ $errors->has('emails') ? 'dash-textarea-error' : '' }}"></textarea>
                @error('emails')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div class="mt-4">
                <label for="role" class="dash-label dash-label-required">Rolle</label>
                <select id="role"
                        wire:model="role"
                        class="dash-select {{ $errors->has('role') ? 'dash-input-error' : '' }}">
                    <option value="">Rolle auswählen...</option>
                    @foreach($availableRoles as $roleName)
                        <option value="{{ $roleName }}">{{ ucfirst($roleName) }}</option>
                    @endforeach
                </select>
                @error('role')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Team (optional) --}}
            @if(count($teams) > 0)
                <div class="mt-4">
                    <label for="teamId" class="dash-label">
                        Team <span class="text-xs" style="color: var(--dash-text-muted);">(optional)</span>
                    </label>
                    <select id="teamId"
                            wire:model="teamId"
                            class="dash-select">
                        <option value="">Kein Team</option>
                        @foreach($teams as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="dash-input-hint">
                        Eingeladene Benutzer werden automatisch diesem Team zugeordnet.
                    </p>
                </div>
            @endif
        </div>

        {{-- Info Box --}}
        <div class="dash-flash dash-flash-info" role="note" style="border-radius: 0.5rem;">
            <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <div class="text-sm">
                <p class="font-medium mb-1">Hinweise</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs" style="opacity: 0.85;">
                    <li>Die Einladung wird per E-Mail versendet</li>
                    <li>Der Einladungslink ist 7 Tage gültig</li>
                    <li>Bereits eingeladene oder bestehende Mitglieder werden übersprungen</li>
                </ul>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.invitations.index') }}"
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
                    Einladungen versenden
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
