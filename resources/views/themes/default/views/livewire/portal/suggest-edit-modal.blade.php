<div>
    {{-- Trigger Button --}}
    @if(!$submitted || $showModal)
        <button
            wire:click="openModal"
            class="suggest-edit-trigger group"
            type="button"
            aria-haspopup="dialog">
            <svg class="w-4 h-4 text-base-content/40 group-hover:text-portal-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Änderung vorschlagen</span>
        </button>
    @endif

    {{-- Success State (nach Submit, Modal geschlossen) --}}
    @if($submitted && !$showModal)
        <div class="suggest-edit-success" role="status">
            <div class="flex items-center gap-2 text-green-600 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm font-medium">Vielen Dank!</span>
            </div>
            <p class="text-xs text-base-content/60">Ihr Vorschlag wird geprüft.</p>
        </div>
    @endif

    {{-- Modal --}}
    @if($showModal)
        <div class="suggest-edit-backdrop"
             x-data
             x-on:keydown.escape.window="$wire.closeModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="suggest-edit-title">

            {{-- Overlay --}}
            <div class="suggest-edit-overlay" wire:click="closeModal" aria-hidden="true"></div>

            {{-- Modal Content --}}
            <div class="suggest-edit-modal">
                {{-- Header --}}
                <div class="suggest-edit-modal__header">
                    <h3 id="suggest-edit-title" class="text-lg font-semibold text-base-content">
                        Änderung für {{ $company->name }} vorschlagen
                    </h3>
                    <button wire:click="closeModal"
                            class="suggest-edit-modal__close"
                            type="button"
                            aria-label="Schließen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @if(!$submitted)
                    <form wire:submit="submit" class="suggest-edit-modal__body">
                        {{-- Honeypot (unsichtbar für echte User) --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="website_url_hp">Website</label>
                            <input type="text" id="website_url_hp" wire:model="website_url" tabindex="-1" autocomplete="off">
                        </div>

                        {{-- Was ändern? --}}
                        <div>
                            <label for="suggest-field" class="label-portal mb-1.5">Was möchten Sie ändern? *</label>
                            <select id="suggest-field"
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
                            <label for="suggest-value" class="label-portal mb-1.5">Ihr Vorschlag *</label>
                            <textarea id="suggest-value"
                                      wire:model="suggestedValue"
                                      class="input-portal w-full min-h-[100px] resize-y"
                                      placeholder="z.B. Die korrekte Adresse ist Musterstraße 15, 10115 Berlin"
                                      required
                                      maxlength="2000"></textarea>
                            @error('suggestedValue')
                                <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Optional: Begründung --}}
                        <div>
                            <label for="suggest-reason" class="label-portal mb-1.5">Begründung <span class="text-base-content/40">(optional)</span></label>
                            <input type="text"
                                   id="suggest-reason"
                                   wire:model="reason"
                                   class="input-portal w-full"
                                   placeholder="z.B. Firma ist umgezogen"
                                   maxlength="500">
                        </div>

                        {{-- Optional: Name + E-Mail --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="suggest-name" class="label-portal mb-1.5">Ihr Name <span class="text-base-content/40">(optional)</span></label>
                                <input type="text"
                                       id="suggest-name"
                                       wire:model="reporterName"
                                       class="input-portal w-full"
                                       placeholder="z.B. Maria S."
                                       maxlength="100">
                            </div>
                            <div>
                                <label for="suggest-email" class="label-portal mb-1.5">E-Mail <span class="text-base-content/40">(optional)</span></label>
                                <input type="email"
                                       id="suggest-email"
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
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button"
                                    wire:click="closeModal"
                                    class="btn-portal-outline px-4 py-2 rounded-lg text-sm font-medium">
                                Abbrechen
                            </button>
                            <button type="submit"
                                    class="btn-portal px-5 py-2 rounded-lg text-sm font-medium ripple inline-flex items-center gap-2"
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
                    {{-- Success State im Modal --}}
                    <div class="suggest-edit-modal__body text-center py-6">
                        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-base-content mb-1">Vielen Dank!</h4>
                        <p class="text-sm text-base-content/60 mb-6">Ihr Änderungsvorschlag wird von unserem Team geprüft.</p>

                        {{-- Claim-CTA nach Erfolg --}}
                        @if(!$company->user_id)
                            <div class="suggest-edit-claim-cta">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-base-content">Ist das Ihr Unternehmen?</span>
                                </div>
                                <p class="text-xs text-base-content/60 mb-3">Übernehmen Sie Ihren Eintrag — kostenlos. Aktualisieren Sie Ihre Daten selbst.</p>
                                <a href="{{ route('register') }}" class="btn-portal w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium ripple">
                                    Jetzt übernehmen
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                </a>
                            </div>
                        @endif

                        <button type="button"
                                wire:click="closeModal"
                                class="mt-4 text-sm text-base-content/50 hover:text-base-content transition-colors">
                            Schließen
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
