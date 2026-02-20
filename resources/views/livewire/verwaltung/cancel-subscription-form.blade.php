<div>
    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="card-portal p-6 max-w-2xl">
        {{-- Warning Banner --}}
        <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200">
            <div class="flex gap-3">
                <svg class="w-6 h-6 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-800 mb-1">Achtung: Kündigung</h3>
                    <p class="text-sm text-amber-700">
                        Das Abonnement wird zum Ende der aktuellen Abrechnungsperiode gekündigt.
                        Bis dahin können Sie alle Premium-Funktionen weiterhin nutzen.
                    </p>
                </div>
            </div>
        </div>

        <form wire:submit="cancel">
            {{-- Reason Selection --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-base-content mb-3">
                    Warum möchten Sie kündigen? <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2">
                    @foreach($reasons as $value => $label)
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors
                                      {{ $reason === $value ? 'border-[color:var(--portal-primary)] bg-[color:var(--portal-primary)]/5' : 'border-base-200 hover:border-base-300' }}">
                            <input type="radio"
                                   wire:model="reason"
                                   value="{{ $value }}"
                                   class="w-4 h-4 text-[color:var(--portal-primary)]">
                            <span class="text-sm text-base-content">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('reason')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Additional Info --}}
            <div class="mb-6">
                <label for="additionalInfo" class="block text-sm font-medium text-base-content mb-2">
                    Möchten Sie uns mehr mitteilen? (optional)
                </label>
                <textarea id="additionalInfo"
                          wire:model="additionalInfo"
                          rows="3"
                          maxlength="1000"
                          placeholder="Ihr Feedback hilft uns, unser Angebot zu verbessern..."
                          class="input-portal w-full text-sm resize-none"></textarea>
                @error('additionalInfo')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-4 border-t border-base-200">
                <a href="{{ route('verwaltung.subscriptions.index') }}"
                   class="btn-portal btn-portal-ghost text-sm text-center">
                    Abbrechen — nicht kündigen
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-portal text-sm bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <span wire:loading.remove wire:target="cancel">Abonnement kündigen</span>
                    <span wire:loading wire:target="cancel">Wird gekündigt...</span>
                </button>
            </div>
        </form>
    </div>
</div>
