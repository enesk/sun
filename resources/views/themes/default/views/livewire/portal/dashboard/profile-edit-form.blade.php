<div x-data="{ showSuccess: @entangle('saved') }">
    {{-- Success Banner --}}
    <div x-show="showSuccess" x-transition x-cloak
         x-init="$watch('showSuccess', val => { if(val) setTimeout(() => showSuccess = false, 3000) })"
         class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 text-sm text-green-700" role="alert">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Änderungen gespeichert!
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Stammdaten --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Stammdaten
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Firmenname --}}
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-base-content mb-1">Firmenname *</label>
                    <input type="text" id="name" wire:model.blur="name"
                           class="input input-bordered w-full {{ $errors->has('name') ? 'input-error' : '' }}"
                           aria-describedby="name-hint"
                           placeholder="z.B. Malerbetrieb Müller GmbH">
                    <p id="name-hint" class="text-xs text-base-content/50 mt-1">Der offizielle Name Ihres Unternehmens</p>
                    @error('name') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Beschreibung --}}
                <div class="sm:col-span-2" x-data="{ charCount: $wire.description.length }">
                    <label for="description" class="block text-sm font-medium text-base-content mb-1">Beschreibung</label>
                    <textarea id="description" wire:model.blur="description"
                              rows="4"
                              class="textarea textarea-bordered w-full {{ $errors->has('description') ? 'input-error' : '' }}"
                              aria-describedby="description-hint"
                              maxlength="5000"
                              x-on:input="charCount = $event.target.value.length"
                              placeholder="Beschreiben Sie Ihr Unternehmen, Ihre Leistungen und was Sie auszeichnet..."></textarea>
                    <div class="flex justify-between mt-1">
                        <p id="description-hint" class="text-xs text-base-content/50">Wird auf Ihrer Firmenseite angezeigt</p>
                        <span class="text-xs text-base-content/40" x-text="charCount + ' / 5.000'"></span>
                    </div>
                    @error('description') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Logo --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Firmenlogo
            </h2>

            <div class="flex items-start gap-4">
                {{-- Current Logo Preview --}}
                <div class="w-24 h-24 rounded-lg border-2 border-dashed border-base-300 flex items-center justify-center overflow-hidden shrink-0 bg-base-200">
                    @if($logo)
                        @if($this->logoPreviewUrl)
                            <img src="{{ $this->logoPreviewUrl }}" alt="Neues Logo" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-portal-primary/5">
                                <svg class="w-8 h-8 text-portal-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                    @elseif($currentLogo)
                        <img src="{{ $currentLogo }}" alt="{{ $name }} Logo" class="w-full h-full object-cover">
                    @else
                        <svg class="w-10 h-10 text-base-content/20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    @endif
                </div>

                <div class="flex-1">
                    <label for="logo-upload" class="btn btn-sm btn-portal-outline cursor-pointer inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Logo hochladen
                    </label>
                    <input type="file" id="logo-upload" wire:model="logo" class="hidden" accept="image/jpeg,image/png,image/webp">

                    @if($currentLogo && !$logo)
                        <button type="button" wire:click="removeLogo" class="btn btn-sm btn-ghost text-error ml-2">Entfernen</button>
                    @endif

                    <p class="text-xs text-base-content/50 mt-2">JPEG, PNG oder WebP. Max. 2 MB.</p>
                    @error('logo') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror

                    <div wire:loading wire:target="logo" class="text-xs text-portal-primary mt-1 flex items-center gap-1">
                        <span class="loading loading-spinner loading-xs"></span> Wird hochgeladen...
                    </div>
                </div>
            </div>
        </div>

        {{-- Cover/Banner-Bild --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Titelbild / Banner
            </h2>

            {{-- Preview --}}
            <div class="relative w-full rounded-xl overflow-hidden border-2 border-dashed border-base-300 bg-base-200" style="aspect-ratio: 3/1;">
                @if($cover)
                    @if($this->coverPreviewUrl)
                        <img src="{{ $this->coverPreviewUrl }}" alt="Neues Titelbild" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-portal-primary/5">
                            <svg class="w-12 h-12 text-portal-primary/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
                        </div>
                    @endif
                @elseif($currentCoverUrl)
                    <img src="{{ $currentCoverUrl }}" alt="{{ $name }} Titelbild" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center text-base-content/30 gap-2">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-xs">Empfohlen: 1200 x 400 px (3:1)</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2 mt-3">
                <label for="cover-upload" class="btn btn-sm btn-portal-outline cursor-pointer inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Titelbild hochladen
                </label>
                <input type="file" id="cover-upload" wire:model="cover" class="hidden" accept="image/jpeg,image/png,image/webp">

                @if($currentCoverUrl && !$cover)
                    <button type="button" wire:click="removeCover" class="btn btn-sm btn-ghost text-error">Entfernen</button>
                @endif
            </div>

            <p class="text-xs text-base-content/50 mt-2">JPEG, PNG oder WebP. Max. 5 MB. Wird als Banner auf Ihrer Firmenseite angezeigt.</p>
            @error('cover') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror

            <div wire:loading wire:target="cover" class="text-xs text-portal-primary mt-1 flex items-center gap-1">
                <span class="loading loading-spinner loading-xs"></span> Wird hochgeladen...
            </div>
        </div>

        {{-- Adresse --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Adresse
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-3">
                    <label for="street" class="block text-sm font-medium text-base-content mb-1">Straße *</label>
                    <input type="text" id="street" wire:model.blur="street"
                           class="input input-bordered w-full {{ $errors->has('street') ? 'input-error' : '' }}"
                           placeholder="Musterstraße">
                    @error('street') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="house_no" class="block text-sm font-medium text-base-content mb-1">Hausnr.</label>
                    <input type="text" id="house_no" wire:model.blur="house_no"
                           class="input input-bordered w-full"
                           placeholder="12a">
                </div>

                <div>
                    <label for="zipcode" class="block text-sm font-medium text-base-content mb-1">PLZ *</label>
                    <input type="text" id="zipcode" wire:model.blur="zipcode"
                           class="input input-bordered w-full {{ $errors->has('zipcode') ? 'input-error' : '' }}"
                           placeholder="12345"
                           maxlength="5">
                    @error('zipcode') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Stadt-Autocomplete --}}
                <div class="sm:col-span-3" x-data="{ open: false }" @click.outside="open = false">
                    <label for="city-search" class="block text-sm font-medium text-base-content mb-1">Stadt *</label>
                    <div class="relative">
                        <input type="text" id="city-search"
                               wire:model.live.debounce.300ms="citySearch"
                               @focus="open = true"
                               @input="open = true"
                               class="input input-bordered w-full {{ $errors->has('city_id') ? 'input-error' : '' }}"
                               placeholder="Stadt suchen..."
                               role="combobox"
                               aria-expanded="false"
                               :aria-expanded="open && {{ count($citySuggestions) }} > 0 ? 'true' : 'false'"
                               aria-controls="city-listbox"
                               autocomplete="off">

                        {{-- Status Icon --}}
                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                            @if($city_id)
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Suggestions Dropdown --}}
                        @if(count($citySuggestions) > 0)
                            <ul x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                x-cloak
                                id="city-listbox"
                                role="listbox"
                                class="absolute z-10 mt-1 w-full bg-base-100 border border-base-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach($citySuggestions as $suggestion)
                                    <li wire:click="selectCity({{ $suggestion['id'] }})"
                                        @click="open = false"
                                        role="option"
                                        class="px-3 py-2 text-sm cursor-pointer hover:bg-base-200 transition-colors">
                                        {{ $suggestion['zipcode'] }} {{ $suggestion['name'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @error('city_id') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Kontakt --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Kontaktdaten
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="tel" class="block text-sm font-medium text-base-content mb-1">Telefon</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <input type="tel" id="tel" wire:model.blur="tel"
                               class="input input-bordered w-full pl-10"
                               placeholder="030 1234567">
                    </div>
                    @error('tel') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-base-content mb-1">E-Mail *</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="email" id="email" wire:model.blur="email"
                               class="input input-bordered w-full pl-10 {{ $errors->has('email') ? 'input-error' : '' }}"
                               placeholder="info@firma.de">
                    </div>
                    <p class="text-xs text-base-content/50 mt-1">Wird auf der Firmenseite als Kontakt-E-Mail angezeigt</p>
                    @error('email') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="website" class="block text-sm font-medium text-base-content mb-1">Website</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                        </div>
                        <input type="url" id="website" wire:model.blur="website"
                               class="input input-bordered w-full pl-10 {{ $errors->has('website') ? 'input-error' : '' }}"
                               placeholder="https://www.firma.de">
                    </div>
                    @error('website') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Kategorien --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Kategorien
                <span class="text-xs font-normal text-base-content/50">({{ count($selectedCategories) }} von 5 ausgewählt)</span>
            </h2>

            <div class="flex flex-wrap gap-2">
                @foreach($categories as $category)
                    @php $isSelected = in_array($category->id, $selectedCategories); @endphp
                    @php $isDisabled = !$isSelected && count($selectedCategories) >= 5; @endphp
                    <button type="button"
                            wire:click="toggleCategory({{ $category->id }})"
                            @if($isDisabled) disabled @endif
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border transition-all touch-target
                                   {{ $isSelected ? 'bg-portal-primary text-white border-portal-primary' : ($isDisabled ? 'bg-base-100 text-base-content/30 border-base-200 cursor-not-allowed' : 'bg-base-100 text-base-content/70 border-base-200 hover:border-portal-primary/50 hover:text-portal-primary') }}"
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                        @if($isSelected)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
            @error('selectedCategories') <p class="text-xs text-error mt-2">{{ $message }}</p> @enderror
        </div>

        {{-- Speichern Button --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('portal.owner.dashboard') }}" class="btn btn-sm btn-ghost text-base-content/60">
                Zurück zur Übersicht
            </a>
            <button type="submit" class="btn btn-portal flex items-center gap-2" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Änderungen speichern</span>
                <span wire:loading wire:target="save" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Wird gespeichert...
                </span>
            </button>
        </div>
    </form>
</div>
