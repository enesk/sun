{{-- Anmeldung per Einmalcode im Checkout (app.otp_login_enabled), Theme sun-v2. Ablauf wie im Default-Partial. --}}
<div class="space-y-4">
  <div>
    <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.account.email') }}</label>
    <input id="email" name="email" type="email" wire:model.live.debounce.500ms="email" @class(['input', 'border-red-500' => $errors->has('email')]) autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    @error('email')<p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
  </div>

  @if(! $showOtpForm)
    @if(! $userExists)
      <div>
        <label for="name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.account.name') }}</label>
        <input id="name" name="name" type="text" wire:model="name" @class(['input', 'border-red-500' => $errors->has('name')]) autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
      </div>
    @endif

    @include('livewire.auth.partials.recaptcha')

    <div>
      <button type="button" class="btn-secondary w-full" wire:click="sendOtpCode" wire:loading.attr="disabled" wire:target="sendOtpCode">
        {{ $userExists ? __('portal.owner.checkout.account.otp_send_existing') : __('portal.owner.checkout.account.otp_send_new') }}
      </button>
      <p class="mt-1 text-sm text-zinc-500">{{ $userExists ? __('portal.owner.checkout.account.otp_hint_existing') : __('portal.owner.checkout.account.otp_hint_new') }}</p>
    </div>
  @else
    <div>
      <label for="oneTimePassword" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.account.otp_code') }}</label>
      <input id="oneTimePassword" name="oneTimePassword" type="text" wire:model.live="oneTimePassword" @class(['input tracking-widest', 'border-red-500' => $errors->has('oneTimePassword')]) autocomplete="one-time-code" inputmode="numeric" required aria-describedby="otp-hint">
      <p id="otp-hint" @class(['mt-1 text-sm', 'text-red-600' => $errors->has('oneTimePassword'), 'text-zinc-500' => ! $errors->has('oneTimePassword')]) @error('oneTimePassword') role="alert" @enderror>
        {{ $errors->first('oneTimePassword') ?: __('portal.owner.checkout.account.otp_code_hint') }}
      </p>
    </div>

    <div x-data="{ sent: false }">
      <button type="button" class="btn-ghost -ml-3 px-3 text-brand-700" x-on:click="if (!sent) { sent = true; $wire.resendOtpCode(); setTimeout(() => sent = false, 2000) }" :disabled="sent">
        <span x-show="!sent">{{ __('portal.owner.checkout.account.otp_resend') }}</span>
        <span x-show="sent" x-cloak>{{ __('portal.owner.checkout.account.otp_resent') }}</span>
      </button>
    </div>
  @endif
</div>
