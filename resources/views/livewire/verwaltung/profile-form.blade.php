<div>
    {{-- Tab Navigation --}}
    <div class="flex gap-1 mb-6 bg-base-100 rounded-lg p-1 border border-base-200" x-data="{ tab: @entangle('activeTab') }">
        <button type="button" @click="tab = 'profile'" :class="tab === 'profile' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all">
            Profil
        </button>
        <button type="button" @click="tab = 'password'" :class="tab === 'password' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all">
            Passwort
        </button>
        @if($twoFactorAuthEnabled)
            <button type="button" @click="tab = '2fa'" :class="tab === '2fa' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all">
                Sicherheit (2FA)
            </button>
        @endif
    </div>

    {{-- Profile Tab --}}
    <div x-data="{ tab: @entangle('activeTab') }" x-show="tab === 'profile'" x-cloak>
        <form wire:submit="saveProfile">
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Persönliche Daten</h3>
                    <p class="text-sm text-base-content/60 mt-1">Ihr Name und Ihre E-Mail-Adresse</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Name *</label>
                        <input type="text" wire:model="name"
                               class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow @error('name') border-red-400 @enderror">
                        @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Öffentlicher Name</label>
                        <input type="text" wire:model="publicName"
                               class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow"
                               placeholder="Wird in Bewertungen angezeigt">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-base-content mb-1.5">E-Mail *</label>
                    <input type="email" wire:model="email"
                           class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow @error('email') border-red-400 @enderror">
                    @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Save --}}
            <div class="flex items-center justify-end gap-3 mt-6">
                @if($saved)
                    <span class="text-sm text-green-600 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Gespeichert
                    </span>
                @endif
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-sm"
                        style="background-color: var(--portal-primary, #3b82f6);">
                    <span wire:loading.remove wire:target="saveProfile">Profil speichern</span>
                    <span wire:loading wire:target="saveProfile">Wird gespeichert...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Password Tab --}}
    <div x-show="tab === 'password'" x-cloak>
        <form wire:submit="changePassword">
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Passwort ändern</h3>
                    <p class="text-sm text-base-content/60 mt-1">Aktuelles Passwort bestätigen und neues Passwort setzen</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-base-content mb-1.5">Aktuelles Passwort *</label>
                    <input type="password" wire:model="currentPassword"
                           class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow @error('currentPassword') border-red-400 @enderror"
                           autocomplete="current-password">
                    @error('currentPassword') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Neues Passwort *</label>
                        <input type="password" wire:model="newPassword"
                               class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow @error('newPassword') border-red-400 @enderror"
                               autocomplete="new-password">
                        @error('newPassword') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-base-content/50 mt-1">Min. 8 Zeichen, Groß-/Kleinbuchstaben, Zahl</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Passwort bestätigen *</label>
                        <input type="password" wire:model="newPasswordConfirmation"
                               class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow"
                               autocomplete="new-password">
                    </div>
                </div>
            </div>

            {{-- Save --}}
            <div class="flex items-center justify-end gap-3 mt-6">
                @if($passwordChanged)
                    <span class="text-sm text-green-600 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Passwort geändert
                    </span>
                @endif
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-sm"
                        style="background-color: var(--portal-primary, #3b82f6);">
                    <span wire:loading.remove wire:target="changePassword">Passwort ändern</span>
                    <span wire:loading wire:target="changePassword">Wird geändert...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- 2FA Tab --}}
    @if($twoFactorAuthEnabled)
        <div x-show="tab === '2fa'" x-cloak>
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Zwei-Faktor-Authentifizierung</h3>
                    <p class="text-sm text-base-content/60 mt-1">Zusätzliche Sicherheitsebene für Ihr Konto</p>
                </div>

                @if($twoFactorEnabled)
                    {{-- 2FA is active --}}
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50 border border-green-200">
                        <svg class="w-5 h-5 text-green-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-green-800">2FA ist aktiviert</p>
                            <p class="text-xs text-green-700 mt-0.5">Ihr Konto ist durch Zwei-Faktor-Authentifizierung geschützt.</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <p class="text-sm text-base-content/60">Um 2FA zu deaktivieren oder Recovery Codes zu erneuern, nutzen Sie bitte die Sicherheitseinstellungen in Ihrem Konto.</p>
                    </div>
                @else
                    {{-- 2FA is not active --}}
                    <div class="flex items-start gap-3 p-4 rounded-lg bg-amber-50 border border-amber-200">
                        <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-amber-800">2FA ist nicht aktiviert</p>
                            <p class="text-xs text-amber-700 mt-0.5">Wir empfehlen die Aktivierung von 2FA für zusätzliche Sicherheit.</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <p class="text-sm text-base-content/60 mb-3">Die Zwei-Faktor-Authentifizierung fügt eine zusätzliche Sicherheitsebene hinzu, indem bei der Anmeldung neben dem Passwort ein zeitbasierter Code aus einer Authenticator-App erforderlich ist.</p>
                        <p class="text-sm text-base-content/60">Die Einrichtung erfolgt über die Sicherheitseinstellungen in Ihrem Konto.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
