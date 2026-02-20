<div x-data="{ tab: @entangle('activeTab') }">
    <form wire:submit="save">
        {{-- Tab Navigation --}}
        <div class="flex gap-1 mb-6 bg-base-100 rounded-lg p-1 border border-base-200 overflow-x-auto">
            <button type="button" @click="tab = 'workspace'" :class="tab === 'workspace' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all whitespace-nowrap">
                Workspace
            </button>
            <button type="button" @click="tab = 'contact'" :class="tab === 'contact' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all whitespace-nowrap">
                Kontakt & Social
            </button>
            <button type="button" @click="tab = 'seo'" :class="tab === 'seo' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all whitespace-nowrap">
                SEO & Analytics
            </button>
            <button type="button" @click="tab = 'features'" :class="tab === 'features' ? 'bg-white shadow-sm text-base-content' : 'text-base-content/60 hover:text-base-content'" class="px-3 py-2 text-sm font-medium rounded-md transition-all whitespace-nowrap">
                Funktionen
            </button>
        </div>

        {{-- Workspace Tab --}}
        <div x-show="tab === 'workspace'" x-cloak>
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Workspace</h3>
                    <p class="text-sm text-base-content/60 mt-1">Name und Adresse Ihres Portals</p>
                </div>

                {{-- Tenant Name --}}
                <div>
                    <label for="tenantName" class="block text-sm font-medium text-base-content mb-1.5">Portal-Name *</label>
                    <input type="text" id="tenantName" wire:model="tenantName"
                           class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow @error('tenantName') border-red-400 ring-red-100 @enderror"
                           style="focus:ring-color: rgba(var(--portal-primary-rgb, 59,130,246), 0.2); focus:border-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. Firmenfreund.de">
                    @error('tenantName') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Address Section --}}
                <div class="border-t border-base-200 pt-6">
                    <h4 class="text-sm font-semibold text-base-content mb-4">Adresse (für Rechnungen)</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Adresszeile 1</label>
                            <input type="text" wire:model="addressLine1" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="Straße, Firma, c/o">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Adresszeile 2</label>
                            <input type="text" wire:model="addressLine2" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="Gebäude, Etage, etc.">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Stadt</label>
                            <input type="text" wire:model="city" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="Berlin">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Bundesland</label>
                            <input type="text" wire:model="state" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">PLZ</label>
                            <input type="text" wire:model="zip" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="10115">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Land</label>
                            <select wire:model="countryCode" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow">
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Telefon</label>
                            <input type="text" wire:model="phone" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Steuernummer</label>
                            <input type="text" wire:model="taxNumber" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="DE123456789">
                        </div>
                    </div>
                </div>

                {{-- Footer Text --}}
                <div class="border-t border-base-200 pt-6">
                    <label class="block text-sm font-medium text-base-content mb-1.5">Footer-Text</label>
                    <input type="text" wire:model="footerText" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="© {year} {tenant_name}. Alle Rechte vorbehalten.">
                    <p class="text-xs text-base-content/50 mt-1">Variablen: {year}, {tenant_name}</p>
                </div>
            </div>
        </div>

        {{-- Contact & Social Tab --}}
        <div x-show="tab === 'contact'" x-cloak>
            <div class="space-y-6">
                {{-- Contact --}}
                <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Kontaktdaten</h3>
                        <p class="text-sm text-base-content/60 mt-1">Öffentliche Kontaktdaten im Portal-Footer</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">E-Mail</label>
                            <input type="email" wire:model="contactEmail" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="kontakt@portal.de">
                            @error('contactEmail') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Telefon</label>
                            <input type="text" wire:model="contactPhone" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="+49 30 1234567">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Adresse</label>
                        <textarea wire:model="contactAddress" rows="2" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow resize-none" placeholder="Musterstraße 1, 10115 Berlin"></textarea>
                    </div>
                </div>

                {{-- Social --}}
                <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Social Media</h3>
                        <p class="text-sm text-base-content/60 mt-1">Links zu Ihren Social-Media-Profilen</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Facebook</label>
                            <input type="url" wire:model="socialFacebook" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="https://facebook.com/...">
                            @error('socialFacebook') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Instagram</label>
                            <input type="url" wire:model="socialInstagram" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="https://instagram.com/...">
                            @error('socialInstagram') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">X (Twitter)</label>
                            <input type="url" wire:model="socialTwitter" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="https://x.com/...">
                            @error('socialTwitter') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">LinkedIn</label>
                            <input type="url" wire:model="socialLinkedin" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="https://linkedin.com/...">
                            @error('socialLinkedin') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SEO & Analytics Tab --}}
        <div x-show="tab === 'seo'" x-cloak>
            <div class="space-y-6">
                {{-- SEO --}}
                <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">SEO</h3>
                        <p class="text-sm text-base-content/60 mt-1">Suchmaschinen-Optimierung für Ihr Portal</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Seitentitel</label>
                        <input type="text" wire:model="siteTitle" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="Branchenportal Berlin">
                        <p class="text-xs text-base-content/50 mt-1">Wird als &lt;title&gt; Tag und in Suchergebnissen angezeigt</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Meta-Beschreibung</label>
                        <textarea wire:model="siteDescription" rows="2" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow resize-none" placeholder="Finden Sie die besten Unternehmen in Ihrer Region..."></textarea>
                        <p class="text-xs text-base-content/50 mt-1">Max. 160 Zeichen für optimale Darstellung in Google</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Meta-Keywords</label>
                        <input type="text" wire:model="metaKeywords" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow" placeholder="branchenportal, firmenverzeichnis, berlin">
                        <p class="text-xs text-base-content/50 mt-1">Kommagetrennte Keywords</p>
                    </div>
                </div>

                {{-- Analytics --}}
                <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">Analytics</h3>
                        <p class="text-sm text-base-content/60 mt-1">Tracking für Besucherstatistiken</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Google Analytics ID</label>
                            <input type="text" wire:model="googleAnalyticsId" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow font-mono" placeholder="G-XXXXXXXXXX">
                            @error('googleAnalyticsId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-base-content mb-1.5">Google Tag Manager ID</label>
                            <input type="text" wire:model="googleTagManagerId" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow font-mono" placeholder="GTM-XXXXXXX">
                            @error('googleTagManagerId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Features Tab --}}
        <div x-show="tab === 'features'" x-cloak>
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Portal-Funktionen</h3>
                    <p class="text-sm text-base-content/60 mt-1">Aktivieren oder deaktivieren Sie Funktionen Ihres Portals</p>
                </div>

                {{-- Feature Toggles --}}
                <div class="space-y-3">
                    {{-- Bewertungen --}}
                    <div x-data="{ on: @entangle('reviewsEnabled').live }"
                         @click="on = !on"
                         :class="on ? 'border-green-200 bg-green-50/50' : 'border-base-200 bg-white'"
                         class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:shadow-sm group">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : 'bg-base-100 text-base-content/40'"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-base-content">Bewertungen</span>
                                <p class="text-xs text-base-content/50 mt-0.5">Besucher können Firmen bewerten</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : 'text-base-content/40'"
                                  class="text-xs font-medium transition-colors duration-300"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : 'bg-base-300'"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    :style="'--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;'"
                                    role="switch"
                                    :aria-checked="on.toString()">
                                <span :style="'width: 20px; height: 20px; transform: translateX(' + (on ? '22px' : '3px') + '); transition: transform 0.3s cubic-bezier(0.68,-0.55,0.265,1.55);'"
                                      class="inline-block rounded-full bg-white shadow-md"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Registrierung --}}
                    <div x-data="{ on: @entangle('registrationEnabled').live }"
                         @click="on = !on"
                         :class="on ? 'border-green-200 bg-green-50/50' : 'border-base-200 bg-white'"
                         class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:shadow-sm group">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : 'bg-base-100 text-base-content/40'"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-base-content">Registrierung</span>
                                <p class="text-xs text-base-content/50 mt-0.5">Neue Benutzer können sich registrieren</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : 'text-base-content/40'"
                                  class="text-xs font-medium transition-colors duration-300"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : 'bg-base-300'"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    :style="'--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;'"
                                    role="switch"
                                    :aria-checked="on.toString()">
                                <span :style="'width: 20px; height: 20px; transform: translateX(' + (on ? '22px' : '3px') + '); transition: transform 0.3s cubic-bezier(0.68,-0.55,0.265,1.55);'"
                                      class="inline-block rounded-full bg-white shadow-md"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Premium-Einträge --}}
                    <div x-data="{ on: @entangle('premiumListingsEnabled').live }"
                         @click="on = !on"
                         :class="on ? 'border-green-200 bg-green-50/50' : 'border-base-200 bg-white'"
                         class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all duration-300 hover:shadow-sm group">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : 'bg-base-100 text-base-content/40'"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-base-content">Premium-Einträge</span>
                                <p class="text-xs text-base-content/50 mt-0.5">Firmen können auf Premium upgraden</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : 'text-base-content/40'"
                                  class="text-xs font-medium transition-colors duration-300"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : 'bg-base-300'"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    :style="'--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;'"
                                    role="switch"
                                    :aria-checked="on.toString()">
                                <span :style="'width: 20px; height: 20px; transform: translateX(' + (on ? '22px' : '3px') + '); transition: transform 0.3s cubic-bezier(0.68,-0.55,0.265,1.55);'"
                                      class="inline-block rounded-full bg-white shadow-md"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            {{-- Success indicator with smooth Alpine transition --}}
            <span x-data="{ show: @js($saved) }"
                  x-init="if(show) setTimeout(() => show = false, 2500)"
                  x-show="show"
                  x-transition:enter="transition ease-out duration-300"
                  x-transition:enter-start="opacity-0 translate-x-2"
                  x-transition:enter-end="opacity-100 translate-x-0"
                  x-transition:leave="transition ease-in duration-200"
                  x-transition:leave-start="opacity-100"
                  x-transition:leave-end="opacity-0"
                  class="text-sm text-green-600 flex items-center gap-1"
                  style="display: none;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Gespeichert
            </span>
            {{-- Save button — overlay technique: text stays in DOM (keeps width), spinner floats on top --}}
            <button type="submit"
                    class="relative inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 disabled:opacity-50 overflow-hidden"
                    style="background-color: var(--portal-primary, #3b82f6); min-width: 130px;"
                    wire:loading.attr="disabled"
                    wire:target="save">
                <span wire:loading.class="opacity-0" wire:target="save" class="transition-opacity duration-200">Speichern</span>
                <span wire:loading wire:target="save" class="absolute inset-0 flex items-center justify-center">
                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </span>
            </button>
        </div>
    </form>
</div>
