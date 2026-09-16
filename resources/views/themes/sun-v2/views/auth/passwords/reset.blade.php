{{--
    Neues Passwort festlegen im Theme sun-v2 (GET password/reset/{token},
    Link aus der Mail). Daten: ResetPasswordController::showResetForm()
    liefert $token und $email (aus dem Link).

    Postet an password.update mit token, email, password,
    password_confirmation. Ist der Link abgelaufen oder ungueltig, meldet
    der Broker das am Feld email (passwords.token) — dann fuehrt ein Link
    direkt zum Neuanfordern.
--}}
@extends('layouts.sun')

@php
    $tokenInvalid = $errors->first('email') === __('passwords.token');
@endphp

@section('title', __('portal.auth.new_password.title').' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="card p-5 md:p-8 w-full max-w-md mx-auto" id="resetCard">
    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('portal.auth.new_password.title') }}</h1>
    <p class="mt-2 text-zinc-500">{{ __('portal.auth.new_password.text') }}</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">

      <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.email_label') }}</label>
      <input id="email" name="email" type="email" value="{{ $email ?? old('email') }}" @class(['input', 'border-red-500' => $errors->has('email')]) autocomplete="username" inputmode="email" required @if(empty($email)) autofocus @endif @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
      @error('email')
        <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">
          {{ $message }}
          @if($tokenInvalid)
            <a href="{{ route('password.request') }}" class="font-medium underline">{{ __('portal.auth.new_password.request_new') }}</a>
          @endif
        </p>
      @else
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.auth.new_password.email_hint') }}</p>
      @enderror

      <label for="password" class="mt-4 block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.auth.new_password.password_label') }}</label>
      <div class="relative">
        <input id="password" name="password" type="password" @class(['input pr-12', 'border-red-500' => $errors->has('password')]) autocomplete="new-password" required minlength="8" @if(!empty($email)) autofocus @endif data-pass @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 size-10 rounded-lg text-zinc-400 hover:text-zinc-700 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand" data-toggle-pass aria-label="{{ __('portal.signup.account.password_show') }}"><x-sun.icon name="eye" class="size-5" /></button>
      </div>
      @error('password')
        <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.signup.account.password_hint') }}</p>
      @enderror

      <label for="password_confirmation" class="mt-4 block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.auth.new_password.confirm_label') }}</label>
      <input id="password_confirmation" name="password_confirmation" type="password" @class(['input', 'border-red-500' => $errors->has('password')]) autocomplete="new-password" required data-pass>
      <p class="mt-1 text-sm text-zinc-500">{{ __('portal.auth.new_password.confirm_hint') }}</p>

      <button type="submit" class="mt-6 btn-primary w-full">{{ __('portal.auth.new_password.submit') }}</button>
      <a href="{{ route('login') }}" class="mt-2 btn-ghost w-full">{{ __('portal.auth.reset.back') }}</a>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const card = document.getElementById('resetCard');
  card?.querySelector('[data-toggle-pass]')?.addEventListener('click', event => {
    const fields = card.querySelectorAll('[data-pass]');
    const isHidden = fields[0].type === 'password';
    fields.forEach(field => { field.type = isHidden ? 'text' : 'password'; });
    event.currentTarget.setAttribute('aria-label', isHidden ? @js(__('portal.signup.account.password_hide')) : @js(__('portal.signup.account.password_show')));
  });
})();
</script>
@endpush
