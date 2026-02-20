<div>
    <form wire:submit="save" class="space-y-6">

        {{-- ================================================================ --}}
        {{-- SEKTION 1: Firmendaten                                          --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Firmendaten</h2>

            <div class="space-y-4">
                {{-- Firmenname --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Firmenname <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           wire:model.blur="name"
                           placeholder="z.B. Malerbetrieb Müller GmbH"
                           class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent transition-colors
                                  {{ $errors->has('name') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}">
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Beschreibung --}}
                <div>
                    <label for="description" class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Beschreibung
                    </label>
                    <textarea id="description"
                              wire:model.blur="description"
                              rows="4"
                              placeholder="Beschreiben Sie Ihr Unternehmen, Ihre Leistungen und was Sie auszeichnet..."
                              class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent transition-colors resize-y
                                     {{ $errors->has('description') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}"></textarea>
                    <p class="mt-1 text-xs text-base-content/40">
                        {{ strlen($description) }}/5000 Zeichen
                    </p>
                    @error('description')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kategorien --}}
                <div>
                    <label class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Kategorien <span class="text-red-500">*</span>
                        <span class="font-normal text-base-content/40">({{ count($selectedCategories) }}/5)</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            @php
                                $isSelected = in_array($category->id, $selectedCategories);
                                $isDisabled = !$isSelected && count($selectedCategories) >= 5;
                            @endphp
                            <button type="button"
                                    wire:click="toggleCategory({{ $category->id }})"
                                    @if($isDisabled) disabled @endif
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium transition-all border
                                           {{ $isSelected
                                               ? 'text-white border-transparent shadow-sm'
                                               : ($isDisabled
                                                   ? 'bg-base-100 text-base-content/30 border-base-200 cursor-not-allowed'
                                                   : 'bg-base-100 text-base-content/70 border-base-200 hover:border-base-300 hover:bg-base-200/50') }}"
                                    @if($isSelected) style="background-color: var(--portal-primary, #3b82f6);" @endif>
                                @if($isSelected)
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                @endif
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                    @error('selectedCategories')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 2: Adresse                                              --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Adresse</h2>

            <div class="space-y-4">
                {{-- Straße + Hausnummer --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label for="street" class="block text-sm font-medium text-base-content/80 mb-1.5">
                            Straße <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="street"
                               wire:model.blur="street"
                               placeholder="Musterstraße"
                               class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent
                                      {{ $errors->has('street') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}">
                        @error('street')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="house_no" class="block text-sm font-medium text-base-content/80 mb-1.5">
                            Nr.
                        </label>
                        <input type="text"
                               id="house_no"
                               wire:model.blur="house_no"
                               placeholder="12a"
                               class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent
                                      {{ $errors->has('house_no') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}">
                        @error('house_no')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- PLZ + Stadt --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="zipcode" class="block text-sm font-medium text-base-content/80 mb-1.5">
                            PLZ <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="zipcode"
                               wire:model.blur="zipcode"
                               placeholder="10115"
                               maxlength="5"
                               class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent
                                      {{ $errors->has('zipcode') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}">
                        @error('zipcode')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-span-2" x-data="{ open: false }" @click.outside="open = false">
                        <label for="citySearch" class="block text-sm font-medium text-base-content/80 mb-1.5">
                            Stadt <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   id="citySearch"
                                   wire:model.live.debounce.300ms="citySearch"
                                   @focus="open = true"
                                   @input="open = true"
                                   placeholder="{{ $currentCity ? $currentCity->name : 'Stadt suchen...' }}"
                                   class="w-full px-3 py-2.5 text-sm border rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent
                                          {{ $errors->has('city_id') ? 'border-red-300 focus:ring-red-500' : 'border-base-200 focus:ring-[var(--portal-primary,#3b82f6)]' }}">

                            {{-- Aktuelle Stadt anzeigen --}}
                            @if($currentCity && !$citySearch)
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                    <span class="text-sm text-base-content">{{ $currentCity->name }}</span>
                                </div>
                            @endif

                            {{-- Dropdown --}}
                            @if(count($cityResults) > 0)
                                <div x-show="open"
                                     x-transition
                                     class="absolute z-20 mt-1 w-full bg-base-100 border border-base-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    @foreach($cityResults as $id => $name)
                                        <button type="button"
                                                wire:click="$set('city_id', {{ $id }}); $set('citySearch', '')"
                                                @click="open = false"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-base-200 transition-colors
                                                       {{ $city_id === $id ? 'font-medium' : 'text-base-content/70' }}"
                                                style="{{ $city_id === $id ? 'color: var(--portal-primary, #3b82f6);' : '' }}">
                                            {{ $name }}
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen($citySearch) >= 2)
                                <div x-show="open"
                                     class="absolute z-20 mt-1 w-full bg-base-100 border border-base-200 rounded-lg shadow-lg p-3 text-sm text-base-content/50 text-center">
                                    Keine Stadt gefunden
                                </div>
                            @endif
                        </div>
                        @error('city_id')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 3: Kontakt                                              --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Kontakt</h2>

            <div class="space-y-4">
                {{-- Telefon --}}
                <div>
                    <label for="tel" class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Telefon
                    </label>
                    <input type="tel"
                           id="tel"
                           wire:model.blur="tel"
                           placeholder="030 1234567"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:ring-[var(--portal-primary,#3b82f6)] focus:border-transparent">
                    @error('tel')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- E-Mail --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-base-content/80 mb-1.5">
                        E-Mail
                    </label>
                    <input type="email"
                           id="email"
                           wire:model.blur="email"
                           placeholder="info@firma.de"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:ring-[var(--portal-primary,#3b82f6)] focus:border-transparent">
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Website --}}
                <div>
                    <label for="website" class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Website
                    </label>
                    <input type="url"
                           id="website"
                           wire:model.blur="website"
                           placeholder="https://www.firma.de"
                           class="w-full px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:ring-[var(--portal-primary,#3b82f6)] focus:border-transparent">
                    @error('website')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 4: Öffnungszeiten                                       --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Öffnungszeiten</h2>

            <div class="space-y-2">
                @php
                    $dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
                @endphp

                @foreach($dayNames as $index => $dayName)
                    <div class="flex items-center gap-3 py-2 {{ $index < 6 ? 'border-b border-base-200/50' : '' }}">
                        {{-- Tag-Name --}}
                        <span class="w-24 sm:w-28 text-sm font-medium text-base-content/80 shrink-0">
                            {{ $dayName }}
                        </span>

                        {{-- Geschlossen-Toggle --}}
                        <label class="flex items-center gap-2 shrink-0 cursor-pointer">
                            <input type="checkbox"
                                   wire:model.live="openingHours.{{ $index }}.is_closed"
                                   class="rounded border-base-300 text-red-500 focus:ring-red-500 w-4 h-4">
                            <span class="text-xs text-base-content/50">Geschlossen</span>
                        </label>

                        {{-- Zeiten --}}
                        @if(!($openingHours[$index]['is_closed'] ?? false))
                            <div class="flex items-center gap-2 ml-auto">
                                <input type="time"
                                       wire:model.blur="openingHours.{{ $index }}.opens_at"
                                       class="px-2 py-1.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-1 focus:ring-[var(--portal-primary,#3b82f6)]">
                                <span class="text-base-content/40 text-sm">–</span>
                                <input type="time"
                                       wire:model.blur="openingHours.{{ $index }}.closes_at"
                                       class="px-2 py-1.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content focus:outline-none focus:ring-1 focus:ring-[var(--portal-primary,#3b82f6)]">
                            </div>
                        @else
                            <span class="ml-auto text-sm text-red-500/70">Geschlossen</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 5: Bilder                                               --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Bilder</h2>

            <div class="space-y-6">
                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Logo
                        <span class="font-normal text-base-content/40">(max. 2 MB, JPG/PNG/WebP)</span>
                    </label>
                    <div class="flex items-start gap-4">
                        {{-- Vorschau --}}
                        @if($isEdit && $company && $company->logo_url && !$logo)
                            <img src="{{ $company->logo_thumb_url ?? $company->logo_url }}"
                                 alt="Aktuelles Logo"
                                 class="w-16 h-16 rounded-lg object-cover border border-base-200 shrink-0">
                        @elseif($logo)
                            <img src="{{ $logo->temporaryUrl() }}"
                                 alt="Neues Logo"
                                 class="w-16 h-16 rounded-lg object-cover border border-base-200 shrink-0">
                        @else
                            <div class="w-16 h-16 rounded-lg border-2 border-dashed border-base-300 flex items-center justify-center text-base-content/30 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 003.75 21z"/>
                                </svg>
                            </div>
                        @endif

                        <div class="flex-1">
                            <input type="file"
                                   wire:model="logo"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="text-sm text-base-content/60 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-200 file:text-base-content/70 hover:file:bg-base-300 file:cursor-pointer file:transition-colors">
                            <div wire:loading wire:target="logo" class="mt-1 text-xs text-base-content/50">Wird hochgeladen...</div>
                        </div>
                    </div>
                    @error('logo')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cover-Bild --}}
                <div>
                    <label class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Cover-Bild
                        <span class="font-normal text-base-content/40">(max. 4 MB, JPG/PNG/WebP, empfohlen 1200x400)</span>
                    </label>
                    @if($isEdit && $company && $company->cover_url && !$cover)
                        <div class="mb-2">
                            <img src="{{ $company->cover_url }}"
                                 alt="Aktuelles Cover"
                                 class="w-full max-w-md h-24 object-cover rounded-lg border border-base-200">
                        </div>
                    @elseif($cover)
                        <div class="mb-2">
                            <img src="{{ $cover->temporaryUrl() }}"
                                 alt="Neues Cover"
                                 class="w-full max-w-md h-24 object-cover rounded-lg border border-base-200">
                        </div>
                    @endif
                    <input type="file"
                           wire:model="cover"
                           accept="image/jpeg,image/png,image/webp"
                           class="text-sm text-base-content/60 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-200 file:text-base-content/70 hover:file:bg-base-300 file:cursor-pointer file:transition-colors">
                    <div wire:loading wire:target="cover" class="mt-1 text-xs text-base-content/50">Wird hochgeladen...</div>
                    @error('cover')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Galerie --}}
                <div>
                    <label class="block text-sm font-medium text-base-content/80 mb-1.5">
                        Galerie
                        <span class="font-normal text-base-content/40">(max. 10 Bilder, je max. 4 MB)</span>
                    </label>

                    {{-- Bestehende Galerie-Bilder --}}
                    @if(count($existingGallery) > 0)
                        <div class="flex flex-wrap gap-3 mb-3">
                            @foreach($existingGallery as $img)
                                <div class="relative group">
                                    <img src="{{ $img['url'] }}"
                                         alt="{{ $img['name'] }}"
                                         class="w-20 h-20 object-cover rounded-lg border border-base-200">
                                    <button type="button"
                                            wire:click="removeGalleryImage({{ $img['id'] }})"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm"
                                            title="Entfernen">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Neue Galerie-Bilder (Vorschau) --}}
                    @if(count($gallery) > 0)
                        <div class="flex flex-wrap gap-3 mb-3">
                            @foreach($gallery as $index => $img)
                                <div class="relative group">
                                    <img src="{{ $img->temporaryUrl() }}"
                                         alt="Neues Bild"
                                         class="w-20 h-20 object-cover rounded-lg border border-base-200 border-dashed">
                                    <button type="button"
                                            wire:click="removeNewGalleryImage({{ $index }})"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm"
                                            title="Entfernen">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <input type="file"
                           wire:model="gallery"
                           accept="image/jpeg,image/png,image/webp"
                           multiple
                           class="text-sm text-base-content/60 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-200 file:text-base-content/70 hover:file:bg-base-300 file:cursor-pointer file:transition-colors">
                    <div wire:loading wire:target="gallery" class="mt-1 text-xs text-base-content/50">Wird hochgeladen...</div>
                    @error('gallery.*')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 6: Status (Admin-only Felder werden ausgeblendet)       --}}
        {{-- ================================================================ --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4">Status</h2>

            <div class="space-y-3">
                {{-- Aktiv --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox"
                           wire:model="is_active"
                           class="rounded border-base-300 w-5 h-5 focus:ring-2 focus:ring-[var(--portal-primary,#3b82f6)]"
                           style="color: var(--portal-primary, #3b82f6);">
                    <div>
                        <span class="text-sm font-medium text-base-content">Aktiv</span>
                        <p class="text-xs text-base-content/50">Firma ist im Portal sichtbar</p>
                    </div>
                </label>

                @if($isAdmin)
                    {{-- Premium --}}
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox"
                               wire:model="is_premium"
                               class="rounded border-base-300 text-amber-500 w-5 h-5 focus:ring-2 focus:ring-amber-500">
                        <div>
                            <span class="text-sm font-medium text-base-content">Premium</span>
                            <p class="text-xs text-base-content/50">Premium-Eintrag mit erweiterten Features</p>
                        </div>
                    </label>

                    {{-- Verifiziert --}}
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox"
                               wire:model="is_verified"
                               class="rounded border-base-300 text-green-500 w-5 h-5 focus:ring-2 focus:ring-green-500">
                        <div>
                            <span class="text-sm font-medium text-base-content">Verifiziert</span>
                            <p class="text-xs text-base-content/50">Firma wurde manuell verifiziert</p>
                        </div>
                    </label>

                    {{-- Owner (Admin) --}}
                    <div class="pt-3 border-t border-base-200">
                        <label for="user_id" class="block text-sm font-medium text-base-content/80 mb-1.5">
                            Inhaber (User-ID)
                            <span class="font-normal text-base-content/40">— nur Admin</span>
                        </label>
                        <input type="number"
                               id="user_id"
                               wire:model.blur="user_id"
                               placeholder="User-ID des Firmeninhabers"
                               class="w-full max-w-xs px-3 py-2.5 text-sm border border-base-200 rounded-lg bg-base-100 text-base-content placeholder-base-content/40 focus:outline-none focus:ring-2 focus:ring-[var(--portal-primary,#3b82f6)] focus:border-transparent">
                    </div>
                @endif
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- AKTIONEN                                                        --}}
        {{-- ================================================================ --}}
        <div class="flex flex-col sm:flex-row items-center gap-3">
            {{-- Speichern — overlay technique: text stays in DOM, spinner floats on top --}}
            <button type="submit"
                    class="relative w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 disabled:opacity-50 overflow-hidden"
                    style="background-color: var(--portal-primary, #3b82f6);"
                    wire:loading.attr="disabled"
                    wire:target="save">
                <span wire:loading.class="opacity-0" wire:target="save" class="transition-opacity duration-200">
                    {{ $isEdit ? 'Änderungen speichern' : 'Firma erstellen' }}
                </span>
                <span wire:loading wire:target="save" class="absolute inset-0 flex items-center justify-center">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>

            {{-- Abbrechen --}}
            <a href="{{ route('verwaltung.companies.index') }}"
               class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-lg text-sm font-medium text-base-content/70 bg-base-200 hover:bg-base-300 transition-colors">
                Abbrechen
            </a>

            {{-- Löschen (nur bei Edit) --}}
            @if($isEdit)
                <div class="sm:ml-auto">
                    <button type="button"
                            wire:click="$set('showDeleteModal', true)"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        Firma löschen
                    </button>
                </div>
            @endif
        </div>
    </form>

    {{-- ================================================================ --}}
    {{-- LÖSCH-BESTÄTIGUNG (Modal)                                       --}}
    {{-- ================================================================ --}}
    @if($isEdit && $showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showDeleteModal', false)"></div>

            {{-- Modal --}}
            <div class="relative bg-base-100 rounded-xl shadow-xl max-w-sm w-full p-6 z-10">
                <h3 id="delete-modal-title" class="text-lg font-semibold text-base-content mb-2">Firma löschen?</h3>
                <p class="text-sm text-base-content/60 mb-6">
                    <strong>{{ $company->name }}</strong> wird unwiderruflich gelöscht — inklusive aller Bilder, Bewertungen und Öffnungszeiten.
                </p>
                <div class="flex items-center gap-3 justify-end">
                    <button type="button"
                            wire:click="$set('showDeleteModal', false)"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-base-content/70 bg-base-200 hover:bg-base-300 transition-colors">
                        Abbrechen
                    </button>
                    <form method="POST" action="{{ route('verwaltung.companies.destroy', $company->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors">
                            Endgültig löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
