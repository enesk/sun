<div>
    <form wire:submit="save">
        <div class="space-y-6">
            {{-- Impressum --}}
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Impressum</h3>
                    <p class="text-sm text-base-content/60 mt-1">Pflichtangaben gemäß § 5 TMG. Wird auf der Impressum-Seite und im Footer angezeigt.</p>
                </div>

                <div x-data="{ focused: false }">
                    <div class="rounded-lg border transition-all"
                         :class="focused ? 'border-blue-400 ring-2 ring-blue-100' : 'border-base-300'">
                        {{-- Toolbar --}}
                        <div class="flex items-center gap-1 p-2 border-b border-base-200 bg-base-50 rounded-t-lg">
                            <button type="button" @click="document.execCommand('bold')" class="p-1.5 rounded hover:bg-base-200 text-base-content/60 hover:text-base-content transition-colors" title="Fett">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/></svg>
                            </button>
                            <button type="button" @click="document.execCommand('italic')" class="p-1.5 rounded hover:bg-base-200 text-base-content/60 hover:text-base-content transition-colors" title="Kursiv">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0l-4 16m0 0h4"/></svg>
                            </button>
                            <div class="w-px h-5 bg-base-300 mx-1"></div>
                            <button type="button" @click="document.execCommand('formatBlock', false, 'h2')" class="p-1.5 rounded hover:bg-base-200 text-base-content/60 hover:text-base-content transition-colors text-xs font-bold" title="Überschrift 2">
                                H2
                            </button>
                            <button type="button" @click="document.execCommand('formatBlock', false, 'h3')" class="p-1.5 rounded hover:bg-base-200 text-base-content/60 hover:text-base-content transition-colors text-xs font-bold" title="Überschrift 3">
                                H3
                            </button>
                            <div class="w-px h-5 bg-base-300 mx-1"></div>
                            <button type="button" @click="document.execCommand('insertUnorderedList')" class="p-1.5 rounded hover:bg-base-200 text-base-content/60 hover:text-base-content transition-colors" title="Aufzählung">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                            </button>
                        </div>
                        {{-- Editor --}}
                        <textarea wire:model="impressum" rows="12"
                                  @focus="focused = true" @blur="focused = false"
                                  class="w-full px-4 py-3 text-sm bg-white focus:outline-none resize-y rounded-b-lg"
                                  placeholder="Angaben gemäß § 5 TMG:&#10;&#10;Firmenname GmbH&#10;Musterstraße 1&#10;10115 Berlin&#10;&#10;Vertreten durch:&#10;Max Mustermann&#10;&#10;Kontakt:&#10;Telefon: +49 (0) 30 1234567&#10;E-Mail: info@example.de"></textarea>
                    </div>
                    <p class="text-xs text-base-content/50 mt-1">HTML-Formatierung (fett, kursiv, Überschriften, Listen, Links) wird unterstützt.</p>
                    @error('impressum') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Datenschutz --}}
            <div class="bg-white rounded-xl border border-base-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Datenschutzerklärung</h3>
                    <p class="text-sm text-base-content/60 mt-1">Datenschutzerklärung gemäß DSGVO. Wird auf der Datenschutz-Seite und im Footer angezeigt.</p>
                </div>

                <div>
                    <textarea wire:model="datenschutz" rows="16"
                              class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition-all resize-y"
                              placeholder="Datenschutzerklärung&#10;&#10;1. Datenschutz auf einen Blick&#10;Allgemeine Hinweise...&#10;&#10;2. Hosting und Content Delivery Networks (CDN)&#10;..."></textarea>
                    <p class="text-xs text-base-content/50 mt-1">HTML-Formatierung wird unterstützt. Tipp: Nutzen Sie einen DSGVO-Generator für eine rechtssichere Vorlage.</p>
                    @error('datenschutz') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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
