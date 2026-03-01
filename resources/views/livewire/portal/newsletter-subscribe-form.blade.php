<div>
    @if($submitted)
        <div class="flex items-center gap-3 text-white" role="status">
            <svg class="w-6 h-6 text-green-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium">Vielen Dank! Sie erhalten ab sofort unseren Newsletter.</p>
        </div>
    @else
        <form wire:submit="subscribe" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto" aria-label="Newsletter-Anmeldung">
            <label for="footer-newsletter-email" class="sr-only">E-Mail-Adresse</label>
            <input
                wire:model="email"
                type="email"
                id="footer-newsletter-email"
                required
                placeholder="Ihre E-Mail-Adresse"
                class="footer-newsletter__input w-full sm:w-[320px]"
                autocomplete="email"
            >
            <button type="submit" class="footer-newsletter__submit ripple" wire:loading.attr="disabled">
                <span wire:loading.remove>Abonnieren</span>
                <span wire:loading>…</span>
            </button>
        </form>
        @error('email')
            <p class="text-red-200 text-xs mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>
