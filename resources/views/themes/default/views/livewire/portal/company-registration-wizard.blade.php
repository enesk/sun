<div>
    @if($submitted && $createdCompany)
        {{-- Erfolgs-Seite --}}
        <div class="p-6 sm:p-8 text-center" x-data="{ show: false }" x-init="$nextTick(() => show = true)">
            {{-- Animated checkmark --}}
            <div class="relative inline-flex items-center justify-center w-20 h-20 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full animate-ping opacity-20"
                     style="background: var(--portal-primary, #3B82F6);"></div>
                <div class="relative w-20 h-20 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, var(--portal-primary, #3B82F6), var(--portal-primary-hover, #2563EB));">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         x-show="show" x-transition:enter="transition ease-out duration-500 delay-300"
                         x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-base-content mb-2"
                x-show="show" x-transition:enter="transition ease-out duration-500 delay-500"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                Firma erfolgreich eingetragen!
            </h2>
            <p class="text-base-content/60 mb-8"
               x-show="show" x-transition:enter="transition ease-out duration-500 delay-700"
               x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <strong class="text-base-content">{{ $createdCompany->name }}</strong> ist jetzt im Portal sichtbar.
            </p>

            {{-- Mini-Preview Card --}}
            <div class="rounded-xl border border-base-200 overflow-hidden mb-8 text-left max-w-sm mx-auto"
                 x-show="show" x-transition:enter="transition ease-out duration-500 delay-900"
                 x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="h-2" style="background: linear-gradient(90deg, var(--portal-primary, #3B82F6), var(--portal-accent, #F59E0B));"></div>
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        @if($createdCompany->getFirstMediaUrl('logo', 'medium'))
                            <img src="{{ $createdCompany->getFirstMediaUrl('logo', 'medium') }}"
                                 alt="{{ $createdCompany->name }}"
                                 class="w-10 h-10 rounded-lg object-cover">
                        @else
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold"
                                 style="background: linear-gradient(135deg, var(--portal-primary, #3B82F6), var(--portal-primary-hover, #2563EB));">
                                {{ strtoupper(substr($createdCompany->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-sm text-base-content">{{ $createdCompany->name }}</p>
                            <p class="text-xs text-base-content/50">{{ $createdCompany->street }} {{ $createdCompany->house_no }}, {{ $createdCompany->zipcode }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Next Steps --}}
            <div class="grid grid-cols-3 gap-3 mb-8 text-center"
                 x-show="show" x-transition:enter="transition ease-out duration-500 delay-1000"
                 x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                @if($createdCompany->getFirstMediaUrl('logo'))
                    <div class="p-3">
                        <div class="w-8 h-8 mx-auto rounded-lg flex items-center justify-center mb-2 bg-green-100">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-xs font-medium text-green-600">Logo hochgeladen</p>
                    </div>
                @else
                    <div class="p-3">
                        <div class="w-8 h-8 mx-auto rounded-lg flex items-center justify-center mb-2"
                             style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1);">
                            <span class="text-portal-primary font-bold text-xs">1</span>
                        </div>
                        <p class="text-xs font-medium text-base-content">Logo hochladen</p>
                    </div>
                @endif
                <div class="p-3">
                    <div class="w-8 h-8 mx-auto rounded-lg flex items-center justify-center mb-2"
                         style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1);">
                        <span class="text-portal-primary font-bold text-xs">{{ $createdCompany->getFirstMediaUrl('logo') ? '1' : '2' }}</span>
                    </div>
                    <p class="text-xs font-medium text-base-content">Profil ergänzen</p>
                </div>
                <div class="p-3">
                    <div class="w-8 h-8 mx-auto rounded-lg flex items-center justify-center mb-2"
                         style="background: rgba(var(--portal-accent-rgb, 245, 158, 11), 0.15);">
                        <span class="text-portal-accent-dark font-bold text-xs">&#9733;</span>
                    </div>
                    <p class="text-xs font-medium text-base-content">Premium werden</p>
                </div>
            </div>

            {{-- CTAs --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('portal.companies.show', $createdCompany->url_slug) }}"
                   class="btn-portal flex items-center justify-center gap-2 py-3 px-6 rounded-xl font-semibold w-full sm:w-auto"
                   style="border-radius: 0.75rem;">
                    Zum Firmenprofil
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="{{ route('home') }}"
                   class="btn-portal-outline flex items-center justify-center py-3 px-6 rounded-xl w-full sm:w-auto"
                   style="border-radius: 0.75rem;">
                    Zur Startseite
                </a>
            </div>
        </div>
    @else
        {{-- Step-Indicator (Desktop) — jetzt innerhalb der Livewire-Component --}}
        <div class="hidden md:block px-5 sm:px-7 pt-5 pb-0">
            @php
                $steps = [
                    1 => ['icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'label' => 'Firmendaten'],
                    2 => ['icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'label' => 'Adresse'],
                    3 => ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => 'Kontakt'],
                    4 => ['icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Logo'],
                    5 => ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Fertig'],
                ];
            @endphp
            <div class="flex items-center justify-between" role="navigation" aria-label="Fortschritt">
                @foreach($steps as $stepNum => $stepInfo)
                    <div class="flex items-center {{ $stepNum < 5 ? 'flex-1' : '' }}">
                        <div class="flex items-center gap-2">
                            {{-- Step Circle --}}
                            <div @class([
                                'flex items-center justify-center w-9 h-9 rounded-full transition-all duration-300',
                                'bg-portal-primary text-white shadow-md ring-2 ring-portal-primary/20' => $currentStep === $stepNum,
                                'bg-green-100 text-green-600' => $currentStep > $stepNum,
                                'bg-base-200/50 text-base-content/30' => $currentStep < $stepNum,
                            ])>
                                @if($currentStep > $stepNum)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $stepInfo['icon'] }}"/></svg>
                                @endif
                            </div>
                            {{-- Label --}}
                            <span @class([
                                'text-xs whitespace-nowrap transition-colors duration-300',
                                'text-portal-primary font-semibold' => $currentStep === $stepNum,
                                'text-green-600 font-medium' => $currentStep > $stepNum,
                                'text-base-content/30' => $currentStep < $stepNum,
                            ])>{{ $stepInfo['label'] }}</span>
                        </div>
                        @if($stepNum < 5)
                            {{-- Progress Line --}}
                            <div class="flex-1 mx-3">
                                <div class="h-[2px] bg-base-200 relative overflow-hidden rounded-full">
                                    <div @class([
                                        'absolute inset-y-0 left-0 transition-all duration-500 rounded-full',
                                        'w-full bg-green-400' => $currentStep > $stepNum,
                                        'w-0 bg-portal-primary' => $currentStep <= $stepNum,
                                    ])></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mobile: Step Progress Bar --}}
        <div class="md:hidden px-5 pt-4 pb-0">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-medium text-base-content/60">Schritt {{ $currentStep }} von {{ $totalSteps }}</span>
                <span class="text-xs font-semibold text-portal-primary">{{ round(($currentStep / $totalSteps) * 100) }}%</span>
            </div>
            <div class="w-full bg-base-200 rounded-full h-1.5" role="progressbar"
                 aria-valuenow="{{ round(($currentStep / $totalSteps) * 100) }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-1.5 rounded-full transition-all duration-500"
                     style="width: {{ ($currentStep / $totalSteps) * 100 }}%; background: var(--portal-primary, #3B82F6);"></div>
            </div>
            <p class="text-xs text-base-content/50 mt-1 text-center">
                @php $stepNames = [1 => 'Firmendaten', 2 => 'Adresse', 3 => 'Kontakt', 4 => 'Logo', 5 => 'Zusammenfassung']; @endphp
                {{ $stepNames[$currentStep] ?? '' }}
            </p>
        </div>

        {{-- Step 1: Firmendaten --}}
        @if($currentStep === 1)
            <div class="p-5 sm:p-7" role="group" aria-label="Firmendaten">
                <div class="form-section-header">
                    <h2>Firmendaten</h2>
                    <p>Grundlegende Informationen zu Ihrem Unternehmen.</p>
                </div>

                <div class="space-y-6">
                    {{-- Firmenname --}}
                    <div>
                        <label for="name" class="label-portal">
                            Firmenname <span class="required">*</span>
                        </label>
                        <input type="text" id="name" wire:model.blur="name"
                               @class(['input-portal', 'input-error' => $errors->has('name')])
                               placeholder="z.B. Müller Elektrotechnik GmbH"
                               aria-describedby="name-help" autofocus>
                        <p id="name-help" class="help-portal">Der offizielle Name Ihres Unternehmens.</p>
                        @error('name') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>

                    {{-- Beschreibung --}}
                    <div x-data="{ charCount: 0 }" x-init="charCount = $refs.desc.value.length">
                        <label for="description" class="label-portal">Beschreibung</label>
                        <textarea id="description" wire:model.blur="description" rows="4" maxlength="5000"
                                  class="textarea-portal"
                                  placeholder="Beschreiben Sie Ihr Unternehmen, Ihre Dienstleistungen und was Sie besonders macht..."
                                  aria-describedby="description-help"
                                  x-ref="desc"
                                  x-on:input="charCount = $refs.desc.value.length"></textarea>
                        <div class="flex items-center justify-between mt-1.5">
                            <p id="description-help" class="help-portal">Eine gute Beschreibung hilft bei der Auffindbarkeit.</p>
                            <span class="text-xs text-base-content/40" x-text="charCount.toLocaleString('de-DE') + ' / 5.000'"></span>
                        </div>
                        @error('description') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>

                    {{-- Kategorien --}}
                    <div>
                        <label class="label-portal">
                            Kategorien <span class="required">*</span>
                        </label>
                        <p class="help-portal mb-3">Wählen Sie 1–5 Kategorien, die zu Ihrem Unternehmen passen.</p>

                        @if($categories->count() > 12)
                            <div class="mb-3">
                                <input type="text" wire:model.live.debounce.200ms="categoryFilter"
                                       class="input-portal !py-2 !text-sm w-full sm:w-64"
                                       placeholder="Kategorie suchen..." aria-label="Kategorien filtern">
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2" role="group" aria-label="Kategorie-Auswahl">
                            @foreach($categories as $category)
                                @php $isSelected = in_array($category->id, $selectedCategories); @endphp
                                <button type="button" wire:click="toggleCategory({{ $category->id }})"
                                        @class([
                                            'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border transition-all duration-150',
                                            'bg-portal-primary text-white border-transparent shadow-sm' => $isSelected,
                                            'bg-white/60 text-base-content border-base-300/50 hover:border-portal-primary/30 hover:bg-portal-primary-light' => !$isSelected && count($selectedCategories) < 5,
                                            'bg-base-100 text-base-content/30 border-base-200 cursor-not-allowed' => !$isSelected && count($selectedCategories) >= 5,
                                        ])
                                        @if(!$isSelected && count($selectedCategories) >= 5) disabled @endif
                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                        aria-label="{{ $category->name }} {{ $isSelected ? '(ausgewählt)' : '' }}">
                                    @if($category->icon) <i data-lucide="{{ $category->icon }}" class="w-4 h-4 inline-block" aria-hidden="true"></i> @endif
                                    {{ $category->name }}
                                    @if($isSelected)
                                        <svg class="w-4 h-4 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <p class="help-portal mt-2">{{ count($selectedCategories) }} von 5 ausgewählt</p>
                        @error('selectedCategories') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 2: Adresse --}}
        @if($currentStep === 2)
            <div class="p-5 sm:p-7" role="group" aria-label="Adresse">
                <div class="form-section-header">
                    <h2>Adresse</h2>
                    <p>Der Standort Ihres Unternehmens.</p>
                </div>

                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-3">
                            <label for="street" class="label-portal">Straße <span class="required">*</span></label>
                            <input type="text" id="street" wire:model.blur="street"
                                   @class(['input-portal', 'input-error' => $errors->has('street')])
                                   placeholder="Musterstraße" autocomplete="street-address">
                            @error('street') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="house_no" class="label-portal">Hausnr.</label>
                            <input type="text" id="house_no" wire:model.blur="house_no"
                                   class="input-portal" placeholder="12a">
                            @error('house_no') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="zipcode" class="label-portal">Postleitzahl <span class="required">*</span></label>
                            <input type="text" id="zipcode" wire:model.blur="zipcode"
                                   @class(['input-portal', 'input-error' => $errors->has('zipcode')])
                                   placeholder="10115" maxlength="5" inputmode="numeric"
                                   pattern="[0-9]{5}" autocomplete="postal-code">
                            @error('zipcode') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2" x-data="{ open: false }" @click.outside="open = false">
                            <label for="citySearch" class="label-portal">Stadt <span class="required">*</span></label>
                            <div class="relative">
                                <input type="text" id="citySearch"
                                       wire:model.live.debounce.300ms="citySearch"
                                       @focus="open = true" @input="open = true"
                                       @class(['input-portal pr-10', 'input-error' => $errors->has('city_id')])
                                       placeholder="Stadt eingeben..." autocomplete="off"
                                       role="combobox"
                                       aria-expanded="{{ count($citySuggestions) > 0 ? 'true' : 'false' }}"
                                       aria-controls="city-listbox" aria-describedby="city-help">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    @if($city_id)
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <svg class="w-5 h-5 text-base-content/25" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    @endif
                                </div>
                                @if(count($citySuggestions) > 0)
                                    <ul x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        id="city-listbox"
                                        class="absolute z-20 w-full mt-1 bg-white border border-base-200 rounded-xl shadow-lg max-h-48 overflow-y-auto"
                                        role="listbox" aria-label="Stadtvorschläge">
                                        @foreach($citySuggestions as $city)
                                            <li>
                                                <button type="button"
                                                        wire:click="selectCity({{ $city['id'] }})"
                                                        @click="open = false"
                                                        class="w-full px-4 py-2.5 text-left hover:bg-portal-primary-light transition-colors text-sm"
                                                        role="option">
                                                    <span class="font-medium">{{ $city['name'] }}</span>
                                                    <span class="text-base-content/40 ml-1">{{ $city['zipcode'] }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <p id="city-help" class="help-portal">Mindestens 2 Zeichen für Vorschläge.</p>
                            @error('city_id') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 3: Kontakt --}}
        @if($currentStep === 3)
            <div class="p-5 sm:p-7" role="group" aria-label="Kontaktdaten">
                <div class="form-section-header">
                    <h2>Kontaktdaten</h2>
                    <p>So können Kunden Sie erreichen.</p>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="email" class="label-portal">E-Mail-Adresse <span class="required">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                                <svg class="w-5 h-5 text-base-content/25" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <input type="email" id="email" wire:model.blur="email"
                                   @class(['input-portal has-icon', 'input-error' => $errors->has('email')])
                                   placeholder="info@ihre-firma.de" autocomplete="email"
                                   aria-describedby="email-help">
                        </div>
                        <p id="email-help" class="help-portal">Wird auf der Firmenseite als Kontakt angezeigt.</p>
                        @error('email') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tel" class="label-portal">Telefon</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                                <svg class="w-5 h-5 text-base-content/25" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <input type="tel" id="tel" wire:model.blur="tel"
                                   class="input-portal has-icon"
                                   placeholder="+49 30 1234567" autocomplete="tel">
                        </div>
                        @error('tel') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="website" class="label-portal">Website</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                                <svg class="w-5 h-5 text-base-content/25" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            </div>
                            <input type="url" id="website" wire:model.blur="website"
                                   class="input-portal has-icon"
                                   placeholder="https://www.ihre-firma.de" autocomplete="url">
                        </div>
                        @error('website') <p class="text-error text-sm mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Hinweis --}}
                <div class="mt-6 rounded-xl p-3.5 flex gap-3" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.05);">
                    <svg class="w-4 h-4 text-portal-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-base-content/60">Kontaktdaten können Sie jederzeit im Dashboard ändern.</p>
                </div>
            </div>
        @endif

        {{-- Step 4: Logo --}}
        @if($currentStep === 4)
            <div class="p-5 sm:p-7" role="group" aria-label="Logo hochladen">
                <div class="form-section-header">
                    <h2>Logo hochladen</h2>
                    <p>Ein Logo macht Ihren Eintrag professioneller und wiedererkennbar. <span class="text-base-content/40">(optional)</span></p>
                </div>

                <div class="space-y-5">
                    @if($logo)
                        {{-- Logo-Vorschau --}}
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative group">
                                <div class="w-40 h-40 rounded-2xl overflow-hidden border-2 border-portal-primary/20 shadow-lg">
                                    @if($this->logoPreviewUrl)
                                        <img src="{{ $this->logoPreviewUrl }}"
                                             alt="Logo-Vorschau"
                                             class="w-full h-full object-cover">
                                    @else
                                        {{-- Fallback wenn keine Preview möglich (z.B. HEIC) --}}
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-portal-primary/5">
                                            <svg class="w-10 h-10 text-portal-primary/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="text-xs text-base-content/40">Vorschau nicht verfügbar</span>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" wire:click="removeLogo"
                                        class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-red-500 text-white flex items-center justify-center shadow-md hover:bg-red-600 transition-colors"
                                        aria-label="Logo entfernen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p class="text-sm text-green-600 font-medium flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Logo ausgewählt
                            </p>
                        </div>
                    @else
                        {{-- Upload-Bereich --}}
                        <div class="relative">
                            <label for="logo-upload"
                                   class="flex flex-col items-center justify-center w-full py-10 px-6 border-2 border-dashed border-base-300/60 rounded-2xl cursor-pointer
                                          hover:border-portal-primary/40 hover:bg-portal-primary/[0.02] transition-all duration-200"
                                   x-data="{ dragging: false }"
                                   x-on:dragover.prevent="dragging = true"
                                   x-on:dragleave="dragging = false"
                                   x-on:drop.prevent="dragging = false"
                                   :class="{ 'border-portal-primary bg-portal-primary/5': dragging }">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                                     style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.08);">
                                    <svg class="w-7 h-7 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-base-content mb-1">
                                    Logo auswählen oder hierher ziehen
                                </p>
                                <p class="text-xs text-base-content/40">JPEG, PNG oder WebP · Max. 2 MB · Wird auf 300×300px zugeschnitten</p>
                                <input id="logo-upload" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            </label>

                            {{-- Loading-State --}}
                            <div wire:loading wire:target="logo" class="absolute inset-0 bg-white/80 rounded-2xl flex items-center justify-center">
                                <div class="flex items-center gap-2 text-sm text-portal-primary font-medium">
                                    <span class="loading loading-spinner loading-sm"></span>
                                    Wird hochgeladen...
                                </div>
                            </div>
                        </div>
                    @endif

                    @error('logo')
                        <p class="text-error text-sm" role="alert">{{ $message }}</p>
                    @enderror

                    {{-- Hinweis --}}
                    <div class="rounded-xl p-3.5 flex gap-3" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.05);">
                        <svg class="w-4 h-4 text-portal-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-base-content/60">Das Logo können Sie auch später im Dashboard hochladen oder ändern.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 5: Zusammenfassung --}}
        @if($currentStep === 5)
            <div class="p-5 sm:p-7" role="group" aria-label="Zusammenfassung">
                <div class="form-section-header">
                    <h2>Zusammenfassung</h2>
                    <p>Überprüfen Sie Ihre Angaben.</p>
                </div>

                <div class="space-y-3">
                    {{-- Firmendaten --}}
                    <div class="rounded-xl overflow-hidden border border-base-200/60">
                        <div class="flex items-center justify-between px-4 py-2.5" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.03);">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span class="text-xs font-semibold text-base-content/60 uppercase tracking-wide">Firmendaten</span>
                            </div>
                            <button type="button" wire:click="goToStep(1)" class="text-xs text-portal-primary-dark hover:underline font-medium">Ändern</button>
                        </div>
                        <div class="px-4 py-3">
                            <dl class="space-y-1.5 text-sm">
                                <div class="flex flex-col sm:flex-row sm:gap-2">
                                    <dt class="text-base-content/40 sm:w-24 shrink-0 text-xs">Name</dt>
                                    <dd class="font-medium text-base-content">{{ $name }}</dd>
                                </div>
                                @if($description)
                                    <div class="flex flex-col sm:flex-row sm:gap-2">
                                        <dt class="text-base-content/40 sm:w-24 shrink-0 text-xs">Beschreibung</dt>
                                        <dd class="text-base-content/80 text-xs">{{ Str::limit($description, 100) }}</dd>
                                    </div>
                                @endif
                                <div class="flex flex-col sm:flex-row sm:gap-2">
                                    <dt class="text-base-content/40 sm:w-24 shrink-0 text-xs">Kategorien</dt>
                                    <dd class="flex flex-wrap gap-1">
                                        @foreach(\App\Models\Portal\Category::whereIn('id', $selectedCategories)->get() as $cat)
                                            <span class="badge-portal text-xs inline-flex items-center gap-1">@if($cat->icon)<i data-lucide="{{ $cat->icon }}" class="w-3 h-3 inline-block" aria-hidden="true"></i>@endif{{ $cat->name }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Adresse --}}
                    <div class="rounded-xl overflow-hidden border border-base-200/60">
                        <div class="flex items-center justify-between px-4 py-2.5" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.03);">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="text-xs font-semibold text-base-content/60 uppercase tracking-wide">Adresse</span>
                            </div>
                            <button type="button" wire:click="goToStep(2)" class="text-xs text-portal-primary-dark hover:underline font-medium">Ändern</button>
                        </div>
                        <div class="px-4 py-3">
                            <p class="text-sm text-base-content">
                                {{ $street }} {{ $house_no }}<br>
                                {{ $zipcode }} {{ $selectedCityName }}
                            </p>
                        </div>
                    </div>

                    {{-- Kontakt --}}
                    <div class="rounded-xl overflow-hidden border border-base-200/60">
                        <div class="flex items-center justify-between px-4 py-2.5" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.03);">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="text-xs font-semibold text-base-content/60 uppercase tracking-wide">Kontakt</span>
                            </div>
                            <button type="button" wire:click="goToStep(3)" class="text-xs text-portal-primary-dark hover:underline font-medium">Ändern</button>
                        </div>
                        <div class="px-4 py-3">
                            <dl class="space-y-1 text-sm">
                                <div class="flex gap-2">
                                    <dt class="text-base-content/40 w-16 shrink-0 text-xs">E-Mail</dt>
                                    <dd class="font-medium text-base-content">{{ $email }}</dd>
                                </div>
                                @if($tel)
                                    <div class="flex gap-2">
                                        <dt class="text-base-content/40 w-16 shrink-0 text-xs">Telefon</dt>
                                        <dd class="text-base-content">{{ $tel }}</dd>
                                    </div>
                                @endif
                                @if($website)
                                    <div class="flex gap-2">
                                        <dt class="text-base-content/40 w-16 shrink-0 text-xs">Website</dt>
                                        <dd class="text-base-content">{{ $website }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Logo --}}
                    <div class="rounded-xl overflow-hidden border border-base-200/60">
                        <div class="flex items-center justify-between px-4 py-2.5" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.03);">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-xs font-semibold text-base-content/60 uppercase tracking-wide">Logo</span>
                            </div>
                            <button type="button" wire:click="goToStep(4)" class="text-xs text-portal-primary-dark hover:underline font-medium">Ändern</button>
                        </div>
                        <div class="px-4 py-3">
                            @if($logo)
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-lg overflow-hidden border border-base-200">
                                        @if($this->logoPreviewUrl)
                                            <img src="{{ $this->logoPreviewUrl }}" alt="Logo" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-portal-primary/5">
                                                <svg class="w-6 h-6 text-portal-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-sm text-green-600 font-medium flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Logo hochgeladen
                                    </span>
                                </div>
                            @else
                                <p class="text-sm text-base-content/40 italic">Kein Logo ausgewählt — kann später im Dashboard ergänzt werden.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Kostenlos-Hinweis --}}
                <div class="mt-6 rounded-xl p-3.5 flex gap-3" style="background: rgba(16, 185, 129, 0.05);">
                    <svg class="w-4 h-4 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-green-800">Kostenloser Basiseintrag</p>
                        <p class="text-xs text-green-700/80 mt-0.5">Dauerhaft kostenlos. Premium-Funktionen jederzeit hinzubuchbar.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Navigation --}}
        <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-t border-base-200/40">
            @if($currentStep > 1)
                <button type="button" wire:click="previousStep"
                        class="inline-flex items-center gap-1 text-sm font-medium text-base-content/60 hover:text-base-content transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Zurück
                </button>
            @else
                <div></div>
            @endif

            @if($currentStep < $totalSteps)
                <button type="button" wire:click="nextStep"
                        class="btn-portal inline-flex items-center gap-1 py-2.5 px-5 rounded-xl font-semibold"
                        style="border-radius: 0.75rem;">
                    <span wire:loading.remove wire:target="nextStep">
                        Weiter
                        <svg class="w-4 h-4 ml-0.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                    <span wire:loading wire:target="nextStep" class="loading loading-spinner loading-sm"></span>
                </button>
            @else
                <button type="button" wire:click="submit"
                        class="btn-portal inline-flex items-center gap-1.5 py-2.5 px-5 rounded-xl font-semibold"
                        style="border-radius: 0.75rem;">
                    <span wire:loading.remove wire:target="submit">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Firma eintragen
                    </span>
                    <span wire:loading wire:target="submit" class="loading loading-spinner loading-sm"></span>
                </button>
            @endif
        </div>
    @endif
</div>
