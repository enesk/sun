<div>
    <form wire:submit="save" class="space-y-6">

        {{-- ================================================================ --}}
        {{-- SEKTION 1: Firmendaten                                          --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Firmendaten</h2>

            <div class="space-y-4">
                {{-- Firmenname --}}
                <div>
                    <label for="name" class="dash-label dash-label-required">Firmenname</label>
                    <input type="text"
                           id="name"
                           wire:model.blur="name"
                           placeholder="z.B. Malerbetrieb Müller GmbH"
                           class="dash-input {{ $errors->has('name') ? 'dash-input-error' : '' }}">
                    @error('name')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Beschreibung --}}
                <div>
                    <label for="description" class="dash-label">Beschreibung</label>
                    <textarea id="description"
                              wire:model.blur="description"
                              rows="4"
                              placeholder="Beschreiben Sie Ihr Unternehmen, Ihre Leistungen und was Sie auszeichnet..."
                              class="dash-textarea {{ $errors->has('description') ? 'dash-textarea-error' : '' }}"></textarea>
                    <p class="dash-input-hint">{{ strlen($description) }}/5000 Zeichen</p>
                    @error('description')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kategorien --}}
                <div>
                    <label class="dash-label dash-label-required">
                        Kategorien
                        <span class="font-normal" style="color: var(--dash-text-muted);">({{ count($selectedCategories) }}/5)</span>
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
                                    class="dash-badge dash-badge-clickable
                                           {{ $isSelected
                                               ? 'text-white'
                                               : ($isDisabled
                                                   ? 'dash-badge-neutral opacity-40 cursor-not-allowed'
                                                   : 'dash-badge-neutral') }}"
                                    @if($isSelected) style="background-color: var(--portal-primary, #3b82f6); color: white;" @endif>
                                @if($isSelected)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                @endif
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                    @error('selectedCategories')
                        <p class="dash-input-error-msg">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 2: Adresse                                              --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Adresse</h2>

            <div class="space-y-4">
                {{-- Straße + Hausnummer --}}
                <div class="dash-form-grid dash-form-grid-3">
                    <div class="col-span-2">
                        <label for="street" class="dash-label dash-label-required">Straße</label>
                        <input type="text"
                               id="street"
                               wire:model.blur="street"
                               placeholder="Musterstraße"
                               class="dash-input {{ $errors->has('street') ? 'dash-input-error' : '' }}">
                        @error('street')
                            <p class="dash-input-error-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="house_no" class="dash-label">Nr.</label>
                        <input type="text"
                               id="house_no"
                               wire:model.blur="house_no"
                               placeholder="12a"
                               class="dash-input {{ $errors->has('house_no') ? 'dash-input-error' : '' }}">
                        @error('house_no')
                            <p class="dash-input-error-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- PLZ + Stadt --}}
                <div class="dash-form-grid dash-form-grid-3">
                    <div>
                        <label for="zipcode" class="dash-label dash-label-required">PLZ</label>
                        <input type="text"
                               id="zipcode"
                               wire:model.blur="zipcode"
                               placeholder="10115"
                               maxlength="5"
                               class="dash-input {{ $errors->has('zipcode') ? 'dash-input-error' : '' }}">
                        @error('zipcode')
                            <p class="dash-input-error-msg">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-span-2" x-data="{ open: false }" @click.outside="open = false">
                        <label for="citySearch" class="dash-label dash-label-required">Stadt</label>
                        <div class="relative">
                            <input type="text"
                                   id="citySearch"
                                   wire:model.live.debounce.300ms="citySearch"
                                   @focus="open = true"
                                   @input="open = true"
                                   placeholder="{{ $currentCity ? $currentCity->name : 'Stadt suchen...' }}"
                                   class="dash-input {{ $errors->has('city_id') ? 'dash-input-error' : '' }}">

                            {{-- Aktuelle Stadt anzeigen --}}
                            @if($currentCity && !$citySearch)
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                    <span class="text-sm" style="color: var(--dash-text-primary);">{{ $currentCity->name }}</span>
                                </div>
                            @endif

                            {{-- Dropdown --}}
                            @if(count($cityResults) > 0)
                                <div x-show="open"
                                     x-transition
                                     class="dash-dropdown mt-1 w-full" style="position: absolute; min-width: 100%;">
                                    @foreach($cityResults as $id => $name)
                                        <button type="button"
                                                wire:click="$set('city_id', {{ $id }}); $set('citySearch', '')"
                                                @click="open = false"
                                                class="dash-dropdown-item {{ $city_id === $id ? 'dash-dropdown-item-active' : '' }}">
                                            {{ $name }}
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen($citySearch) >= 2)
                                <div x-show="open"
                                     class="dash-dropdown mt-1 w-full p-3 text-center" style="position: absolute; min-width: 100%;">
                                    <span class="text-sm" style="color: var(--dash-text-muted);">Keine Stadt gefunden</span>
                                </div>
                            @endif
                        </div>
                        @error('city_id')
                            <p class="dash-input-error-msg">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 3: Kontakt                                              --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Kontakt</h2>

            <div class="space-y-4">
                <div>
                    <label for="tel" class="dash-label">Telefon</label>
                    <input type="tel" id="tel" wire:model.blur="tel" placeholder="030 1234567"
                           class="dash-input {{ $errors->has('tel') ? 'dash-input-error' : '' }}">
                    @error('tel') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="dash-label">E-Mail</label>
                    <input type="email" id="email" wire:model.blur="email" placeholder="info@firma.de"
                           class="dash-input {{ $errors->has('email') ? 'dash-input-error' : '' }}">
                    @error('email') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="website" class="dash-label">Website</label>
                    <input type="url" id="website" wire:model.blur="website" placeholder="https://www.firma.de"
                           class="dash-input {{ $errors->has('website') ? 'dash-input-error' : '' }}">
                    @error('website') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 4: Öffnungszeiten                                       --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Öffnungszeiten</h2>

            <div class="space-y-2">
                @php
                    $dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
                @endphp

                @foreach($dayNames as $index => $dayName)
                    <div class="flex items-center gap-3 py-2 {{ $index < 6 ? 'border-b' : '' }}"
                         style="{{ $index < 6 ? 'border-color: var(--dash-border);' : '' }}">
                        <span class="w-24 sm:w-28 text-sm font-medium shrink-0" style="color: var(--dash-text-secondary);">
                            {{ $dayName }}
                        </span>

                        <label class="dash-checkbox shrink-0">
                            <input type="checkbox"
                                   wire:model.live="openingHours.{{ $index }}.is_closed"
                                   style="accent-color: var(--dash-danger, #dc2626);">
                            <span class="text-xs" style="color: var(--dash-text-muted);">Geschlossen</span>
                        </label>

                        @if(!($openingHours[$index]['is_closed'] ?? false))
                            <div class="flex items-center gap-2 ml-auto">
                                <input type="time"
                                       wire:model.blur="openingHours.{{ $index }}.opens_at"
                                       class="dash-input" style="width: auto; padding: 0.375rem 0.5rem;">
                                <span style="color: var(--dash-text-muted);">–</span>
                                <input type="time"
                                       wire:model.blur="openingHours.{{ $index }}.closes_at"
                                       class="dash-input" style="width: auto; padding: 0.375rem 0.5rem;">
                            </div>
                        @else
                            <span class="ml-auto text-sm" style="color: var(--dash-danger);">Geschlossen</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 5: Bilder                                               --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Bilder</h2>

            <div class="space-y-6">
                {{-- Logo --}}
                <div>
                    <label class="dash-label">
                        Logo
                        <span class="font-normal" style="color: var(--dash-text-muted);">(max. 2 MB, JPG/PNG/WebP)</span>
                    </label>
                    <div class="flex items-start gap-4">
                        @if($isEdit && $company && $company->logo_url && !$logo)
                            <img src="{{ $company->logo_thumb_url ?? $company->logo_url }}"
                                 alt="Aktuelles Logo"
                                 class="w-16 h-16 rounded-lg object-cover shrink-0"
                                 style="border: 1px solid var(--dash-border);">
                        @elseif($logo)
                            <img src="{{ $logo->temporaryUrl() }}"
                                 alt="Neues Logo"
                                 class="w-16 h-16 rounded-lg object-cover shrink-0"
                                 style="border: 1px solid var(--dash-border);">
                        @else
                            <div class="w-16 h-16 rounded-lg border-2 border-dashed flex items-center justify-center shrink-0"
                                 style="border-color: var(--dash-border-strong); color: var(--dash-text-muted);">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 003.75 21z"/>
                                </svg>
                            </div>
                        @endif

                        <div class="flex-1">
                            <input type="file"
                                   wire:model="logo"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer file:transition-colors"
                                   style="color: var(--dash-text-muted); --tw-file-bg: #f1f5f9; --tw-file-text: var(--dash-text-secondary);">
                            <div wire:loading wire:target="logo" class="dash-input-hint mt-1">Wird hochgeladen...</div>
                        </div>
                    </div>
                    @error('logo') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>

                {{-- Cover-Bild --}}
                <div>
                    <label class="dash-label">
                        Cover-Bild
                        <span class="font-normal" style="color: var(--dash-text-muted);">(max. 4 MB, JPG/PNG/WebP, empfohlen 1200x400)</span>
                    </label>
                    @if($isEdit && $company && $company->cover_url && !$cover)
                        <div class="mb-2">
                            <img src="{{ $company->cover_url }}" alt="Aktuelles Cover"
                                 class="w-full max-w-md h-24 object-cover rounded-lg"
                                 style="border: 1px solid var(--dash-border);">
                        </div>
                    @elseif($cover)
                        <div class="mb-2">
                            <img src="{{ $cover->temporaryUrl() }}" alt="Neues Cover"
                                 class="w-full max-w-md h-24 object-cover rounded-lg"
                                 style="border: 1px solid var(--dash-border);">
                        </div>
                    @endif
                    <input type="file" wire:model="cover" accept="image/jpeg,image/png,image/webp"
                           class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer file:transition-colors"
                           style="color: var(--dash-text-muted);">
                    <div wire:loading wire:target="cover" class="dash-input-hint mt-1">Wird hochgeladen...</div>
                    @error('cover') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>

                {{-- Galerie --}}
                <div>
                    <label class="dash-label">
                        Galerie
                        <span class="font-normal" style="color: var(--dash-text-muted);">(max. 10 Bilder, je max. 4 MB)</span>
                    </label>

                    @if(count($existingGallery) > 0)
                        <div class="flex flex-wrap gap-3 mb-3">
                            @foreach($existingGallery as $img)
                                <div class="relative group">
                                    <img src="{{ $img['url'] }}" alt="{{ $img['name'] }}"
                                         class="w-20 h-20 object-cover rounded-lg"
                                         style="border: 1px solid var(--dash-border);">
                                    <button type="button" wire:click="removeGalleryImage({{ $img['id'] }})"
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

                    @if(count($gallery) > 0)
                        <div class="flex flex-wrap gap-3 mb-3">
                            @foreach($gallery as $index => $img)
                                <div class="relative group">
                                    <img src="{{ $img->temporaryUrl() }}" alt="Neues Bild"
                                         class="w-20 h-20 object-cover rounded-lg border-dashed"
                                         style="border: 2px dashed var(--dash-border-strong);">
                                    <button type="button" wire:click="removeNewGalleryImage({{ $index }})"
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

                    <input type="file" wire:model="gallery" accept="image/jpeg,image/png,image/webp" multiple
                           class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer file:transition-colors"
                           style="color: var(--dash-text-muted);">
                    <div wire:loading wire:target="gallery" class="dash-input-hint mt-1">Wird hochgeladen...</div>
                    @error('gallery.*') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 6: Status                                               --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">Status</h2>

            <div class="space-y-3">
                <label class="dash-checkbox">
                    <input type="checkbox" wire:model="is_active"
                           style="accent-color: var(--portal-primary, #3b82f6);">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--dash-text-primary);">Aktiv</span>
                        <p class="text-xs" style="color: var(--dash-text-muted);">Firma ist im Portal sichtbar</p>
                    </div>
                </label>

                @if($isAdmin)
                    <label class="dash-checkbox">
                        <input type="checkbox" wire:model="is_premium"
                               style="accent-color: var(--dash-warning, #d97706);">
                        <div>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary);">Premium</span>
                            <p class="text-xs" style="color: var(--dash-text-muted);">Premium-Eintrag mit erweiterten Features</p>
                        </div>
                    </label>

                    <label class="dash-checkbox">
                        <input type="checkbox" wire:model="is_verified"
                               style="accent-color: var(--dash-success, #16a34a);">
                        <div>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary);">Verifiziert</span>
                            <p class="text-xs" style="color: var(--dash-text-muted);">Firma wurde manuell verifiziert</p>
                        </div>
                    </label>

                    <div class="dash-form-section">
                        <label for="user_id" class="dash-label">
                            Inhaber (User-ID)
                            <span class="font-normal" style="color: var(--dash-text-muted);">— nur Admin</span>
                        </label>
                        <input type="number" id="user_id" wire:model.blur="user_id"
                               placeholder="User-ID des Firmeninhabers"
                               class="dash-input" style="max-width: 16rem;">
                    </div>
                @endif
            </div>
        </div>

        {{-- ================================================================ --}}
        {{-- AKTIONEN                                                        --}}
        {{-- ================================================================ --}}
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <button type="submit"
                    class="dash-btn dash-btn-primary w-full sm:w-auto relative overflow-hidden"
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

            <a href="{{ route('verwaltung.companies.index') }}"
               class="dash-btn dash-btn-secondary w-full sm:w-auto">
                Abbrechen
            </a>

            @if($isEdit)
                <div class="sm:ml-auto">
                    <button type="button"
                            wire:click="$set('showDeleteModal', true)"
                            class="dash-btn dash-btn-ghost" style="color: var(--dash-danger);">
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
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="dash-modal-backdrop" wire:click="$set('showDeleteModal', false)"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <h3 id="delete-modal-title" class="dash-modal-title">Firma löschen?</h3>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        <strong>{{ $company->name }}</strong> wird unwiderruflich gelöscht — inklusive aller Bilder, Bewertungen und Öffnungszeiten.
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button type="button"
                            wire:click="$set('showDeleteModal', false)"
                            class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <form method="POST" action="{{ route('verwaltung.companies.destroy', $company->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dash-btn dash-btn-danger">
                            Endgültig löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
