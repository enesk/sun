<div>
    <form wire:submit="save">
        <div class="space-y-6">
            {{-- Theme Selection --}}
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Theme-Auswahl</h3>
                    <p class="text-sm text-base-content/60 mt-1">Wählen Sie das Design für Ihr Portal</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($availableThemes as $theme)
                        <label class="relative cursor-pointer group">
                            <input type="radio" wire:model.live="activeTheme" value="{{ $theme['slug'] }}" class="sr-only peer">
                            <div class="p-4 rounded-lg border-2 transition-all peer-checked:shadow-md"
                                 style="border-color: {{ $activeTheme === $theme['slug'] ? 'var(--portal-primary, #3b82f6)' : 'rgb(229 231 235)' }};">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                                         style="background-color: var(--portal-primary, #3b82f6);">
                                        {{ strtoupper(substr($theme['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-base-content">{{ $theme['name'] }}</p>
                                        <p class="text-xs text-base-content/50">v{{ $theme['version'] }}</p>
                                    </div>
                                </div>
                                <p class="text-xs text-base-content/60 line-clamp-2">{{ $theme['description'] }}</p>
                                @if($theme['author'])
                                    <p class="text-xs text-base-content/40 mt-2">von {{ $theme['author'] }}</p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Branding Colors --}}
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Farben</h3>
                    <p class="text-sm text-base-content/60 mt-1">Primär-, Sekundär- und Akzentfarbe Ihres Portals</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Primärfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="primaryColor" class="w-10 h-10 rounded-lg border border-base-300 cursor-pointer p-0.5">
                            <input type="text" wire:model.live="primaryColor" class="flex-1 px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm font-mono focus:outline-none focus:ring-2 transition-shadow" placeholder="#3B82F6">
                        </div>
                        @error('primaryColor') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Sekundärfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="secondaryColor" class="w-10 h-10 rounded-lg border border-base-300 cursor-pointer p-0.5">
                            <input type="text" wire:model.live="secondaryColor" class="flex-1 px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm font-mono focus:outline-none focus:ring-2 transition-shadow" placeholder="#1E40AF">
                        </div>
                        @error('secondaryColor') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Akzentfarbe</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="accentColor" class="w-10 h-10 rounded-lg border border-base-300 cursor-pointer p-0.5">
                            <input type="text" wire:model.live="accentColor" class="flex-1 px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm font-mono focus:outline-none focus:ring-2 transition-shadow" placeholder="#F59E0B">
                        </div>
                        @error('accentColor') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Color Preview --}}
                <div class="mt-4 p-4 rounded-lg border border-base-200 bg-base-50">
                    <p class="text-xs font-medium text-base-content/60 mb-2">Vorschau</p>
                    <div class="flex items-center gap-3">
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $primaryColor }};"></div>
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $secondaryColor }};"></div>
                        <div class="h-8 flex-1 rounded" style="background-color: {{ $accentColor }};"></div>
                    </div>
                </div>
            </div>

            {{-- Typography --}}
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Typografie & Form</h3>
                    <p class="text-sm text-base-content/60 mt-1">Schriftart und Rundungen</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Schriftart</label>
                        <select wire:model="fontFamily" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow">
                            <option value="Inter">Inter (Modern, klar)</option>
                            <option value="Poppins">Poppins (Freundlich)</option>
                            <option value="Open Sans">Open Sans (Klassisch)</option>
                            <option value="Roboto">Roboto (Google-Stil)</option>
                            <option value="Nunito">Nunito (Weich, rund)</option>
                            <option value="Lato">Lato (Sauber, neutral)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1.5">Eckenradius</label>
                        <select wire:model="borderRadius" class="w-full px-3 py-2.5 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 transition-shadow">
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
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Logo & Bilder</h3>
                    <p class="text-sm text-base-content/60 mt-1">Portal-Logo, Favicon und OG-Image</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {{-- Logo --}}
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Logo</label>
                        @if($currentLogoUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentLogoUrl }}" alt="Logo" class="h-16 w-auto rounded-lg border border-base-200">
                                <button type="button" wire:click="deleteLogo" wire:confirm="Logo wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="logo" accept="image/*" class="w-full text-sm text-base-content/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-100 file:text-base-content hover:file:bg-base-200 transition-colors">
                        <p class="text-xs text-base-content/50 mt-1">PNG, JPG, SVG, WebP. Max 2 MB.</p>
                        @error('logo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Favicon --}}
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Favicon</label>
                        @if($currentFaviconUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentFaviconUrl }}" alt="Favicon" class="h-8 w-8 rounded border border-base-200">
                                <button type="button" wire:click="deleteFavicon" wire:confirm="Favicon wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="favicon" accept=".png,.ico,.svg" class="w-full text-sm text-base-content/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-100 file:text-base-content hover:file:bg-base-200 transition-colors">
                        <p class="text-xs text-base-content/50 mt-1">PNG, ICO, SVG. Max 512 KB.</p>
                        @error('favicon') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- OG Image --}}
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">OG-Image (Social Sharing)</label>
                        @if($currentOgImageUrl)
                            <div class="relative mb-2 inline-block">
                                <img src="{{ $currentOgImageUrl }}" alt="OG Image" class="h-16 w-auto rounded-lg border border-base-200">
                                <button type="button" wire:click="deleteOgImage" wire:confirm="OG-Image wirklich löschen?"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <input type="file" wire:model="ogImage" accept="image/*" class="w-full text-sm text-base-content/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-100 file:text-base-content hover:file:bg-base-200 transition-colors">
                        <p class="text-xs text-base-content/50 mt-1">1200x630px empfohlen. Max 2 MB.</p>
                        @error('ogImage') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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
            {{-- Save button — overlay technique --}}
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
