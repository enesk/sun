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
                {{-- Email Addresses --}}
                <div>
                    <label for="emails" class="block text-sm font-medium text-base-content mb-1.5">
                        E-Mail-Adressen <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-base-content/50 mb-2">
                        Mehrere Adressen mit Komma oder Zeilenumbruch trennen.
                    </p>
                    <textarea id="emails"
                              wire:model="emails"
                              rows="4"
                              placeholder="max@beispiel.de, anna@beispiel.de&#10;peter@beispiel.de"
                              class="w-full px-4 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent resize-y {{ $errors->has('emails') ? 'border-red-300 focus:ring-red-500' : 'border-base-200' }}"
                              style="{{ !$errors->has('emails') ? 'focus:ring-color: var(--portal-primary, #3b82f6)' : '' }}"></textarea>
                    @error('emails')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Role --}}
                <div>
                    <label for="role" class="block text-sm font-medium text-base-content mb-1.5">
                        Rolle <span class="text-red-500">*</span>
                    </label>
                    <select id="role"
                            wire:model="role"
                            class="w-full px-4 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('role') ? 'border-red-300 focus:ring-red-500' : 'border-base-200' }}"
                            style="{{ !$errors->has('role') ? 'focus:ring-color: var(--portal-primary, #3b82f6)' : '' }}">
                        <option value="">Rolle auswählen...</option>
                        @foreach($availableRoles as $roleName)
                            <option value="{{ $roleName }}">{{ ucfirst($roleName) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Team (optional) --}}
                @if(count($teams) > 0)
                    <div>
                        <label for="teamId" class="block text-sm font-medium text-base-content mb-1.5">
                            Team <span class="text-xs text-base-content/40">(optional)</span>
                        </label>
                        <select id="teamId"
                                wire:model="teamId"
                                class="w-full px-4 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                                style="focus:ring-color: var(--portal-primary, #3b82f6);">
                            <option value="">Kein Team</option>
                            @foreach($teams as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-base-content/40">
                            Eingeladene Benutzer werden automatisch diesem Team zugeordnet.
                        </p>
                    </div>
                @endif

                {{-- Info Box --}}
                <div class="p-4 rounded-lg bg-blue-50/50 border border-blue-100">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                        </svg>
                        <div class="text-sm text-blue-700/80">
                            <p class="font-medium mb-1">Hinweise</p>
                            <ul class="list-disc list-inside space-y-0.5 text-xs">
                                <li>Die Einladung wird per E-Mail versendet</li>
                                <li>Der Einladungslink ist 7 Tage gültig</li>
                                <li>Bereits eingeladene oder bestehende Mitglieder werden übersprungen</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-base-200 bg-base-100/50">
                <a href="{{ route('verwaltung.invitations.index') }}"
                   class="px-4 py-2 text-sm font-medium text-base-content/70 border border-base-200 rounded-lg hover:bg-base-200/50 transition-colors">
                    Abbrechen
                </a>
                <button type="submit"
                        class="px-6 py-2.5 text-sm font-medium text-white rounded-lg transition-all hover:opacity-90 shadow-sm"
                        style="background-color: var(--portal-primary, #3b82f6);">
                    Einladungen versenden
                </button>
            </div>
        </div>
    </form>
</div>
