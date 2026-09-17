{{--
    Einstellungen im Betriebsbereich, Theme sun-v2 (Vorlage einstellungen-elektrikerportal.html).
    Daten: OwnerDashboardController::settings(). Texte: lang/de/portal.php (owner.settings.*).

    Abweichungen von der Vorlage, weil das Backend fehlt (wie auf der alten Seite):
    - Name und E-Mail ohne "Ändern", Handynummer-Zeile entfaellt (kein Feld am Konto).
    - Passwort "Ändern" fuehrt zum vorhandenen Zuruecksetzen per E-Mail (password.request).
    - Benachrichtigungen werden nicht gespeichert: Schalter deaktiviert, Hinweis "kommt bald".
      Ausnahme: Monatsbericht (#16), eigene Livewire-Komponente monthly-report-setting.
    - Eintrag verstecken / loeschen: Buttons deaktiviert, Hinweis "kommt bald".
--}}
@extends('layouts.panel')

@php
    $user = auth()->user();
    $reviewCount = $company->reviews->count();
@endphp

@section('title', __('portal.owner.settings.title'))

@section('content')
  <div class="max-w-3xl">
    <div>
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.settings.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.settings.intro') }}</p>
    </div>

    {{-- Konto --}}
    <section class="mt-6 card p-5 md:p-6" aria-labelledby="sec-konto">
      <h2 id="sec-konto" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.settings.account.title') }}</h2>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.settings.account.since', ['datum' => $user->created_at->format('d.m.Y')]) }}</p>
      <dl class="mt-4 divide-y divide-zinc-200">
        <div class="py-3 grid sm:grid-cols-[8rem_1fr_auto] gap-1 sm:gap-4 items-center">
          <dt class="text-sm text-zinc-500">{{ __('portal.owner.settings.account.name') }}</dt>
          <dd class="text-base text-zinc-900">{{ $user->name }}</dd>
        </div>
        <div class="py-3 grid sm:grid-cols-[8rem_1fr_auto] gap-1 sm:gap-4 items-center">
          <dt class="text-sm text-zinc-500">{{ __('portal.owner.settings.account.email') }}</dt>
          <dd class="text-base text-zinc-900 break-all">{{ $user->email }}</dd>
        </div>
        <div class="py-3 grid sm:grid-cols-[8rem_1fr_auto] gap-1 sm:gap-4 items-center">
          <dt class="text-sm text-zinc-500">{{ __('portal.owner.settings.account.password') }}</dt>
          <dd class="text-base text-zinc-900" aria-label="{{ __('portal.owner.settings.account.password_hidden') }}">••••••••</dd>
          <dd><a href="{{ route('password.request') }}" class="btn-ghost -my-2 -mx-3 sm:mx-0">{{ __('portal.owner.settings.account.change') }}</a></dd>
        </div>
      </dl>
    </section>

    {{-- Benachrichtigungen --}}
    <section class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-benachrichtigungen">
      <h2 id="sec-benachrichtigungen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.settings.notifications.title') }}</h2>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.settings.notifications.intro', ['email' => $user->email]) }}</p>
      <ul class="mt-2 divide-y divide-zinc-200">
        @foreach(__('portal.owner.settings.notifications.items') as $key => $item)
          @if($key === 'report')
            {{-- Monatsbericht ist schaltbar (#16) --}}
            <livewire:portal.company.dashboard.monthly-report-setting />
            @continue
          @endif
          <li class="flex items-start justify-between gap-4 py-4">
            <div>
              <p class="font-medium text-zinc-900" id="notify-{{ $key }}">{{ $item['title'] }}</p>
              <p class="text-sm text-zinc-500">{{ $item['text'] }}</p>
              @if(! empty($item['premium']) && ! $company->is_premium)
                <p class="mt-1 text-sm"><span class="pill-brand text-xs">{{ __('portal.owner.edit.locked.badge') }}</span> <a href="{{ route('portal.owner.premium') }}" class="text-brand font-medium hover:underline ml-1">{{ __('portal.owner.settings.notifications.learn_more') }}</a></p>
              @endif
            </div>
            <button type="button" role="switch" aria-checked="{{ $item['default'] ? 'true' : 'false' }}" aria-labelledby="notify-{{ $key }}" disabled
                    class="group relative mt-1 inline-flex h-7 w-12 shrink-0 items-center rounded-full bg-zinc-300 p-0.5 transition-colors aria-checked:bg-brand disabled:cursor-not-allowed disabled:opacity-60">
              <span class="size-6 rounded-full bg-white shadow-sm transition-transform group-aria-checked:translate-x-5"></span>
            </button>
          </li>
        @endforeach
      </ul>
      <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.settings.notifications.soon') }}</p>
    </section>

    {{-- Abo --}}
    <section class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-abo">
      <h2 id="sec-abo" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.settings.plan.title') }}</h2>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4">
        @if($company->is_premium)
          <div>
            <p class="font-medium text-zinc-900 flex items-center gap-2">{{ __('portal.owner.edit.locked.badge') }} <span class="pill-brand text-xs">{{ __('portal.owner.overview.entry.active') }}</span></p>
            <p class="text-sm text-zinc-500">{{ __('portal.owner.settings.plan.premium_text') }}</p>
          </div>
          <a href="{{ route('portal.owner.premium') }}" class="btn-secondary">{{ __('portal.owner.settings.plan.manage') }}</a>
        @else
          <div>
            <p class="font-medium text-zinc-900 flex items-center gap-2">{{ __('portal.owner.overview.entry.plan_basic') }} <span class="pill text-xs">{{ __('portal.owner.settings.plan.free') }}</span></p>
            <p class="text-sm text-zinc-500">{{ __('portal.owner.settings.plan.basic_text') }}</p>
          </div>
          <a href="{{ route('portal.owner.premium') }}" class="btn-secondary"><x-sun.icon name="sparkles" class="icon" />{{ __('portal.owner.sidebar_premium.cta') }}</a>
        @endif
      </div>
      @unless($company->is_premium)
        <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.settings.plan.hint') }}</p>
      @endunless
    </section>

    {{-- Verifiziert-Badge (#11) --}}
    <livewire:portal.company.dashboard.verification />

    {{-- Eintrag loeschen: ruhig, ohne rote Box --}}
    <section class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-loeschen">
      <h2 id="sec-loeschen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.settings.delete.title') }}</h2>
      <p class="mt-1 text-base text-zinc-700 max-w-prose">{{ trans_choice('portal.owner.settings.delete.text', $reviewCount, ['anzahl' => $reviewCount]) }}</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="btn-secondary disabled:cursor-not-allowed disabled:opacity-60" disabled>{{ __('portal.owner.settings.delete.hide') }}</button>
        <button type="button" class="btn-ghost text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60" disabled>{{ __('portal.owner.settings.delete.delete') }}</button>
      </div>
      @php $supportEmail = $currentTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL); @endphp
      <p class="mt-3 text-sm text-zinc-500">
        @if($supportEmail)
          {{ __('portal.owner.settings.delete.soon_contact') }} <a href="mailto:{{ $supportEmail }}" class="font-medium text-brand hover:underline">{{ $supportEmail }}</a>.
        @else
          {{ __('portal.owner.settings.delete.soon') }}
        @endif
      </p>
    </section>
  </div>
@endsection
