{{-- OTP Login Flow --}}
@if(!$showOtpForm)
    <div class="flex flex-col gap-3">
        <div>
            <label for="email" class="checkout-label">E-Mail-Adresse</label>
            <input type="email" class="checkout-input @error('email') checkout-input-error @enderror" name="email" required id="email" wire:model.live.debounce.500ms="email" value="{{ old('email') }}" placeholder="ihre@email.de" />
        </div>

        @error('email')
        <span class="text-xs text-red-500" role="alert">
            {{ $message }}
        </span>
        @enderror

        {{-- Name-Feld nur für neue User --}}
        @if(!$userExists)
            <div>
                <label for="name" class="checkout-label">Ihr Name</label>
                <input type="text" class="checkout-input @error('name') checkout-input-error @enderror" name="name" required id="name" wire:model="name" value="{{ old('name') }}" />
            </div>

            @error('name')
            <span class="text-xs text-red-500" role="alert">
                {{ $message }}
            </span>
            @enderror
        @endif

        @include('livewire.auth.partials.recaptcha')

        {{-- OTP senden --}}
        <div class="mt-2">
            <button
                type="button"
                class="checkout-cta"
                wire:click="sendOtpCode"
                wire:loading.attr="disabled"
            >
                {{ $userExists ? 'Login-Code senden' : 'Konto erstellen & Code senden' }}
                <span wire:loading wire:target="sendOtpCode">
                    <span class="loading loading-ring loading-xs"></span>
                </span>
            </button>
        </div>

        @if($userExists)
            <div class="my-1 ms-1 text-xs" style="color: #94A3B8;">Klicken Sie, um ein Einmalpasswort per E-Mail zu erhalten.</div>
        @else
            <div class="my-1 ms-1 text-xs" style="color: #94A3B8;">Geben Sie Ihren Namen ein und klicken Sie, um Ihr Konto zu erstellen und einen Login-Code zu erhalten.</div>
        @endif
    </div>

@elseif($showOtpForm)
    <div class="flex flex-col gap-3">
        <div>
            <label for="email" class="checkout-label">E-Mail-Adresse</label>
            <input type="email" class="checkout-input @error('email') checkout-input-error @enderror" name="email" required id="email" wire:model.live.debounce.500ms="email" value="{{ old('email') }}" />
        </div>

        @error('email')
        <span class="text-xs text-red-500" role="alert">
            {{ $message }}
        </span>
        @enderror

        {{-- OTP Eingabe --}}
        <div>
            <label for="oneTimePassword" class="checkout-label">Einmalpasswort</label>
            <input type="text" class="checkout-input @error('oneTimePassword') checkout-input-error @enderror" name="oneTimePassword" required id="oneTimePassword" wire:model.live="oneTimePassword" placeholder="Code eingeben" />
        </div>

        @error('oneTimePassword')
        <span class="text-xs text-red-500" role="alert">
            {{ $message }}
        </span>
        @enderror

        <div class="my-1 ms-1 text-xs" style="color: #94A3B8;">Geben Sie das Einmalpasswort ein, das an Ihre E-Mail-Adresse gesendet wurde.</div>

        {{-- Code erneut senden --}}
        <div class="mt-1 text-end" x-data="{ resendText: 'Code erneut senden', isResending: false }">
            <button
                type="button"
                @click="
                    if (!isResending) {
                        isResending = true;
                        resendText = 'Code gesendet';
                        $wire.resendOtpCode();
                        setTimeout(() => {
                            resendText = 'Code erneut senden';
                            isResending = false;
                        }, 2000);
                    }
                "
                class="text-xs cursor-pointer bg-transparent border-0 p-0 m-0 text-left underline"
                style="color: var(--portal-primary, #3B82F6);"
                :class="{ 'underline': !isResending }"
                x-text="resendText"
            ></button>
        </div>
    </div>
@endif
