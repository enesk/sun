<div>
    <form action="" method="post" wire:submit="checkout">
        @csrf

        <div class="checkout-content__inner">

            <div class="checkout-col-form">
                @include('livewire.checkout.partials.login-or-register')
                @include('livewire.checkout.partials.payment')
            </div>

            <div class="checkout-col-details">
                @include('livewire.checkout.partials.plan-details', ['isTrialSkipped' => true])
            </div>

        </div>

        {{-- CTA Submit Button — outside sticky column so it never disappears --}}
        <div class="checkout-cta-wrapper">
            <button type="submit" class="checkout-cta" wire:loading.attr="disabled">
                <span wire:loading.remove>Jetzt abonnieren</span>
                <span wire:loading>
                    <svg class="animate-spin inline-block h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Wird verarbeitet…
                </span>
            </button>

            <p class="checkout-cta-legal">
                Mit dem Klick akzeptieren Sie unsere
                <a href="{{ route('terms-of-service') }}" target="_blank">AGB</a>
                und
                <a href="{{ route('privacy-policy') }}" target="_blank">Datenschutzerklärung</a>.
            </p>
        </div>

    </form>
</div>
