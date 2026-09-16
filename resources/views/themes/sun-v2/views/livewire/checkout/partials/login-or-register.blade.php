{{--
    Konto im Checkout fuer Gaeste, Theme sun-v2. Anmelden oder Registrieren passiert beim
    Bestellen (CheckoutForm::handleLoginOrRegistration); der einzige Primaer-Button ist "Bestellen".
--}}
@guest
  <section class="card p-5 md:p-6" aria-labelledby="checkout-account">
    <h2 id="checkout-account" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.checkout.account.title') }}</h2>
    <p class="mt-1 text-base text-zinc-500">{{ ! empty($intro) ? $intro : __('portal.owner.checkout.account.text') }}</p>

    <div class="mt-4">
      @if($otpEnabled)
        @include('livewire.checkout.partials.one-time-password')
      @else
        @include('livewire.checkout.partials.traditional-login-or-register')
      @endif
    </div>

    @if(empty($email))
      <x-auth.social-login>
        <x-slot name="before">
          <p class="my-4 flex items-center gap-3 text-sm text-zinc-500 before:h-px before:flex-1 before:bg-zinc-200 after:h-px after:flex-1 after:bg-zinc-200">{{ __('portal.owner.checkout.account.or') }}</p>
        </x-slot>
      </x-auth.social-login>
    @endif
  </section>
@endguest
