<div x-data="{
    step: 1,
    maxStep: 3,
    visited: [1],
    get canNext() {
        if (this.step === 1) {
            return $wire.title.trim().length > 0 && $wire.employment_type.length > 0;
        }
        if (this.step === 2) {
            return $wire.description.trim().length >= 20;
        }
        return true;
    },
    goTo(s) {
        if (s <= Math.max(...this.visited) + 1 && s >= 1 && s <= this.maxStep) {
            this.step = s;
            if (!this.visited.includes(s)) this.visited.push(s);
            this.$nextTick(() => {
                this.$refs['panel' + s]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    },
    next() {
        if (this.step < this.maxStep && this.canNext) {
            this.goTo(this.step + 1);
        }
    },
    prev() {
        if (this.step > 1) {
            this.goTo(this.step - 1);
        }
    }
}">
    <form wire:submit="save">

        {{-- Step Indicator --}}
        <nav class="dash-wizard-steps" aria-label="Formular-Fortschritt" role="navigation">
            {{-- Step 1 --}}
            <button type="button" @click="goTo(1)"
                    class="dash-wizard-step"
                    :class="{
                        'dash-wizard-step--active': step === 1,
                        'dash-wizard-step--done': step > 1
                    }"
                    :aria-current="step === 1 ? 'step' : false"
                    aria-label="Schritt 1: Stelleninformationen">
                <span class="dash-wizard-step-circle">
                    <template x-if="step > 1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                    <template x-if="step <= 1">
                        <span>1</span>
                    </template>
                </span>
                <span class="dash-wizard-step-label">Stelleninfo</span>
            </button>

            <div class="dash-wizard-step-connector" :class="step > 1 && 'dash-wizard-step-connector--done'" aria-hidden="true"></div>

            {{-- Step 2 --}}
            <button type="button" @click="goTo(2)"
                    class="dash-wizard-step"
                    :class="{
                        'dash-wizard-step--active': step === 2,
                        'dash-wizard-step--done': step > 2
                    }"
                    :aria-current="step === 2 ? 'step' : false"
                    aria-label="Schritt 2: Beschreibung">
                <span class="dash-wizard-step-circle">
                    <template x-if="step > 2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                    <template x-if="step <= 2">
                        <span>2</span>
                    </template>
                </span>
                <span class="dash-wizard-step-label">Beschreibung</span>
            </button>

            <div class="dash-wizard-step-connector" :class="step > 2 && 'dash-wizard-step-connector--done'" aria-hidden="true"></div>

            {{-- Step 3 --}}
            <button type="button" @click="goTo(3)"
                    class="dash-wizard-step"
                    :class="{
                        'dash-wizard-step--active': step === 3,
                        'dash-wizard-step--done': false
                    }"
                    :aria-current="step === 3 ? 'step' : false"
                    aria-label="Schritt 3: Gehalt & Veröffentlichen">
                <span class="dash-wizard-step-circle">3</span>
                <span class="dash-wizard-step-label">Veröffentlichen</span>
            </button>
        </nav>

        {{-- ═══════════════════════════════════════════════
             STEP 1: Stelleninformationen
             ═══════════════════════════════════════════════ --}}
        <div x-ref="panel1" x-show="step === 1" x-transition:enter="dash-wizard-panel" class="space-y-6">
            <div class="dash-card dash-card-padded">
                <h2 class="text-base font-semibold mb-4 flex items-center gap-2" style="color: var(--dash-text-primary)">
                    <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Stelleninformationen
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Titel --}}
                    <div class="sm:col-span-2">
                        <label for="title" class="dash-label dash-label-required">Stellentitel</label>
                        <input type="text" id="title" wire:model.blur="title"
                               class="dash-input w-full {{ $errors->has('title') ? 'dash-input-error' : '' }}"
                               placeholder="z.B. Malergeselle (m/w/d) gesucht">
                        @error('title') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- Beschäftigungsart --}}
                    <div>
                        <label for="employment_type" class="dash-label dash-label-required">Beschäftigungsart</label>
                        <select id="employment_type" wire:model="employment_type"
                                class="dash-select w-full {{ $errors->has('employment_type') ? 'dash-input-error' : '' }}">
                            @foreach($employmentTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('employment_type') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- Standort --}}
                    <div>
                        <label for="location" class="dash-label">Standort</label>
                        <input type="text" id="location" wire:model.blur="location"
                               class="dash-input w-full"
                               placeholder="z.B. Berlin-Kreuzberg">
                        <p class="dash-input-hint">Leer = Firmenadresse wird verwendet</p>
                    </div>

                    {{-- Stadt (Autocomplete) --}}
                    <div class="sm:col-span-2" x-data="{ open: false }" @click.outside="open = false">
                        <label for="citySearch" class="dash-label">Stadt</label>
                        <div class="relative">
                            <input type="text" id="citySearch"
                                   wire:model.live.debounce.300ms="citySearch"
                                   @focus="open = true"
                                   @input="open = true"
                                   class="dash-input w-full"
                                   placeholder="PLZ oder Stadtname eingeben..."
                                   autocomplete="off"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="{{ count($citySuggestions) > 0 ? 'true' : 'false' }}"
                                   aria-controls="city-suggestions">

                            @if($city_id)
                                <button type="button" wire:click="clearCity"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md transition-colors"
                                        style="color: var(--dash-text-muted)"
                                        title="Stadt entfernen"
                                        aria-label="Ausgewählte Stadt entfernen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            @endif

                            {{-- Suggestions Dropdown --}}
                            @if(count($citySuggestions) > 0)
                                <ul x-show="open" x-cloak x-transition
                                    id="city-suggestions"
                                    role="listbox"
                                    class="absolute z-20 w-full mt-1 rounded-lg border shadow-lg overflow-hidden"
                                    style="background: var(--dash-surface, white); border-color: var(--dash-border);">
                                    @foreach($citySuggestions as $city)
                                        <li role="option">
                                            <button type="button"
                                                    wire:click="selectCity({{ $city['id'] }})"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2.5 text-sm transition-colors"
                                                    style="color: var(--dash-text-primary)"
                                                    onmouseover="this.style.background='rgba(0,0,0,0.04)'"
                                                    onmouseout="this.style.background='transparent'">
                                                {{ $city['zipcode'] }} {{ $city['name'] }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 1 Navigation --}}
            <div class="dash-wizard-footer">
                <a href="{{ route('portal.owner.jobs.index') }}" class="dash-btn dash-btn-secondary">
                    Abbrechen
                </a>
                <button type="button" @click="next()" class="dash-btn dash-btn-primary"
                        :disabled="!canNext"
                        :class="!canNext && 'opacity-50 cursor-not-allowed'">
                    Weiter: Beschreibung
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             STEP 2: Beschreibung
             ═══════════════════════════════════════════════ --}}
        <div x-ref="panel2" x-show="step === 2" x-transition:enter="dash-wizard-panel" class="space-y-6">
            <div class="dash-card dash-card-padded">
                <h2 class="text-base font-semibold mb-4 flex items-center gap-2" style="color: var(--dash-text-primary)">
                    <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Beschreibung
                </h2>

                <div class="space-y-4">
                    {{-- Stellenbeschreibung --}}
                    <div x-data="{ charCount: $wire.description.length }">
                        <label for="description" class="dash-label dash-label-required">Stellenbeschreibung</label>
                        <textarea id="description" wire:model.blur="description"
                                  rows="6"
                                  maxlength="5000"
                                  x-on:input="charCount = $event.target.value.length"
                                  class="dash-textarea w-full {{ $errors->has('description') ? 'dash-input-error' : '' }}"
                                  placeholder="Beschreiben Sie die Stelle, typische Aufgaben und was den Job besonders macht..."
                                  aria-describedby="description-hint"></textarea>
                        <div class="flex justify-between mt-1">
                            @error('description') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                            <span id="description-hint" class="dash-input-hint ml-auto" x-text="charCount + ' / 5.000'" aria-live="polite"></span>
                        </div>
                    </div>

                    {{-- Anforderungen --}}
                    <div>
                        <label for="requirements" class="dash-label">Anforderungen</label>
                        <textarea id="requirements" wire:model.blur="requirements"
                                  rows="4"
                                  maxlength="3000"
                                  class="dash-textarea w-full"
                                  placeholder="z.B. Gesellenbrief, Führerschein Klasse B, Berufserfahrung..."></textarea>
                        @error('requirements') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- Benefits --}}
                    <div>
                        <label for="benefits" class="dash-label">Was wir bieten</label>
                        <textarea id="benefits" wire:model.blur="benefits"
                                  rows="4"
                                  maxlength="3000"
                                  class="dash-textarea w-full"
                                  placeholder="z.B. Übertarifliche Bezahlung, Firmenwagen, 30 Tage Urlaub..."></textarea>
                        @error('benefits') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Step 2 Navigation --}}
            <div class="dash-wizard-footer">
                <button type="button" @click="prev()" class="dash-btn dash-btn-secondary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Zurück
                </button>
                <button type="button" @click="next()" class="dash-btn dash-btn-primary"
                        :disabled="!canNext"
                        :class="!canNext && 'opacity-50 cursor-not-allowed'">
                    Weiter: Gehalt & Veröffentlichen
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             STEP 3: Gehalt, Fristen & Veröffentlichen
             ═══════════════════════════════════════════════ --}}
        <div x-ref="panel3" x-show="step === 3" x-transition:enter="dash-wizard-panel" class="space-y-6">
            <div class="dash-card dash-card-padded">
                <h2 class="text-base font-semibold mb-4 flex items-center gap-2" style="color: var(--dash-text-primary)">
                    <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Gehalt & Fristen
                </h2>

                {{-- Salary Toggle --}}
                <div class="mb-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="showSalary" class="dash-checkbox">
                        <span class="text-sm" style="color: var(--dash-text-secondary)">Gehaltsangabe hinzufügen</span>
                    </label>
                    <p class="dash-input-hint mt-1.5 ml-8">Stellen mit Gehaltsangabe erhalten bis zu 30% mehr Bewerbungen.</p>
                </div>

                @if($showSalary)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4" wire:transition>
                        <div>
                            <label for="salary_min" class="dash-label">Gehalt von (€)</label>
                            <input type="number" id="salary_min" wire:model.blur="salary_min"
                                   class="dash-input w-full"
                                   min="0" step="100"
                                   placeholder="z.B. 2500">
                            @error('salary_min') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="salary_max" class="dash-label">Gehalt bis (€)</label>
                            <input type="number" id="salary_max" wire:model.blur="salary_max"
                                   class="dash-input w-full"
                                   min="0" step="100"
                                   placeholder="z.B. 3500">
                            @error('salary_max') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="salary_type" class="dash-label">Bezahlung</label>
                            <select id="salary_type" wire:model="salary_type" class="dash-select w-full">
                                @foreach($salaryTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                {{-- Bewerbungsfrist --}}
                <div class="max-w-xs">
                    <label for="application_deadline" class="dash-label">Bewerbungsfrist</label>
                    <input type="date" id="application_deadline" wire:model.blur="application_deadline"
                           class="dash-input w-full"
                           min="{{ now()->addDay()->format('Y-m-d') }}">
                    <p class="dash-input-hint">Optional — die Anzeige läuft automatisch nach 30 Tagen ab.</p>
                    @error('application_deadline') <p class="dash-input-error-msg">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Summary Preview --}}
            <div class="dash-card dash-card-padded" style="background: rgba(var(--portal-primary-rgb, 59 130 246), 0.03); border: 1px solid rgba(var(--portal-primary-rgb, 59 130 246), 0.1);">
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2" style="color: var(--dash-text-primary)">
                    <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Vorschau
                </h3>
                <div class="space-y-2 text-sm" style="color: var(--dash-text-secondary)">
                    <div class="flex items-center gap-2">
                        <span class="font-medium" style="color: var(--dash-text-primary)" x-text="$wire.title || 'Kein Titel'"></span>
                        <span class="dash-badge" style="background: var(--portal-primary); color: white; font-size: 0.6875rem;"
                              x-text="$wire.employment_type === 'vollzeit' ? 'Vollzeit' : $wire.employment_type === 'teilzeit' ? 'Teilzeit' : $wire.employment_type === 'minijob' ? 'Minijob' : $wire.employment_type === 'ausbildung' ? 'Ausbildung' : 'Praktikum'"></span>
                    </div>
                    <template x-if="$wire.location">
                        <div class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="$wire.location"></span>
                        </div>
                    </template>
                    <div class="flex items-center gap-1" style="color: var(--dash-text-muted)">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>30 Tage sichtbar nach Veröffentlichung</span>
                    </div>
                </div>
            </div>

            {{-- Step 3 Navigation --}}
            <div class="dash-wizard-footer">
                <button type="button" @click="prev()" class="dash-btn dash-btn-secondary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Zurück
                </button>
                <button type="submit" class="dash-btn dash-btn-primary dash-btn-lg" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $isEdit ? 'Änderungen speichern' : 'Stelle veröffentlichen' }}
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Wird gespeichert...
                    </span>
                </button>
            </div>

            <p class="dash-wizard-footer-hint">
                Die Stellenanzeige wird sofort veröffentlicht und ist 30 Tage aktiv.
            </p>
        </div>

    </form>
</div>
