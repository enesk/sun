<div x-data="{ tab: @entangle('activeTab') }">
    <form wire:submit="save">
        {{-- Tab Navigation --}}
        <div class="dash-tab-bar mb-6">
            <button type="button" @click="tab = 'workspace'" :class="tab === 'workspace' ? 'dash-tab dash-tab-active' : 'dash-tab'" class="whitespace-nowrap">
                Workspace
            </button>
            <button type="button" @click="tab = 'contact'" :class="tab === 'contact' ? 'dash-tab dash-tab-active' : 'dash-tab'" class="whitespace-nowrap">
                Kontakt & Social
            </button>
            <button type="button" @click="tab = 'seo'" :class="tab === 'seo' ? 'dash-tab dash-tab-active' : 'dash-tab'" class="whitespace-nowrap">
                SEO & Analytics
            </button>
            <button type="button" @click="tab = 'features'" :class="tab === 'features' ? 'dash-tab dash-tab-active' : 'dash-tab'" class="whitespace-nowrap">
                Funktionen
            </button>
        </div>

        {{-- Workspace Tab --}}
        <div x-show="tab === 'workspace'" x-cloak>
            <div class="dash-card dash-card-padded space-y-6">
                <div>
                    <h3 class="dash-form-section-title">Workspace</h3>
                    <p class="dash-input-hint">Name und Adresse Ihres Portals</p>
                </div>

                {{-- Tenant Name --}}
                <div>
                    <label for="tenantName" class="dash-label dash-label-required">Portal-Name</label>
                    <input type="text" id="tenantName" wire:model="tenantName"
                           class="dash-input {{ $errors->has('tenantName') ? 'dash-input-error' : '' }}"
                           placeholder="z.B. Firmenfreund.de">
                    @error('tenantName') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>

                {{-- Address Section --}}
                <div style="border-top: 1px solid var(--dash-border); padding-top: 1.5rem;">
                    <h4 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary);">Adresse (für Rechnungen)</h4>
                    <div class="dash-form-grid dash-form-grid-2">
                        <div>
                            <label class="dash-label">Adresszeile 1</label>
                            <input type="text" wire:model="addressLine1" class="dash-input" placeholder="Straße, Firma, c/o">
                        </div>
                        <div>
                            <label class="dash-label">Adresszeile 2</label>
                            <input type="text" wire:model="addressLine2" class="dash-input" placeholder="Gebäude, Etage, etc.">
                        </div>
                        <div>
                            <label class="dash-label">Stadt</label>
                            <input type="text" wire:model="city" class="dash-input" placeholder="Berlin">
                        </div>
                        <div>
                            <label class="dash-label">Bundesland</label>
                            <input type="text" wire:model="state" class="dash-input">
                        </div>
                        <div>
                            <label class="dash-label">PLZ</label>
                            <input type="text" wire:model="zip" class="dash-input" placeholder="10115">
                        </div>
                        <div>
                            <label class="dash-label">Land</label>
                            <select wire:model="countryCode" class="dash-select">
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                            </select>
                        </div>
                        <div>
                            <label class="dash-label">Telefon</label>
                            <input type="text" wire:model="phone" class="dash-input">
                        </div>
                        <div>
                            <label class="dash-label">Steuernummer</label>
                            <input type="text" wire:model="taxNumber" class="dash-input" placeholder="DE123456789">
                        </div>
                    </div>
                </div>

                {{-- Footer Text --}}
                <div style="border-top: 1px solid var(--dash-border); padding-top: 1.5rem;">
                    <label class="dash-label">Footer-Text</label>
                    <input type="text" wire:model="footerText" class="dash-input" placeholder="© {year} {tenant_name}. Alle Rechte vorbehalten.">
                    <p class="dash-input-hint">Variablen: {year}, {tenant_name}</p>
                </div>
            </div>
        </div>

        {{-- Contact & Social Tab --}}
        <div x-show="tab === 'contact'" x-cloak>
            <div class="space-y-6">
                {{-- Contact --}}
                <div class="dash-card dash-card-padded space-y-4">
                    <div>
                        <h3 class="dash-form-section-title">Kontaktdaten</h3>
                        <p class="dash-input-hint">Öffentliche Kontaktdaten im Portal-Footer</p>
                    </div>
                    <div class="dash-form-grid dash-form-grid-2">
                        <div>
                            <label class="dash-label">E-Mail</label>
                            <input type="email" wire:model="contactEmail" class="dash-input {{ $errors->has('contactEmail') ? 'dash-input-error' : '' }}" placeholder="kontakt@portal.de">
                            @error('contactEmail') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="dash-label">Telefon</label>
                            <input type="text" wire:model="contactPhone" class="dash-input" placeholder="+49 30 1234567">
                        </div>
                    </div>
                    <div>
                        <label class="dash-label">Adresse</label>
                        <textarea wire:model="contactAddress" rows="2" class="dash-textarea" placeholder="Musterstraße 1, 10115 Berlin"></textarea>
                    </div>
                </div>

                {{-- Social --}}
                <div class="dash-card dash-card-padded space-y-4">
                    <div>
                        <h3 class="dash-form-section-title">Social Media</h3>
                        <p class="dash-input-hint">Links zu Ihren Social-Media-Profilen</p>
                    </div>
                    <div class="dash-form-grid dash-form-grid-2">
                        <div>
                            <label class="dash-label">Facebook</label>
                            <input type="url" wire:model="socialFacebook" class="dash-input {{ $errors->has('socialFacebook') ? 'dash-input-error' : '' }}" placeholder="https://facebook.com/...">
                            @error('socialFacebook') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="dash-label">Instagram</label>
                            <input type="url" wire:model="socialInstagram" class="dash-input {{ $errors->has('socialInstagram') ? 'dash-input-error' : '' }}" placeholder="https://instagram.com/...">
                            @error('socialInstagram') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="dash-label">X (Twitter)</label>
                            <input type="url" wire:model="socialTwitter" class="dash-input {{ $errors->has('socialTwitter') ? 'dash-input-error' : '' }}" placeholder="https://x.com/...">
                            @error('socialTwitter') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="dash-label">LinkedIn</label>
                            <input type="url" wire:model="socialLinkedin" class="dash-input {{ $errors->has('socialLinkedin') ? 'dash-input-error' : '' }}" placeholder="https://linkedin.com/...">
                            @error('socialLinkedin') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SEO & Analytics Tab --}}
        <div x-show="tab === 'seo'" x-cloak>
            <div class="space-y-6">
                {{-- SEO --}}
                <div class="dash-card dash-card-padded space-y-4">
                    <div>
                        <h3 class="dash-form-section-title">SEO</h3>
                        <p class="dash-input-hint">Suchmaschinen-Optimierung für Ihr Portal</p>
                    </div>
                    <div>
                        <label class="dash-label">Seitentitel</label>
                        <input type="text" wire:model="siteTitle" class="dash-input" placeholder="Branchenportal Berlin">
                        <p class="dash-input-hint">Wird als &lt;title&gt; Tag und in Suchergebnissen angezeigt</p>
                    </div>
                    <div>
                        <label class="dash-label">Meta-Beschreibung</label>
                        <textarea wire:model="siteDescription" rows="2" class="dash-textarea" placeholder="Finden Sie die besten Unternehmen in Ihrer Region..."></textarea>
                        <p class="dash-input-hint">Max. 160 Zeichen für optimale Darstellung in Google</p>
                    </div>
                    <div>
                        <label class="dash-label">Meta-Keywords</label>
                        <input type="text" wire:model="metaKeywords" class="dash-input" placeholder="branchenportal, firmenverzeichnis, berlin">
                        <p class="dash-input-hint">Kommagetrennte Keywords</p>
                    </div>
                </div>

                {{-- Analytics --}}
                <div class="dash-card dash-card-padded space-y-4">
                    <div>
                        <h3 class="dash-form-section-title">Analytics</h3>
                        <p class="dash-input-hint">Tracking für Besucherstatistiken</p>
                    </div>
                    <div class="dash-form-grid dash-form-grid-2">
                        <div>
                            <label class="dash-label">Google Analytics ID</label>
                            <input type="text" wire:model="googleAnalyticsId" class="dash-input font-mono {{ $errors->has('googleAnalyticsId') ? 'dash-input-error' : '' }}" placeholder="G-XXXXXXXXXX">
                            @error('googleAnalyticsId') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="dash-label">Google Tag Manager ID</label>
                            <input type="text" wire:model="googleTagManagerId" class="dash-input font-mono {{ $errors->has('googleTagManagerId') ? 'dash-input-error' : '' }}" placeholder="GTM-XXXXXXX">
                            @error('googleTagManagerId') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Features Tab --}}
        <div x-show="tab === 'features'" x-cloak>
            <div class="dash-card dash-card-padded space-y-6">
                <div>
                    <h3 class="dash-form-section-title">Portal-Funktionen</h3>
                    <p class="dash-input-hint">Aktivieren oder deaktivieren Sie Funktionen Ihres Portals</p>
                </div>

                {{-- Feature Toggles --}}
                <div class="space-y-3">
                    {{-- Bewertungen --}}
                    <div x-data="{ on: @entangle('reviewsEnabled').live }"
                         @click="on = !on"
                         :class="on ? 'border-green-200 bg-green-50/50' : ''"
                         class="dash-card flex items-center justify-between p-4 cursor-pointer transition-all duration-300 hover:shadow-sm group"
                         style="border-width: 2px; border-radius: 0.75rem;">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : ''"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300"
                                 :style="!on ? 'background-color: var(--dash-bg-secondary); color: var(--dash-text-muted);' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold" style="color: var(--dash-text-primary);">Bewertungen</span>
                                <p class="text-xs mt-0.5" style="color: var(--dash-text-muted);">Besucher können Firmen bewerten</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : ''"
                                  class="text-xs font-medium transition-colors duration-300"
                                  :style="!on ? 'color: var(--dash-text-muted);' : ''"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : ''"
                                    :style="!on ? 'background-color: var(--dash-border);' : ''"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    style="--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;"
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
                         :class="on ? 'border-green-200 bg-green-50/50' : ''"
                         class="dash-card flex items-center justify-between p-4 cursor-pointer transition-all duration-300 hover:shadow-sm group"
                         style="border-width: 2px; border-radius: 0.75rem;">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : ''"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300"
                                 :style="!on ? 'background-color: var(--dash-bg-secondary); color: var(--dash-text-muted);' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold" style="color: var(--dash-text-primary);">Registrierung</span>
                                <p class="text-xs mt-0.5" style="color: var(--dash-text-muted);">Neue Benutzer können sich registrieren</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : ''"
                                  class="text-xs font-medium transition-colors duration-300"
                                  :style="!on ? 'color: var(--dash-text-muted);' : ''"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : ''"
                                    :style="!on ? 'background-color: var(--dash-border);' : ''"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    style="--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;"
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
                         :class="on ? 'border-green-200 bg-green-50/50' : ''"
                         class="dash-card flex items-center justify-between p-4 cursor-pointer transition-all duration-300 hover:shadow-sm group"
                         style="border-width: 2px; border-radius: 0.75rem;">
                        <div class="flex items-center gap-3">
                            <div :class="on ? 'bg-green-100 text-green-600' : ''"
                                 class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-300"
                                 :style="!on ? 'background-color: var(--dash-bg-secondary); color: var(--dash-text-muted);' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            </div>
                            <div>
                                <span class="text-sm font-semibold" style="color: var(--dash-text-primary);">Premium-Einträge</span>
                                <p class="text-xs mt-0.5" style="color: var(--dash-text-muted);">Firmen können auf Premium upgraden</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="on ? 'text-green-600' : ''"
                                  class="text-xs font-medium transition-colors duration-300"
                                  :style="!on ? 'color: var(--dash-text-muted);' : ''"
                                  x-text="on ? 'Aktiv' : 'Aus'"></span>
                            <button type="button"
                                    @click.stop="on = !on"
                                    :class="on ? 'bg-green-500' : ''"
                                    :style="!on ? 'background-color: var(--dash-border);' : ''"
                                    class="relative inline-flex shrink-0 items-center rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    style="--tw-ring-color: var(--portal-primary, #3b82f6); width: 48px; height: 28px;"
                                    role="switch"
                                    :aria-checked="on.toString()">
                                <span :style="'width: 20px; height: 20px; transform: translateX(' + (on ? '22px' : '3px') + '); transition: transform 0.3s cubic-bezier(0.68,-0.55,0.265,1.55);'"
                                      class="inline-block rounded-full bg-white shadow-md"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- URL-Konfiguration --}}
                <div style="border-top: 1px solid var(--dash-border); padding-top: 1.5rem;">
                    <h4 class="text-sm font-semibold mb-1" style="color: var(--dash-text-primary);">Firmen-URL-Format</h4>
                    <p class="dash-input-hint mb-4">Bestimmt wie Firmen-URLs in Ihrem Portal aufgebaut sind</p>

                    <div class="space-y-2">
                        @foreach(\App\Services\CompanyUrlService::PATTERNS as $key => $info)
                            <label @click="$wire.set('companyUrlPattern', '{{ $key }}')"
                                   :class="$wire.companyUrlPattern === '{{ $key }}' ? 'border-2' : 'border'"
                                   :style="$wire.companyUrlPattern === '{{ $key }}' ? 'border-color: var(--portal-primary); background: rgba(var(--portal-primary-rgb, 59,130,246), 0.04);' : ''"
                                   class="dash-card flex items-start gap-3 p-4 cursor-pointer transition-all duration-200 hover:shadow-sm"
                                   style="border-radius: 0.75rem;">
                                <div class="mt-0.5">
                                    <div :class="$wire.companyUrlPattern === '{{ $key }}' ? '' : ''"
                                         :style="$wire.companyUrlPattern === '{{ $key }}' ? 'border-color: var(--portal-primary); background: var(--portal-primary);' : 'border-color: var(--dash-border);'"
                                         class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all duration-200">
                                        <div x-show="$wire.companyUrlPattern === '{{ $key }}'" class="w-2 h-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-semibold font-mono" style="color: var(--dash-text-primary);">{{ $info['label'] }}</span>
                                    <p class="text-xs mt-1 font-mono" style="color: var(--dash-text-muted);">{{ $info['example'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('companyUrlPattern') <p class="dash-input-error-msg mt-2">{{ $message }}</p> @enderror
                    <p class="dash-input-hint mt-3">
                        <svg class="w-4 h-4 inline-block" style="color: var(--portal-accent, #f59e0b);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        Bestehende URLs werden automatisch per 301-Redirect weitergeleitet.
                    </p>
                </div>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <span x-data="{ show: @js($saved) }"
                  x-init="if(show) setTimeout(() => show = false, 2500)"
                  x-show="show"
                  x-transition:enter="transition ease-out duration-300"
                  x-transition:enter-start="opacity-0 translate-x-2"
                  x-transition:enter-end="opacity-100 translate-x-0"
                  x-transition:leave="transition ease-in duration-200"
                  x-transition:leave-start="opacity-100"
                  x-transition:leave-end="opacity-0"
                  class="dash-flash dash-flash-success text-sm"
                  style="display: none; padding: 0.375rem 0.75rem;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Gespeichert
            </span>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="dash-btn dash-btn-primary relative overflow-hidden"
                    style="min-width: 130px;"
                    wire:target="save">
                <span wire:loading.class="opacity-0" wire:target="save" class="transition-opacity duration-200">Speichern</span>
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
