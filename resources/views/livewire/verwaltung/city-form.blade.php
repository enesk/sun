<div>
    <form wire:submit="save" class="space-y-6">
        {{-- Grunddaten --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Grunddaten</h2>

            <div class="dash-form-grid dash-form-grid-2">
                {{-- Name --}}
                <div>
                    <label for="name" class="dash-label dash-label-required">Stadtname</label>
                    <input type="text"
                           id="name"
                           wire:model.live.debounce.300ms="name"
                           class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }}"
                           placeholder="z.B. Berlin">
                    @error('name')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="dash-label dash-label-required">Slug</label>
                    <input type="text"
                           id="slug"
                           wire:model.blur="slug"
                           class="dash-input {{ $errors->has('slug') ? 'dash-input-error' : '' }}"
                           placeholder="berlin">
                    @error('slug')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                    <p class="dash-input-hint">URL-Pfad: /staedte/{{ $slug ?: '...' }}</p>
                </div>
            </div>

            {{-- PLZ --}}
            <div class="mt-4">
                <label for="zipcode" class="dash-label">Postleitzahl</label>
                <input type="text"
                       id="zipcode"
                       wire:model="zipcode"
                       maxlength="10"
                       class="dash-input"
                       style="max-width: 16rem;"
                       placeholder="z.B. 10115">
                @error('zipcode')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Verwaltung --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Verwaltungseinheit</h2>

            <div class="dash-form-grid dash-form-grid-2">
                {{-- Bundesland --}}
                <div>
                    <label for="administrative_area_level_1" class="dash-label">Bundesland</label>
                    <input type="text"
                           id="administrative_area_level_1"
                           wire:model="administrative_area_level_1"
                           class="dash-input"
                           placeholder="z.B. Berlin, Bayern, Nordrhein-Westfalen">
                    @error('administrative_area_level_1')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Gemeinde --}}
                <div>
                    <label for="community" class="dash-label">Gemeinde / Kreis</label>
                    <input type="text"
                           id="community"
                           wire:model="community"
                           class="dash-input"
                           placeholder="z.B. Mitte, Kreisfreie Stadt">
                    @error('community')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Geodaten --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Geodaten</h2>
            <p class="dash-input-hint mb-4">Koordinaten werden für die Kartenanzeige und Entfernungsberechnung verwendet.</p>

            <div class="dash-form-grid dash-form-grid-2">
                {{-- Latitude --}}
                <div>
                    <label for="latitude" class="dash-label">Breitengrad</label>
                    <input type="number"
                           id="latitude"
                           wire:model="latitude"
                           step="0.000001"
                           min="-90"
                           max="90"
                           class="dash-input"
                           placeholder="z.B. 52.520008">
                    @error('latitude')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Longitude --}}
                <div>
                    <label for="longitude" class="dash-label">Längengrad</label>
                    <input type="number"
                           id="longitude"
                           wire:model="longitude"
                           step="0.000001"
                           min="-180"
                           max="180"
                           class="dash-input"
                           placeholder="z.B. 13.404954">
                    @error('longitude')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if($latitude && $longitude)
                <div class="dash-flash dash-flash-success mt-3" role="status" style="border-radius: 0.5rem;">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Koordinaten: {{ $latitude }}, {{ $longitude }}
                </div>
            @endif
        </div>

        {{-- Introtext / SEO --}}
        @if($cityId)
        <div class="dash-card dash-card-padded">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="dash-form-section-title" style="margin-bottom: 0;">Introtext & SEO</h2>
                    @if($is_generated && $generated_at)
                        <p class="dash-input-hint mt-1">
                            <svg class="w-3.5 h-3.5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                            </svg>
                            KI-generiert am {{ $generated_at }}
                        </p>
                    @endif
                </div>

                <button type="button"
                        wire:click="generateContent"
                        wire:loading.attr="disabled"
                        wire:target="generateContent"
                        class="dash-btn dash-btn-secondary"
                        style="font-size: 0.8125rem;">
                    <span wire:loading.remove wire:target="generateContent">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                        {{ $intro_text ? 'Neu generieren' : 'KI-Text generieren' }}
                    </span>
                    <span wire:loading wire:target="generateContent" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Generiere...
                    </span>
                </button>
            </div>

            {{-- Introtext --}}
            <div>
                <label for="intro_text" class="dash-label">Introtext (HTML)</label>
                <textarea id="intro_text"
                          wire:model="intro_text"
                          rows="8"
                          class="dash-input {{ $errors->has('intro_text') ? 'dash-input-error' : '' }}"
                          placeholder="Informativer Text über die Stadt und ihre lokalen Unternehmen... (HTML mit <p>-Tags)"></textarea>
                @error('intro_text')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
                <p class="dash-input-hint">Wird auf der öffentlichen Städteseite angezeigt. HTML erlaubt (&lt;p&gt;, &lt;h3&gt;, &lt;strong&gt;).</p>
            </div>

            {{-- Meta Title --}}
            <div class="mt-4">
                <label for="meta_title" class="dash-label">Meta-Title</label>
                <input type="text"
                       id="meta_title"
                       wire:model="meta_title"
                       class="dash-input {{ $errors->has('meta_title') ? 'dash-input-error' : '' }}"
                       placeholder="z.B. Unternehmen in Berlin — Firmenfreund">
                @error('meta_title')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Meta Description --}}
            <div class="mt-4">
                <label for="meta_description" class="dash-label">Meta-Beschreibung</label>
                <textarea id="meta_description"
                          wire:model="meta_description"
                          rows="2"
                          maxlength="500"
                          class="dash-input {{ $errors->has('meta_description') ? 'dash-input-error' : '' }}"
                          placeholder="SEO-Beschreibung für Suchmaschinen (max. 500 Zeichen)"></textarea>
                @error('meta_description')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
                <p class="dash-input-hint">{{ strlen($meta_description) }}/500 Zeichen</p>
            </div>
        </div>
        @else
        <div class="dash-card dash-card-padded">
            <div class="dash-flash dash-flash-info" role="status" style="border-radius: 0.5rem;">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                </svg>
                Introtext und SEO-Felder können nach dem Erstellen der Stadt bearbeitet werden.
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('verwaltung.cities.index') }}"
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
                    {{ $cityId ? 'Änderungen speichern' : 'Stadt erstellen' }}
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
