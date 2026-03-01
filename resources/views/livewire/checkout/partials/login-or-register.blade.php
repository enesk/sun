@guest()
    <div class="mb-4">

        <h2 class="checkout-card__title">Ihre Daten</h2>

        <div class="checkout-card relative">

            @if (!empty($intro))
                <div class="mb-4 text-sm" style="color: #64748B;">
                    {{ $intro }}
                </div>
            @endif

            <div class="absolute top-0 right-0 p-2">
                    <span wire:loading>
                        <span class="loading loading-spinner loading-xs"></span>
                    </span>
            </div>

            @if($otpEnabled)
                @include('livewire.checkout.partials.one-time-password')
            @else
                @include('livewire.checkout.partials.traditional-login-or-register')
            @endif

            @if(empty($email))
                <x-auth.social-login>
                    <x-slot name="before">
                        <div class="checkout-divider">oder</div>
                    </x-slot>
                </x-auth.social-login>
            @endif

            {{-- Inline CTA + Terms --}}
            <button
                type="submit"
                class="checkout-cta"
                wire:loading.attr="disabled"
                @if(!$this->isCheckoutButtonEnabled()) disabled @endif
            >
                @if(isset($plan) && $plan->has_trial)
                    Kostenlos testen — 30 Tage Premium
                @else
                    Jetzt abonnieren
                @endif
                <span wire:loading>
                    <span class="loading loading-ring loading-xs"></span>
                </span>
            </button>
            <p class="checkout-terms">
                Mit der Fortsetzung stimmen Sie unseren
                <a target="_blank" href="{{ route('terms-of-service') }}">Nutzungsbedingungen</a> und der
                <a target="_blank" href="{{ route('privacy-policy') }}">Datenschutzerklärung</a> zu.
            </p>

        </div>
    </div>

@endguest
