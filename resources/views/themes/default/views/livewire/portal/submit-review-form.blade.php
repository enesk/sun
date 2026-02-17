<div>
    {{-- CTA Button --}}
    @if(!$submitted)
        <button
            wire:click="toggleForm"
            class="btn-portal-outline inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium ripple"
            aria-expanded="{{ $showForm ? 'true' : 'false' }}"
            aria-controls="review-form-{{ $company->id }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Bewertung schreiben
        </button>
    @endif

    {{-- Inline Form (Expansion) --}}
    @if($showForm && !$submitted)
        <div id="review-form-{{ $company->id }}"
             class="mt-4 company-review-card"
             x-data="{
                 hoverRating: 0,
                 selectedRating: @entangle('rating'),
                 bodyLength: 0
             }"
             x-init="bodyLength = $refs.bodyField?.value?.length || 0">

            <h3 class="text-base font-semibold text-[#0F172A] mb-4">
                Bewertung für {{ $company->name }} schreiben
            </h3>

            {{-- Sterne-Rating (interaktiv) --}}
            <div class="mb-5">
                <label class="label-portal mb-2">Wie bewerten Sie dieses Unternehmen? *</label>
                <div class="flex items-center gap-1" role="radiogroup" aria-label="Bewertung in Sternen">
                    @for($i = 1; $i <= 5; $i++)
                        {{-- Halber Stern (linke Hälfte) --}}
                        <button type="button"
                                wire:click="setRating({{ $i - 0.5 }})"
                                @mouseenter="hoverRating = {{ $i - 0.5 }}"
                                @mouseleave="hoverRating = 0"
                                class="relative w-6 h-12 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-portal-primary rounded-l overflow-hidden"
                                role="radio"
                                :aria-checked="selectedRating === {{ $i - 0.5 }}"
                                aria-label="{{ $i - 0.5 }} {{ $i - 0.5 === 1.0 ? 'Stern' : 'Sterne' }}"
                                title="{{ $i - 0.5 }} {{ $i - 0.5 === 1.0 ? 'Stern' : 'Sterne' }}">
                            <svg class="w-12 h-12 absolute right-0 top-0 transition-colors duration-150"
                                 :class="(hoverRating || selectedRating) >= {{ $i - 0.5 }} ? 'text-portal-accent' : 'text-gray-300'"
                                 fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                        {{-- Ganzer Stern (rechte Hälfte) --}}
                        <button type="button"
                                wire:click="setRating({{ $i }})"
                                @mouseenter="hoverRating = {{ $i }}"
                                @mouseleave="hoverRating = 0"
                                class="relative w-6 h-12 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-portal-primary rounded-r overflow-hidden"
                                role="radio"
                                :aria-checked="selectedRating === {{ $i }}"
                                aria-label="{{ $i }} {{ $i === 1 ? 'Stern' : 'Sterne' }}"
                                title="{{ $i }} {{ $i === 1 ? 'Stern' : 'Sterne' }}">
                            <svg class="w-12 h-12 absolute left-[-24px] top-0 transition-colors duration-150"
                                 :class="(hoverRating || selectedRating) >= {{ $i }} ? 'text-portal-accent' : 'text-gray-300'"
                                 fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor

                    {{-- Ausgewählte Bewertung als Text --}}
                    <span class="ml-3 text-sm font-medium text-[#64748B]"
                          x-show="selectedRating > 0"
                          x-text="(hoverRating || selectedRating).toFixed(1) + ' / 5'"
                          x-transition></span>
                </div>
                @error('rating')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Name (optional) --}}
            <div class="mb-4">
                <label for="review-author" class="label-portal">Ihr Name <span class="text-[#94A3B8] font-normal">(optional)</span></label>
                <input type="text"
                       id="review-author"
                       wire:model.blur="authorName"
                       class="input-portal"
                       placeholder="z.B. Maria S."
                       maxlength="100"
                       autocomplete="name">
                <p class="help-portal">Wird öffentlich angezeigt. Leer = „Anonym"</p>
                @error('authorName')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Titel (optional) --}}
            <div class="mb-4">
                <label for="review-title" class="label-portal">Titel <span class="text-[#94A3B8] font-normal">(optional)</span></label>
                <input type="text"
                       id="review-title"
                       wire:model.blur="title"
                       class="input-portal"
                       placeholder="z.B. Sehr zufrieden mit der Arbeit"
                       maxlength="150">
                @error('title')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Bewertungstext (optional) --}}
            <div class="mb-5">
                <label for="review-body" class="label-portal">Ihre Erfahrung <span class="text-[#94A3B8] font-normal">(optional)</span></label>
                <textarea
                    id="review-body"
                    wire:model.blur="body"
                    x-ref="bodyField"
                    @input="bodyLength = $el.value.length"
                    class="textarea-portal"
                    rows="4"
                    maxlength="2000"
                    placeholder="Beschreiben Sie Ihre Erfahrung mit diesem Unternehmen..."></textarea>
                <div class="flex justify-between items-center mt-1">
                    @error('body')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span class="text-xs text-[#94A3B8]"
                          :class="bodyLength > 1800 ? 'text-amber-500' : ''"
                          x-text="bodyLength + ' / 2.000'"></span>
                </div>
            </div>

            {{-- Hinweis --}}
            <p class="text-xs text-[#94A3B8] mb-4">
                <svg class="inline w-3.5 h-3.5 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Ihre Bewertung wird nach einer kurzen Prüfung veröffentlicht.
            </p>

            {{-- Aktionen --}}
            <div class="flex items-center gap-3">
                <button
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait"
                    class="btn-portal inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium ripple"
                    :disabled="selectedRating === 0">
                    <span wire:loading.remove wire:target="submit">Bewertung absenden</span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Wird gesendet…
                    </span>
                </button>
                <button
                    wire:click="toggleForm"
                    class="text-sm text-[#64748B] hover:text-[#0F172A] transition-colors px-3 py-2.5">
                    Abbrechen
                </button>
            </div>
        </div>
    @endif

    {{-- Erfolgs-Meldung --}}
    @if($submitted)
        <div class="mt-4 company-review-card border-green-200 bg-green-50/50">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-green-800 text-sm">Vielen Dank für Ihre Bewertung!</h4>
                    <p class="text-sm text-green-700 mt-1">Ihre Bewertung wird nach einer kurzen Prüfung veröffentlicht. Dies dauert in der Regel weniger als 24 Stunden.</p>
                </div>
            </div>
        </div>
    @endif
</div>
