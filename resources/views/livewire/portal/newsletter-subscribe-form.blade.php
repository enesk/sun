<div>
    {{-- Skripte unbedingt, auch wenn das Widget erst nach einem Livewire-Umlauf erscheint: der Stack 'scripts' wird nur beim Rendern des Layouts geleert (#53). --}}
    <x-turnstile-scripts action="contact" />

    @if($submitted)
        <div class="flex items-center gap-3 text-white" role="status">
            <svg class="w-6 h-6 text-green-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium">{{ __('portal.newsletter.success') }}</p>
        </div>
    @else
        <form wire:submit="subscribe" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto" aria-label="{{ __('portal.newsletter.form_label') }}">
            <label for="footer-newsletter-email" class="sr-only">{{ __('portal.newsletter.email_label') }}</label>
            <input
                wire:model="email"
                type="email"
                id="footer-newsletter-email"
                required
                placeholder="{{ __('portal.newsletter.email_placeholder') }}"
                class="footer-newsletter__input w-full sm:w-[320px]"
                autocomplete="email"
            >
            <button type="submit" class="footer-newsletter__submit ripple" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('portal.newsletter.submit') }}</span>
                <span wire:loading>…</span>
            </button>
            {{-- Honigtopf und Ausfuellzeit (#22) --}}
            <x-antispam-fields wire />
        </form>
        {{-- Turnstile (#22) steht ausserhalb des Formulars: die Zeile ist ein
             Flex-Container, der Kasten wuerde darin zur dritten Spalte. Die
             Bindung laeuft ueber die Livewire-Komponente, nicht ueber das
             Formular. Modus non_interactive, gate="false" — im Fuss darf der
             Knopf nicht warten, bis Cloudflare geantwortet hat. --}}
        <x-turnstile action="contact" wire="turnstileToken" field="turnstileToken" size="flexible" gate="false" class="mt-2" />
        @error('email')
            <p class="text-red-200 text-xs mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>
