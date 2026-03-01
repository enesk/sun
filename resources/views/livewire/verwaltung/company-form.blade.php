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
        {{-- SEKTION 4: Social Links (Premium)                              --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">
                Social Media
                @if(!$isPremium)
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium ml-1 px-1.5 py-0.5 rounded" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12); color: var(--portal-accent-dark, #92400e);">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Premium
                    </span>
                @endif
            </h2>

            @if($isPremium || $isAdmin)
                <div class="dash-form-grid dash-form-grid-2">
                    <div>
                        <label class="dash-label">Facebook</label>
                        <input type="url" wire:model.blur="socialFacebook" placeholder="https://facebook.com/..."
                               class="dash-input {{ $errors->has('socialFacebook') ? 'dash-input-error' : '' }}">
                        @error('socialFacebook') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">Instagram</label>
                        <input type="url" wire:model.blur="socialInstagram" placeholder="https://instagram.com/..."
                               class="dash-input {{ $errors->has('socialInstagram') ? 'dash-input-error' : '' }}">
                        @error('socialInstagram') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">LinkedIn</label>
                        <input type="url" wire:model.blur="socialLinkedin" placeholder="https://linkedin.com/..."
                               class="dash-input {{ $errors->has('socialLinkedin') ? 'dash-input-error' : '' }}">
                        @error('socialLinkedin') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="dash-label">YouTube</label>
                        <input type="url" wire:model.blur="socialYoutube" placeholder="https://youtube.com/@..."
                               class="dash-input {{ $errors->has('socialYoutube') ? 'dash-input-error' : '' }}">
                        @error('socialYoutube') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="dash-input-hint mt-2">Links werden auf Ihrem öffentlichen Firmenprofil angezeigt.</p>
            @else
                {{-- Soft-Lock: Social Links sind Premium --}}
                <div class="relative">
                    <div class="dash-form-grid dash-form-grid-2 opacity-40 pointer-events-none select-none" aria-hidden="true">
                        <div>
                            <label class="dash-label">Facebook</label>
                            <div class="dash-input" style="background: var(--dash-bg-secondary);">&nbsp;</div>
                        </div>
                        <div>
                            <label class="dash-label">Instagram</label>
                            <div class="dash-input" style="background: var(--dash-bg-secondary);">&nbsp;</div>
                        </div>
                        <div>
                            <label class="dash-label">LinkedIn</label>
                            <div class="dash-input" style="background: var(--dash-bg-secondary);">&nbsp;</div>
                        </div>
                        <div>
                            <label class="dash-label">YouTube</label>
                            <div class="dash-input" style="background: var(--dash-bg-secondary);">&nbsp;</div>
                        </div>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center" style="background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.7) 40%, rgba(255,255,255,0.9) 100%);">
                        <div class="text-center p-4">
                            <div class="w-10 h-10 rounded-full mx-auto mb-2 flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                                <svg class="w-5 h-5" style="color: var(--portal-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">Social Media Links</p>
                            <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary);">Verlinken Sie Facebook, Instagram & mehr auf Ihrem Profil.</p>
                            <a href="{{ route('verwaltung.subscriptions.index') }}" class="inline-flex items-center gap-1 text-xs font-medium mt-2 transition-colors hover:opacity-80" style="color: var(--portal-accent);">
                                Auf Premium upgraden
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ================================================================ --}}
        {{-- SEKTION 5: Öffnungszeiten (Premium)                             --}}
        {{-- ================================================================ --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-form-section-title">
                Öffnungszeiten
                @if(!$isPremium)
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium ml-1 px-1.5 py-0.5 rounded" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12); color: var(--portal-accent-dark, #92400e);">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Premium
                    </span>
                @endif
            </h2>

            @if($isPremium || $isAdmin)
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
                <p class="dash-input-hint mt-3">Öffnungszeiten werden auf Ihrem öffentlichen Firmenprofil angezeigt.</p>
            @else
                {{-- Soft-Lock: Öffnungszeiten sind Premium --}}
                <div class="relative">
                    @php $dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag']; @endphp
                    <div class="space-y-2 opacity-40 pointer-events-none select-none" aria-hidden="true">
                        @foreach($dayNames as $index => $dayName)
                            <div class="flex items-center gap-3 py-2 {{ $index < 6 ? 'border-b' : '' }}"
                                 style="{{ $index < 6 ? 'border-color: var(--dash-border);' : '' }}">
                                <span class="w-24 sm:w-28 text-sm font-medium shrink-0" style="color: var(--dash-text-secondary);">
                                    {{ $dayName }}
                                </span>
                                <div class="flex items-center gap-2 ml-auto">
                                    <div class="dash-input" style="width: 5rem; padding: 0.375rem 0.5rem; background: var(--dash-bg-secondary);">{{ $index < 5 ? '08:00' : '--:--' }}</div>
                                    <span style="color: var(--dash-text-muted);">–</span>
                                    <div class="dash-input" style="width: 5rem; padding: 0.375rem 0.5rem; background: var(--dash-bg-secondary);">{{ $index < 5 ? '17:00' : '--:--' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center" style="background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 30%, rgba(255,255,255,0.9) 100%);">
                        <div class="text-center p-4">
                            <div class="w-10 h-10 rounded-full mx-auto mb-2 flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                                <svg class="w-5 h-5" style="color: var(--portal-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">Öffnungszeiten anzeigen</p>
                            <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary);">Zeigen Sie Besuchern wann Sie erreichbar sind.</p>
                            <a href="{{ route('verwaltung.subscriptions.index') }}" class="inline-flex items-center gap-1 text-xs font-medium mt-2 transition-colors hover:opacity-80" style="color: var(--portal-accent);">
                                Auf Premium upgraden
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
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
                        @if(!$isPremium)
                            <span class="inline-flex items-center gap-0.5 text-xs font-medium ml-1 px-1.5 py-0.5 rounded" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12); color: var(--portal-accent-dark, #92400e);">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Premium
                            </span>
                        @endif
                    </label>

                    @if($isPremium || $isAdmin)
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
                    @else
                        {{-- Soft-Lock: Cover ist Premium --}}
                        <div class="w-full max-w-md h-24 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, rgba(var(--portal-primary-rgb, 59 130 246), 0.04), rgba(var(--portal-accent-rgb, 245 158 11), 0.04)); border: 1px dashed var(--dash-border);">
                            <div class="text-center">
                                <svg class="w-5 h-5 mx-auto mb-1" style="color: var(--portal-accent); opacity: 0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <p class="text-xs" style="color: var(--dash-text-muted);">Premium-Feature</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Galerie --}}
                <div>
                    @php
                        $maxGallery = $isPremium ? 20 : 3;
                        $currentGalleryCount = count($existingGallery) + count($gallery);
                        $galleryFull = $currentGalleryCount >= $maxGallery;
                    @endphp

                    <label class="dash-label">
                        Galerie
                        <span class="font-normal" style="color: var(--dash-text-muted);">
                            ({{ $currentGalleryCount }}/{{ $maxGallery }} Bilder{{ !$isPremium ? ' · Kostenlos' : '' }}, je max. 4 MB)
                        </span>
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

                    @if(!$galleryFull)
                        <input type="file" wire:model="gallery" accept="image/jpeg,image/png,image/webp" multiple
                               class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer file:transition-colors"
                               style="color: var(--dash-text-muted);">
                        <div wire:loading wire:target="gallery" class="dash-input-hint mt-1">Wird hochgeladen...</div>
                    @endif
                    @error('gallery.*') <p class="dash-input-error-msg">{{ $message }}</p> @enderror

                    {{-- Soft-Lock: Free-User Galerie-Limit erreicht --}}
                    @if(!$isPremium && $galleryFull)
                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.06); border: 1px solid rgba(var(--portal-accent-rgb, 245 158 11), 0.15);">
                            <div class="flex items-start gap-2.5">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                                    <svg class="w-4 h-4" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-semibold" style="color: var(--portal-accent-dark)">Bilder-Limit erreicht ({{ $maxGallery }}/{{ $maxGallery }})</p>
                                    <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary)">
                                        Mit Premium laden Sie bis zu <strong>20 Fotos</strong> hoch und zeigen Ihr Unternehmen von seiner besten Seite.
                                    </p>
                                    <a href="{{ route('verwaltung.subscriptions.index') }}" class="inline-flex items-center gap-1 text-xs font-medium mt-1.5 transition-colors hover:opacity-80" style="color: var(--portal-accent);">
                                        Auf Premium upgraden
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif(!$isPremium && $currentGalleryCount > 0)
                        <p class="dash-input-hint mt-1">
                            {{ $maxGallery - $currentGalleryCount }} {{ ($maxGallery - $currentGalleryCount) === 1 ? 'Bild' : 'Bilder' }} verbleibend ·
                            <a href="{{ route('verwaltung.subscriptions.index') }}" class="font-medium transition-colors hover:opacity-80" style="color: var(--portal-accent);">Premium: bis zu 20 Bilder</a>
                        </p>
                    @endif
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
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.set('showDeleteModal', false)">
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
