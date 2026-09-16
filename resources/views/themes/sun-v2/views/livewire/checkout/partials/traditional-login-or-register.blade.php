{{-- E-Mail, Passwort und Name im Checkout, Theme sun-v2 (Felder wie im Default-Partial). --}}
<div class="space-y-4">
  <div>
    <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.account.email') }}</label>
    <input id="email" name="email" type="email" wire:model.blur="email" @class(['input', 'border-red-500' => $errors->has('email')]) autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    @error('email')<p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
  </div>

  @if(! empty($email))
    <div>
      <div class="flex items-baseline justify-between gap-3 mb-1">
        <label for="password" class="block text-sm font-medium text-zinc-700">{{ __('portal.owner.checkout.account.password') }}</label>
        @if($userExists && Route::has('password.request'))
          <a href="{{ route('password.request') }}" class="text-sm text-brand-700 underline">{{ __('portal.owner.checkout.account.forgot') }}</a>
        @endif
      </div>
      <input id="password" name="password" type="password" wire:model="password" @class(['input', 'border-red-500' => $errors->has('password')]) autocomplete="{{ $userExists ? 'current-password' : 'new-password' }}" required aria-describedby="password-hint">
      <p id="password-hint" @class(['mt-1 text-sm', 'text-red-600' => $errors->has('password'), 'text-zinc-500' => ! $errors->has('password')]) @error('password') role="alert" @enderror>
        {{ $errors->first('password') ?: ($userExists ? __('portal.owner.checkout.account.password_existing') : __('portal.owner.checkout.account.password_new')) }}
      </p>
    </div>
  @endif

  @if(! $userExists || empty($email))
    <div>
      <label for="name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.account.name') }}</label>
      <input id="name" name="name" type="text" wire:model="name" @class(['input', 'border-red-500' => $errors->has('name')]) autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
      @error('name')<p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
  @endif

  @include('livewire.auth.partials.recaptcha')
</div>
