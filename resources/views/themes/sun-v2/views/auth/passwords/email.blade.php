{{--
    Passwort-Link anfordern im Theme sun-v2 (GET password/reset).
    Daten: ForgotPasswordController::showLinkRequestForm(), keine Variablen.
    Verlinkt aus dem Anmelden-Formular und aus "Einstellungen -> Passwort
    aendern". Angemeldete Inhaber bekommen ihre Adresse vorbelegt und den
    Rueckweg in die Einstellungen.

    Postet an password.email (Feld email, optional g-recaptcha-response).
    Nach dem Absenden kommt der Nutzer mit session('status') zurueck, dann
    zeigt die Karte den Zustand "Link gesendet".
--}}
@extends('layouts.sun')

@php
    $user = auth()->user();
    $backUrl = $user && Route::has('portal.owner.settings') ? route('portal.owner.settings') : route('login');
    $backLabel = $user ? __('portal.auth.request.back_settings') : __('portal.auth.reset.back');
@endphp

@section('title', __('portal.auth.reset.title').' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, follow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="card p-5 md:p-8 w-full max-w-md mx-auto">
    @if(session('status'))
      <div class="text-center py-4 flex flex-col items-center gap-3" role="status">
        <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="mail" class="size-6" /></span>
        <h1 class="text-lg font-semibold text-zinc-900">{{ __('portal.auth.sent.title') }}</h1>
        <p class="text-zinc-500">{{ __('portal.auth.sent.text', ['minuten' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]) }}</p>
        <a href="{{ $backUrl }}" class="btn-ghost w-full mt-2">{{ $backLabel }}</a>
      </div>
    @else
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('portal.auth.reset.title') }}</h1>
      <p class="mt-2 text-zinc-500">{{ __('portal.auth.reset.text') }}</p>

      <form method="POST" action="{{ route('password.email') }}" class="mt-6">
        @csrf
        <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.email_label') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" @class(['input', 'border-red-500' => $errors->has('email')]) autocomplete="username" inputmode="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')
          <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
        @else
          <p class="mt-1 text-sm text-zinc-500">{{ __('portal.auth.reset.email_hint') }}</p>
        @enderror

        @if(config('app.recaptcha_enabled'))
          <div class="mt-4">{!! htmlFormSnippet() !!}</div>
          @error('g-recaptcha-response')
            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
          @enderror
          @push('scripts')
            {!! htmlScriptTagJsApi() !!}
          @endpush
        @endif

        <button type="submit" class="mt-6 btn-primary w-full">{{ __('portal.auth.reset.submit') }}</button>
        <a href="{{ $backUrl }}" class="mt-2 btn-ghost w-full">{{ $backLabel }}</a>
      </form>
    @endif
  </div>
</div>
@endsection
