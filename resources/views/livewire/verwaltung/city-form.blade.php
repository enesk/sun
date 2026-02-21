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
