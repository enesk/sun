<div>
    <form wire:submit="save" class="space-y-6">
        {{-- Grunddaten --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Grunddaten</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-base-content/70 mb-1">
                        Stadtname <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           wire:model.live.debounce.300ms="name"
                           class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('name') ? 'border-red-300' : 'border-base-200' }}"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. Berlin">
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-base-content/70 mb-1">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="slug"
                           wire:model.blur="slug"
                           class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent {{ $errors->has('slug') ? 'border-red-300' : 'border-base-200' }}"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="berlin">
                    @error('slug')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-base-content/40">URL-Pfad: /staedte/{{ $slug ?: '...' }}</p>
                </div>
            </div>

            {{-- PLZ --}}
            <div class="mt-4">
                <label for="zipcode" class="block text-sm font-medium text-base-content/70 mb-1">Postleitzahl</label>
                <input type="text"
                       id="zipcode"
                       wire:model="zipcode"
                       maxlength="10"
                       class="w-full max-w-xs px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                       style="focus:ring-color: var(--portal-primary, #3b82f6);"
                       placeholder="z.B. 10115">
                @error('zipcode')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Verwaltung --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Verwaltungseinheit</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Bundesland --}}
                <div>
                    <label for="administrative_area_level_1" class="block text-sm font-medium text-base-content/70 mb-1">Bundesland</label>
                    <input type="text"
                           id="administrative_area_level_1"
                           wire:model="administrative_area_level_1"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. Berlin, Bayern, Nordrhein-Westfalen">
                    @error('administrative_area_level_1')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Gemeinde --}}
                <div>
                    <label for="community" class="block text-sm font-medium text-base-content/70 mb-1">Gemeinde / Kreis</label>
                    <input type="text"
                           id="community"
                           wire:model="community"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. Mitte, Kreisfreie Stadt">
                    @error('community')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Geodaten --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Geodaten</h2>
            <p class="text-xs text-base-content/50 mb-4">Koordinaten werden für die Kartenanzeige und Entfernungsberechnung verwendet.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Latitude --}}
                <div>
                    <label for="latitude" class="block text-sm font-medium text-base-content/70 mb-1">Breitengrad</label>
                    <input type="number"
                           id="latitude"
                           wire:model="latitude"
                           step="0.000001"
                           min="-90"
                           max="90"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. 52.520008">
                    @error('latitude')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Longitude --}}
                <div>
                    <label for="longitude" class="block text-sm font-medium text-base-content/70 mb-1">Längengrad</label>
                    <input type="number"
                           id="longitude"
                           wire:model="longitude"
                           step="0.000001"
                           min="-180"
                           max="180"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-2 focus:border-transparent"
                           style="focus:ring-color: var(--portal-primary, #3b82f6);"
                           placeholder="z.B. 13.404954">
                    @error('longitude')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if($latitude && $longitude)
                <div class="mt-3 p-2 rounded-lg bg-green-50 border border-green-200 text-xs text-green-700 flex items-center gap-2">
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
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-base-content/70 hover:text-base-content transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Abbrechen
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="relative inline-flex items-center justify-center px-6 py-2.5 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 disabled:opacity-50 overflow-hidden"
                    style="background-color: var(--portal-primary, #3b82f6);">
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
