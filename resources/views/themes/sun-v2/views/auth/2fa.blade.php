{{--
    Zwei-Faktor-Code bei der Anmeldung im Theme sun-v2.
    Gerendert von Laragear\TwoFactor (config two-factor.login.view = auth.2fa),
    wenn LoginService::attempt() fuer ein Konto mit aktivem 2FA den Code
    verlangt — also auf der Tenant-Domain nach POST login.
    Daten: $input (Feldname des Codes, Standard 2fa_code), $errors.

    Das Formular hat bewusst keine action: es postet an dieselbe Adresse
    (login) zurueck, die Zugangsdaten haelt Laragear in der Session.
--}}
@extends('layouts.sun')

@section('title', __('portal.auth.two_factor.title').' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="card p-5 md:p-8 w-full max-w-md mx-auto">
    <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="shield-check" class="size-6" /></span>
    <h1 class="mt-4 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('portal.auth.two_factor.title') }}</h1>
    <p class="mt-2 text-zinc-500">{{ __('portal.auth.two_factor.text') }}</p>

    <form method="POST" class="mt-6">
      @csrf
      <label for="{{ $input }}" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.auth.two_factor.code_label') }}</label>
      <input id="{{ $input }}" name="{{ $input }}" type="text" @class(['input text-center text-xl tracking-widest font-mono', 'border-red-500' => $errors->isNotEmpty()]) autocomplete="one-time-code" minlength="6" required autofocus @if($errors->isNotEmpty()) aria-invalid="true" aria-describedby="code-error" @endif>
      @if($errors->isNotEmpty())
        <p id="code-error" class="mt-1 text-sm text-red-600" role="alert">{{ __('portal.auth.two_factor.failed') }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.auth.two_factor.code_hint') }}</p>
      @endif

      <button type="submit" class="mt-6 btn-primary w-full">{{ __('portal.auth.two_factor.submit') }}</button>
      <a href="{{ route('login') }}" class="mt-2 btn-ghost w-full">{{ __('portal.auth.reset.back') }}</a>
    </form>
  </div>
</div>
@endsection
