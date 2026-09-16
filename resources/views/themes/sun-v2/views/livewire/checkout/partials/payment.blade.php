{{-- Zahlungsart im Checkout, Theme sun-v2. Anbieter-Skripte (z. B. Paddle) wie im Default-Partial. --}}
<section class="card p-5 md:p-6" aria-labelledby="checkout-payment">
  <h2 id="checkout-payment" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.checkout.payment.title') }}</h2>

  <div class="mt-4 space-y-3" role="radiogroup" aria-labelledby="checkout-payment">
    @foreach($paymentProviders as $paymentProvider)
      <label class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 cursor-pointer transition-colors hover:border-zinc-300 has-checked:border-brand has-checked:bg-brand-50">
        <input type="radio" name="paymentProvider" value="{{ $paymentProvider->getSlug() }}" wire:model="paymentProvider" class="mt-1 size-5 shrink-0 accent-brand focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
        <span class="min-w-0 flex-1">
          <span class="flex items-center justify-between gap-3">
            <span class="text-base font-semibold text-zinc-900">{{ $paymentProvider->getName() }}</span>
            <img src="{{ asset('images/payment-providers/'.$paymentProvider->getSlug().'.png') }}" alt="" class="h-5 w-auto grayscale" loading="lazy">
          </span>
          <span class="mt-1 block text-sm text-zinc-500">
            @if ($paymentProvider->isRedirectProvider())
              {{ __('portal.owner.checkout.payment.redirect', ['anbieter' => $paymentProvider->getName()]) }}
            @elseif ($paymentProvider->isOverlayProvider())
              {{ __('portal.owner.checkout.payment.overlay') }}
            @else
              {{ __('portal.owner.checkout.payment.offline') }}
            @endif
          </span>
        </span>
      </label>
    @endforeach
  </div>

  @foreach($paymentProviders as $paymentProvider)
    @includeIf('payment-providers.'.$paymentProvider->getSlug())
  @endforeach
</section>
