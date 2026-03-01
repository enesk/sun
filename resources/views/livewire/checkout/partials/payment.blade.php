<h2 class="checkout-card__title">Zahlungsmethode</h2>

<div class="checkout-payment-card">

    @foreach($paymentProviders as $paymentProvider)
        <div class="checkout-payment-option">
            <label class="cursor-pointer flex justify-between items-center w-full">
                <span class="flex flex-col gap-1.5 me-2">
                    <span class="flex items-center gap-2 font-medium" style="color: var(--portal-primary-dark, #1E3A5F);">
                        <span>{{ $paymentProvider->getName() }}</span>
                        <img src="{{ asset('images/payment-providers/' . $paymentProvider->getSlug() . '.png') }}" alt="{{ $paymentProvider->getName() }}" class="h-5 grayscale" />
                    </span>
                    <span class="text-sm" style="color: #64748B;">
                        @if ($paymentProvider->isRedirectProvider())
                            Sie werden zur sicheren Zahlungsseite weitergeleitet.
                        @elseif ($paymentProvider->isOverlayProvider())
                            Ihre Zahlungsdaten werden in einem sicheren Overlay erfasst.
                        @else
                            Sie erhalten eine E-Mail mit Zahlungsanweisungen.
                        @endif
                    </span>
                </span>
                <input type="radio"
                       value="{{ $paymentProvider->getSlug() }}"
                       class="radio checked:bg-white"
                       style="--radio-color: var(--portal-primary, #3B82F6);"
                       name="paymentProvider"
                       wire:model="paymentProvider"
                />
            </label>
        </div>
    @endforeach

    @foreach($paymentProviders as $paymentProvider)
        @includeIf('payment-providers.' . $paymentProvider->getSlug())
    @endforeach

</div>
