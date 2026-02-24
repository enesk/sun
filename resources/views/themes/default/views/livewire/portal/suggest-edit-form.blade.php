<div>
    @if(!$submitted)
        <form wire:submit="submit" class="space-y-5">
            {{-- Honeypot (unsichtbar für echte User) --}}
            <div class="hidden" aria-hidden="true">
                <label for="website_url_hp_form">Website</label>
                <input type="text" id="website_url_hp_form" wire:model="website_url" tabindex="-1" autocomplete="off">
            </div>

            {{-- Was ändern? --}}
            <div>
                <label for="suggest-field-form" class="label-portal mb-1.5">Was möchten Sie ändern? *</label>
                <select id="suggest-field-form"
                        wire:model="field"
                        class="input-portal w-full"
                        required>
                    <option value="">— Bitte wählen —</option>
                    <option value="address">Adresse / Standort</option>
                    <option value="phone">Telefonnummer</option>
                    <option value="hours">Öffnungszeiten</option>
                    <option value="description">Beschreibung / Name</option>
                    <option value="other">Sonstiges</option>
                </select>
                @error('field')
                    <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Vorschlag --}}
            <div>
                <label for="suggest-value-form" class="label-portal mb-1.5">Ihr Vorschlag *</label>
                <textarea id="suggest-value-form"
                          wire:model="suggestedValue"
                          class="input-portal w-full min-h-[120px] resize-y"
                          placeholder="z.B. Die korrekte Adresse ist Musterstraße 15, 10115 Berlin"
                          required
                          maxlength="2000"></textarea>
                @error('suggestedValue')
                    <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Optional: Begründung --}}
            <div>
                <label for="suggest-reason-form" class="label-portal mb-1.5">Begründung <span class="text-base-content/40">(optional)</span></label>
                <input type="text"
                       id="suggest-reason-form"
                       wire:model="reason"
                       class="input-portal w-full"
                       placeholder="z.B. Firma ist umgezogen"
                       maxlength="500">
            </div>

            {{-- Optional: Name + E-Mail --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="suggest-name-form" class="label-portal mb-1.5">Ihr Name <span class="text-base-content/40">(optional)</span></label>
                    <input type="text"
                           id="suggest-name-form"
                           wire:model="reporterName"
                           class="input-portal w-full"
                           placeholder="z.B. Maria S."
                           maxlength="100">
                </div>
                <div>
                    <label for="suggest-email-form" class="label-portal mb-1.5">E-Mail <span class="text-base-content/40">(optional)</span></label>
                    <input type="email"
                           id="suggest-email-form"
                           wire:model="reporterEmail"
                           class="input-portal w-full"
                           placeholder="maria@beispiel.de"
                           maxlength="255">
                    @error('reporterEmail')
                        <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Submit --}}
            <div class="pt-2">
                <button type="submit"
                        class="btn-portal w-full sm:w-auto px-8 py-3 rounded-xl text-sm font-semibold ripple inline-flex items-center justify-center gap-2"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-wait">
                    <svg wire:loading class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove>Vorschlag senden</span>
                    <span wire:loading>Wird gesendet…</span>
                </button>
            </div>
        </form>
    @else
        {{-- Success State --}}
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-green-50 flex items-center justify-center">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-base-content mb-2">Vielen Dank für Ihren Vorschlag!</h3>
            <p class="text-base-content/60 mb-8">Unser Team wird Ihren Änderungsvorschlag prüfen und gegebenenfalls übernehmen.</p>

            {{-- Claim-CTA nach Erfolg --}}
            @if(!$company->user_id)
                <div class="suggest-edit-page-claim-cta p-6 rounded-2xl text-left max-w-md mx-auto"
                     style="background: linear-gradient(135deg, rgba(var(--portal-primary-rgb), 0.04), rgba(var(--portal-primary-rgb), 0.08)); border: 2px solid rgba(var(--portal-primary-rgb), 0.15);">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                             style="background: rgba(var(--portal-primary-rgb), 0.12);">
                            <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-base-content text-base leading-tight">Ist das Ihr Unternehmen?</h4>
                            <p class="text-sm text-base-content/60 mt-1">Übernehmen Sie Ihren Eintrag — kostenlos. Aktualisieren Sie Ihre Daten selbst und antworten Sie auf Bewertungen.</p>
                            <a href="{{ route('register') }}"
                               class="btn-portal w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium ripple mt-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Jetzt kostenlos übernehmen
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <a href="{{ $company->portal_url }}"
               class="inline-flex items-center gap-2 mt-6 text-sm font-medium transition-colors hover:underline"
               style="color: var(--portal-primary)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück zum Firmenprofil
            </a>
        </div>
    @endif
</div>
