<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-error">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if(session('success'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-success">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <div class="dash-card dash-card-padded max-w-2xl">
        {{-- Warning Banner --}}
        <div class="dash-flash dash-flash-warning mb-6" role="alert" style="border-radius: 0.75rem;">
            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
                <h3 class="font-semibold mb-1">Achtung: Kündigung</h3>
                <p class="text-sm" style="opacity: 0.85;">
                    Das Abonnement wird zum Ende der aktuellen Abrechnungsperiode gekündigt.
                    Bis dahin können Sie alle Premium-Funktionen weiterhin nutzen.
                </p>
            </div>
        </div>

        <form wire:submit="cancel">
            {{-- Reason Selection --}}
            <div class="mb-6">
                <label class="dash-label dash-label-required mb-3">
                    Warum möchten Sie kündigen?
                </label>
                <div class="space-y-2">
                    @foreach($reasons as $value => $label)
                        <label class="dash-checkbox-card {{ $reason === $value ? 'dash-checkbox-card-active' : '' }}"
                               style="border-radius: 0.75rem;">
                            <input type="radio"
                                   wire:model="reason"
                                   value="{{ $value }}"
                                   class="w-4 h-4"
                                   style="accent-color: var(--portal-primary, #3b82f6);">
                            <span class="text-sm" style="color: var(--dash-text-primary);">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('reason')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Additional Info --}}
            <div class="mb-6">
                <label for="additionalInfo" class="dash-label">
                    Möchten Sie uns mehr mitteilen? <span class="text-xs" style="color: var(--dash-text-muted);">(optional)</span>
                </label>
                <textarea id="additionalInfo"
                          wire:model="additionalInfo"
                          rows="3"
                          maxlength="1000"
                          placeholder="Ihr Feedback hilft uns, unser Angebot zu verbessern..."
                          class="dash-textarea"></textarea>
                @error('additionalInfo')
                    <p class="dash-input-error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-4" style="border-top: 1px solid var(--dash-border);">
                <a href="{{ route('verwaltung.subscriptions.index') }}"
                   class="dash-btn dash-btn-secondary text-center">
                    Abbrechen — nicht kündigen
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="dash-btn dash-btn-danger relative overflow-hidden"
                        wire:target="cancel">
                    <span wire:loading.class="opacity-0" wire:target="cancel" class="transition-opacity duration-200">Abonnement kündigen</span>
                    <span wire:loading wire:target="cancel" class="absolute inset-0 flex items-center justify-center">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
