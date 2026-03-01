<div class="flex flex-col gap-3">
    <div>
        <label for="email" class="checkout-label">E-Mail-Adresse</label>
        <input type="email" class="checkout-input @error('email') checkout-input-error @enderror" name="email" required id="email" wire:model.blur="email" value="{{ old('email') }}" placeholder="ihre@email.de" />
    </div>

    @error('email')
    <span class="text-xs text-red-500" role="alert">
        {{ $message }}
    </span>
    @enderror


    @if(!empty($email))
        <div>
            <label for="password" class="checkout-label">Passwort</label>
            <input type="password" class="checkout-input @error('password') checkout-input-error @enderror" name="password" required id="password" wire:model="password" />
        </div>

        @error('password')
        <span class="text-xs text-red-500 ms-1" role="alert">
            {{ $message }}
        </span>
        @enderror
    @endif

    @if ($userExists)
        <div class="my-2 ms-1 text-xs" style="color: #94A3B8;">Sie sind bereits registriert. Bitte geben Sie Ihr Passwort ein.</div>
    @elseif(!empty($email))
        <div class="my-2 ms-1 text-xs" style="color: #94A3B8;">Wählen Sie ein Passwort für Ihr neues Konto.</div>
    @endif

    @if($userExists)
        @if (Route::has('password.request'))
            <div class="text-end">
                <a class="text-xs" style="color: var(--portal-primary, #3B82F6);" href="{{ route('password.request') }}">
                    Passwort vergessen?
                </a>
            </div>
        @endif
    @endif


    @if(!$userExists || empty($email))

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
</div>
