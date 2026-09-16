{{--
    Passwort bestaetigen im Theme sun-v2 (GET password/confirm, nur
    angemeldet). Daten: ConfirmPasswordController::showConfirmForm(), keine
    Variablen. Erscheint vor Routen mit der Middleware password.confirm.

    Postet an password.confirm (Feld password), Fehler kommen an password.
--}}
@extends('layouts.sun')

@section('title', __('portal.auth.confirm.title').' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="card p-5 md:p-8 w-full max-w-md mx-auto" id="confirmCard">
    <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="lock" class="size-6" /></span>
    <h1 class="mt-4 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('portal.auth.confirm.title') }}</h1>
    <p class="mt-2 text-zinc-500">{{ __('portal.auth.confirm.text') }}</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6">
      @csrf
      <div class="flex items-center justify-between gap-4">
        <label for="password" class="text-sm font-medium text-zinc-700">{{ __('portal.signup.account.password_label') }}</label>
        @if(Route::has('password.request'))
          <a href="{{ route('password.request') }}" class="text-sm text-brand font-medium hover:underline">{{ __('portal.auth.login.forgot') }}</a>
        @endif
      </div>
      <div class="relative mt-1">
        <input id="password" name="password" type="password" @class(['input pr-12', 'border-red-500' => $errors->has('password')]) autocomplete="current-password" required autofocus @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 size-10 rounded-lg text-zinc-400 hover:text-zinc-700 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand" data-toggle-pass aria-label="{{ __('portal.signup.account.password_show') }}"><x-sun.icon name="eye" class="size-5" /></button>
      </div>
      @error('password')
        <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }} {{ __('portal.auth.confirm.failed_hint') }}</p>
      @enderror

      <button type="submit" class="mt-6 btn-primary w-full">{{ __('portal.auth.confirm.submit') }}</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const pass = document.getElementById('password');
  document.querySelector('#confirmCard [data-toggle-pass]')?.addEventListener('click', event => {
    const isHidden = pass.type === 'password';
    pass.type = isHidden ? 'text' : 'password';
    event.currentTarget.setAttribute('aria-label', isHidden ? @js(__('portal.signup.account.password_hide')) : @js(__('portal.signup.account.password_show')));
  });
})();
</script>
@endpush
