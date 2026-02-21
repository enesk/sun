<div>
    <form wire:submit="save">
        <div class="space-y-6">
            {{-- Theme Selection --}}
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Theme-Auswahl</h3>
                    <p class="dash-input-hint">Wählen Sie das Design für Ihr Portal</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($availableThemes as $theme)
                        <label class="relative cursor-pointer group">
                            <input type="radio" wire:model.live="activeTheme" value="{{ $theme['slug'] }}" class="sr-only peer">
                            <div class="dash-card p-4 transition-all peer-checked:shadow-md"
                                 style="border-width: 2px; border-color: {{ $activeTheme === $theme['slug'] ? 'var(--portal-primary, #3b82f6)' : 'var(--dash-border)' }};">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                                         style="background-color: var(--portal-primary, #3b82f6);">
                                        {{ strtoupper(substr($theme['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">{{ $theme['name'] }}</p>
                                        <p class="text-xs" style="color: var(--dash-text-muted);">v{{ $theme['version'] }}</p>
                                    </div>
                                </div>
                                <p class="text-xs line-clamp-2" style="color: var(--dash-text-secondary);">{{ $theme['description'] }}</p>
                                @if($theme['author'])
                                    <p class="text-xs mt-2" style="color: var(--dash-text-muted);">von {{ $theme['author'] }}</p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Branding Colors --}}
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Farben</h3>
                    <p class="dash-input-hint">Primär-, Sekundär- und Akzentfarbe Ihres Portals</p>
                </div>

                <div class="dash-form-grid dash-form-grid-3">
                    <div>
                        <label class="dash-label">Primärfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="primaryColor" class="w-10 h-10 rounded-lg cursor-pointer p-0.5" style="border: 1px solid var(--dash-border);">
                            <input type="text" wire:model.live="primaryColor" class="dash-input flex-1 font-mono" placeholder="#3B82F6">
                        </div>
                        @error('primaryColor') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">Sekundärfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="secondaryColor" class="w-10 h-10 rounded-lg cursor-pointer p-0.5" style="border: 1px solid var(--dash-border);">
                            <input type="text" wire:model.live="secondaryColor" class="dash-input flex-1 font-mono" placeholder="#1E40AF">
                        </div>
                        @error('secondaryColor') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">Akzentfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="accentColor" class="w-10 h-10 rounded-lg cursor-pointer p-0.5" style="border: 1px solid var(--dash-border);">
                            <input type="text" wire:model.live="accentColor" class="dash-input flex-1 font-mono" placeholder="#F59E0B">
                        </div>
                        @error('accentColor') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Color Preview --}}
                <div class="mt-4 p-4 rounded-lg" style="border: 1px solid var(--dash-border); background-color: var(--dash-bg-secondary);">
                    <p class="text-xs font-medium mb-2" style="color: var(--dash-text-muted);">Vorschau</p>
                    <div class="flex items-center gap-3">
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $primaryColor }};"></div>
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $secondaryColor }};"></div>
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $accentColor }};"></div>
                    </div>
                </div>
            </div>

            {{-- Typography --}}
            <div class="dash-card dash-card-padded space-y-4">
                <div>
                    <h3 class="dash-form-section-title">Typografie & Form</h3>
                    <p class="dash-input-hint">Schriftart und Rundungen</p>
                </div>

                <div class="dash-form-grid dash-form-grid-2">
                    <div>
                        <label class="dash-label">Schriftart</label>
                        <select wire:model="fontFamily" class="dash-select">
                            <option value="Inter">Inter (Modern, klar)</option>
                            <option value="Poppins">Poppins (Freundlich)</option>
                            <option value="Open Sans">Open Sans (Klassisch)</option>
                            <option value="Roboto">Roboto (Google-Stil)</option>
                            <option value="Nunito">Nunito (Weich, rund)</option>
                            <option value="Lato">Lato (Sauber, neutral)</option>
                        </select>
                    </div>
                    <div>
                        <label class="dash-label">Eckenradius</label>
                        <select wire:model="borderRadius" class="dash-select">
                            <option value="0rem">Keine (0)</option>
                            <option value="0.25rem">Leicht (0.25rem)</option>
                            <option value="0.5rem">Standard (0.5rem)</option>
                            <option value="0.75rem">Stark (0.75rem)</option>
                            <option value="1rem">Rund (1rem)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- File Uploads --}}
            <div class="dash-card dash-card-padded space-y-6">
                <div>
                    <h3 class="dash-form-section-title">Logo & Bilder</h3>
                    <p class="dash-input-hint">Portal-Logo, Favicon und OG-Image</p>
                </div>

                <div class="dash-form-grid dash-form-grid-3">
                    {{-- Logo --}}
                    <div>
                        <label class="dash-label">Logo</label>
                        @if($currentLogoUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentLogoUrl }}" alt="Logo" class="h-16 w-auto rounded-lg" style="border: 1px solid var(--dash-border);">
                                <button type="button" wire:click="deleteLogo" wire:confirm="Logo wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="logo" accept="image/*"
                               class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium transition-colors"
                               style="color: var(--dash-text-secondary); file:background-color: var(--dash-bg-secondary); file:color: var(--dash-text-primary);">
                        <p class="dash-input-hint">PNG, JPG, SVG, WebP. Max 2 MB.</p>
                        @error('logo') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- Favicon --}}
                    <div>
                        <label class="dash-label">Favicon</label>
                        @if($currentFaviconUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentFaviconUrl }}" alt="Favicon" class="h-8 w-8 rounded" style="border: 1px solid var(--dash-border);">
                                <button type="button" wire:click="deleteFavicon" wire:confirm="Favicon wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="favicon" accept=".png,.ico,.svg"
                               class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium transition-colors"
                               style="color: var(--dash-text-secondary); file:background-color: var(--dash-bg-secondary); file:color: var(--dash-text-primary);">
                        <p class="dash-input-hint">PNG, ICO, SVG. Max 512 KB.</p>
                        @error('favicon') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- OG Image --}}
                    <div>
                        <label class="dash-label">OG-Image (Social Sharing)</label>
                        @if($currentOgImageUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentOgImageUrl }}" alt="OG Image" class="h-16 w-auto rounded-lg" style="border: 1px solid var(--dash-border);">
                                <button type="button" wire:click="deleteOgImage" wire:confirm="OG-Image wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="ogImage" accept="image/*"
                               class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium transition-colors"
                               style="color: var(--dash-text-secondary); file:background-color: var(--dash-bg-secondary); file:color: var(--dash-text-primary);">
                        <p class="dash-input-hint">1200x630px empfohlen. Max 2 MB.</p>
                        @error('ogImage') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
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
