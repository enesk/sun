{{--
    Anmeldung im Theme sun-v2 (Vorlage elektrikerportal-login.html).
    Daten: LoginController::showLoginForm() plus $sunLogin aus
    App\Themes\SunV2\LoginViewComposer.

    Beide Formulare posten an die vorhandenen Routen (login, password.email).
    Die drei Zustaende der Karte (Anmelden, Passwort zuruecksetzen, Link
    gesendet) waehlt der Server vor — nach einem Rueckweg mit Fehler oder
    Status-Meldung steht die passende Karte offen. Das Umschalten ohne
    Neuladen ist nur Zugabe (Skript unten).
--}}
@extends('layouts.sun')

@php
    // SendsPasswordResetEmails schickt nur 'email' als Eingabe zurueck, ein
    // verstecktes Feld kommt also nicht an. Ein Fehler aus dem Passwort-Link
    // ist deshalb an seinem Meldungstext (passwords.*) zu erkennen.
    $resetErrors = [__('passwords.user'), __('passwords.throttled')];
    $panel = match (true) {
        (bool) session('status') => 'sent',
        in_array($errors->first('email'), $resetErrors, true) => 'reset',
        default => 'login',
    };
    $loginFailed = $panel === 'login' && ($errors->has('email') || $errors->has('password'));
@endphp

@section('title', 'Anmelden | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, follow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="grid gap-8 lg:grid-cols-2 lg:gap-12 lg:items-center max-w-5xl mx-auto">

    <!-- ===== LOGIN-KARTE ===== -->
    <div class="card p-5 md:p-8 w-full max-w-md mx-auto lg:mx-0 lg:order-2" id="loginCard">

      <!-- Anmelden -->
      <div data-panel="login" @class(['hidden' => $panel !== 'login'])>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">Anmelden</h1>
        <p class="mt-2 text-zinc-500">{{ $sunLogin['intro'] }}</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6">
          @csrf
          <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">E-Mail</label>
          <input id="email" name="email" type="email" value="{{ $panel === 'login' ? old('email') : '' }}" @class(['input', 'border-red-500' => $loginFailed]) autocomplete="username" inputmode="email" required @if($panel === 'login') autofocus @endif>

          <div class="mt-4 flex items-center justify-between gap-4">
            <label for="pass" class="text-sm font-medium text-zinc-700">Passwort</label>
            <a href="{{ route('password.request') }}" class="text-sm text-brand font-medium hover:underline" data-goto="reset">Vergessen?</a>
          </div>
          <div class="relative mt-1">
            <input id="pass" name="password" type="password" @class(['input pr-12', 'border-red-500' => $loginFailed]) autocomplete="current-password" required>
            <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 size-10 rounded-lg text-zinc-400 hover:text-zinc-700 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand" data-toggle-pass aria-label="Passwort anzeigen"><x-sun.icon name="eye" class="size-5" /></button>
          </div>

          @if($loginFailed)
            <p class="mt-3 text-sm text-red-600" role="alert">E-Mail oder Passwort stimmt nicht. Prüf beides noch mal.</p>
          @endif

          <label class="mt-4 flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="remember" class="size-5 rounded border-zinc-300 text-brand focus:ring-brand" @checked(old('remember', true))>
            <span class="text-sm text-zinc-700">Angemeldet bleiben</span>
          </label>

          @if(config('app.recaptcha_enabled'))
            <div class="mt-4">{!! htmlFormSnippet() !!}</div>
            @error('g-recaptcha-response')
              <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            @push('scripts')
              {!! htmlScriptTagJsApi() !!}
            @endpush
          @endif

          <button type="submit" class="mt-6 btn-primary w-full">Anmelden</button>
        </form>

        <p class="mt-6 pt-6 border-t border-zinc-200 text-sm text-zinc-500 text-center">Noch kein Konto? <a href="{{ route('portal.companies.create') }}" class="text-brand font-medium hover:underline">Betrieb kostenlos eintragen</a></p>
      </div>

      <!-- Passwort zurücksetzen -->
      <div data-panel="reset" @class(['hidden' => $panel !== 'reset'])>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">Passwort zurücksetzen</h1>
        <p class="mt-2 text-zinc-500">Gib deine E-Mail ein, wir schicken dir einen Link zum Neuvergeben.</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6">
          @csrf
          <label for="reset-email" class="block text-sm font-medium text-zinc-700 mb-1">E-Mail</label>
          <input id="reset-email" name="email" type="email" value="{{ $panel === 'reset' ? old('email') : '' }}" @class(['input', 'border-red-500' => $panel === 'reset' && $errors->has('email')]) autocomplete="username" inputmode="email" required>
          @if($panel === 'reset' && $errors->has('email'))
            <p class="mt-1 text-sm text-red-600" role="alert">{{ $errors->first('email') }}</p>
          @else
            <p class="mt-1 text-sm text-zinc-500">Die Adresse, mit der du dich registriert hast.</p>
          @endif
          <button type="submit" class="mt-6 btn-primary w-full">Link senden</button>
          <a href="{{ route('login') }}" class="mt-2 btn-ghost w-full" data-goto="login">Zurück zur Anmeldung</a>
        </form>
      </div>

      <!-- Link gesendet -->
      <div data-panel="sent" @class(['text-center py-4 flex-col items-center gap-3', 'flex' => $panel === 'sent', 'hidden' => $panel !== 'sent'])>
        <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="mail" class="size-6" /></span>
        <h1 class="text-lg font-semibold text-zinc-900">Schau in dein Postfach</h1>
        <p class="text-zinc-500">Falls ein Konto mit dieser Adresse existiert, ist der Link unterwegs. Er gilt 60 Minuten.</p>
        <a href="{{ route('login') }}" class="btn-ghost w-full mt-2" data-goto="login">Zurück zur Anmeldung</a>
      </div>
    </div>

    <!-- ===== SEITENTEXT ===== -->
    <div class="max-w-md mx-auto lg:mx-0 lg:order-1">
      <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ $sunLogin['headline'] }}</h2>
      <p class="mt-3 text-base md:text-lg leading-relaxed">{{ $sunLogin['text'] }}</p>
      <ul class="mt-5 space-y-3 text-zinc-700">
        @foreach($sunLogin['benefits'] as $benefit)
          <li class="flex items-start gap-3"><x-sun.icon name="check" class="icon text-brand mt-0.5" /><span>{{ $benefit }}</span></li>
        @endforeach
      </ul>
      <p class="mt-6 text-sm text-zinc-500">Probleme beim Anmelden? <a href="mailto:{{ $sunLogin['support_email'] }}" class="text-brand hover:underline">{{ $sunLogin['support_email'] }}</a></p>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const card = document.getElementById('loginCard');
  if (!card) return;

  const show = name => {
    card.querySelectorAll('[data-panel]').forEach(panel => {
      const active = panel.dataset.panel === name;
      panel.classList.toggle('hidden', !active);
      if (panel.dataset.panel === 'sent') panel.classList.toggle('flex', active);
    });
    card.querySelector(`[data-panel="${name}"] input:not([type=hidden])`)?.focus();
  };

  card.querySelectorAll('[data-goto]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    show(link.dataset.goto);
  }));

  const pass = card.querySelector('#pass');
  card.querySelector('[data-toggle-pass]')?.addEventListener('click', event => {
    const isHidden = pass.type === 'password';
    pass.type = isHidden ? 'text' : 'password';
    event.currentTarget.setAttribute('aria-label', isHidden ? 'Passwort verbergen' : 'Passwort anzeigen');
  });
})();
</script>
@endpush
