<div>
    {{-- Tab Navigation --}}
    <div class="dash-tab-bar mb-6" x-data="{ tab: @entangle('activeTab') }">
        <button type="button" @click="tab = 'profile'" :class="tab === 'profile' ? 'dash-tab dash-tab-active' : 'dash-tab'">
            Profil
        </button>
        <button type="button" @click="tab = 'password'" :class="tab === 'password' ? 'dash-tab dash-tab-active' : 'dash-tab'">
            Passwort
        </button>
        @if($twoFactorAuthEnabled)
            <button type="button" @click="tab = '2fa'" :class="tab === '2fa' ? 'dash-tab dash-tab-active' : 'dash-tab'">
                Sicherheit (2FA)
            </button>
        @endif
    </div>

    {{-- Profile Tab --}}
    <div x-data="{ tab: @entangle('activeTab') }" x-show="tab === 'profile'" x-cloak>
        <form wire:submit="saveProfile">
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Persönliche Daten</h3>
                    <p class="dash-input-hint">Ihr Name und Ihre E-Mail-Adresse</p>
                </div>

                <div class="dash-form-grid dash-form-grid-2">
                    <div>
                        <label class="dash-label dash-label-required">Name</label>
                        <input type="text" wire:model="name"
                               class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }}">
                        @error('name') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">Öffentlicher Name</label>
                        <input type="text" wire:model="publicName"
                               class="dash-input"
                               placeholder="Wird in Bewertungen angezeigt">
                    </div>
                </div>

                <div>
                    <label class="dash-label dash-label-required">E-Mail</label>
                    <input type="email" wire:model="email"
                           class="dash-input {{ $errors->has('email') ? 'dash-input-error' : '' }}">
                    @error('email') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Save --}}
            <div class="flex items-center justify-end gap-3 mt-6">
                @if($saved)
                    <span class="dash-flash dash-flash-success text-sm" style="padding: 0.375rem 0.75rem;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Gespeichert
                    </span>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="dash-btn dash-btn-primary relative overflow-hidden"
                        wire:target="saveProfile">
                    <span wire:loading.class="opacity-0" wire:target="saveProfile" class="transition-opacity duration-200">Profil speichern</span>
                    <span wire:loading wire:target="saveProfile" class="absolute inset-0 flex items-center justify-center">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- Password Tab --}}
    <div x-show="tab === 'password'" x-cloak>
        <form wire:submit="changePassword">
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Passwort ändern</h3>
                    <p class="dash-input-hint">Aktuelles Passwort bestätigen und neues Passwort setzen</p>
                </div>

                <div>
                    <label class="dash-label dash-label-required">Aktuelles Passwort</label>
                    <input type="password" wire:model="currentPassword"
                           class="dash-input {{ $errors->has('currentPassword') ? 'dash-input-error' : '' }}"
                           autocomplete="current-password">
                    @error('currentPassword') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>

                <div class="dash-form-grid dash-form-grid-2">
                    <div>
                        <label class="dash-label dash-label-required">Neues Passwort</label>
                        <input type="password" wire:model="newPassword"
                               class="dash-input {{ $errors->has('newPassword') ? 'dash-input-error' : '' }}"
                               autocomplete="new-password">
                        @error('newPassword') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        <p class="dash-input-hint">Min. 8 Zeichen, Groß-/Kleinbuchstaben, Zahl</p>
                    </div>
                    <div>
                        <label class="dash-label dash-label-required">Passwort bestätigen</label>
                        <input type="password" wire:model="newPasswordConfirmation"
                               class="dash-input"
                               autocomplete="new-password">
                    </div>
                </div>
            </div>

            {{-- Save --}}
            <div class="flex items-center justify-end gap-3 mt-6">
                @if($passwordChanged)
                    <span class="dash-flash dash-flash-success text-sm" style="padding: 0.375rem 0.75rem;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Passwort geändert
                    </span>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="dash-btn dash-btn-primary relative overflow-hidden"
                        wire:target="changePassword">
                    <span wire:loading.class="opacity-0" wire:target="changePassword" class="transition-opacity duration-200">Passwort ändern</span>
                    <span wire:loading wire:target="changePassword" class="absolute inset-0 flex items-center justify-center">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- 2FA Tab --}}
    @if($twoFactorAuthEnabled)
        <div x-show="tab === '2fa'" x-cloak>
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Zwei-Faktor-Authentifizierung</h3>
                    <p class="dash-input-hint">Zusätzliche Sicherheitsebene für Ihr Konto</p>
                </div>

                @if($twoFactorEnabled)
                    <div class="dash-flash dash-flash-success" role="status" style="border-radius: 0.5rem;">
                        <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium">2FA ist aktiviert</p>
                            <p class="text-xs mt-0.5" style="opacity: 0.85;">Ihr Konto ist durch Zwei-Faktor-Authentifizierung geschützt.</p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <p class="text-sm" style="color: var(--dash-text-secondary);">Um 2FA zu deaktivieren oder Recovery Codes zu erneuern, nutzen Sie bitte die Sicherheitseinstellungen in Ihrem Konto.</p>
                    </div>
                @else
                    <div class="dash-flash dash-flash-warning" role="status" style="border-radius: 0.5rem;">
                        <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium">2FA ist nicht aktiviert</p>
                            <p class="text-xs mt-0.5" style="opacity: 0.85;">Wir empfehlen die Aktivierung von 2FA für zusätzliche Sicherheit.</p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <p class="text-sm mb-3" style="color: var(--dash-text-secondary);">Die Zwei-Faktor-Authentifizierung fügt eine zusätzliche Sicherheitsebene hinzu, indem bei der Anmeldung neben dem Passwort ein zeitbasierter Code aus einer Authenticator-App erforderlich ist.</p>
                        <p class="text-sm" style="color: var(--dash-text-secondary);">Die Einrichtung erfolgt über die Sicherheitseinstellungen in Ihrem Konto.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
