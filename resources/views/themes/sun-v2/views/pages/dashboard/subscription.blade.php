{{--
    Abo-Details und Kuendigung im Betriebsbereich (/firmenprofil/abo), Theme sun-v2.
    Inhalt: Livewire SubscriptionDetails. Texte: lang/de/premium.php (subscription.*).
--}}
@extends('layouts.panel')

@section('title', __('premium.subscription.title'))

@section('content')
  <div class="max-w-3xl">
    <a href="{{ route('portal.owner.plan') }}" class="btn-ghost -ml-3 px-3">
      <x-sun.icon name="arrow-left" class="icon" />{{ __('premium.plan.title') }}
    </a>
    <div class="mt-2">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('premium.subscription.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('premium.subscription.intro') }}</p>
    </div>

    <div class="mt-6">
      <livewire:portal.company.dashboard.subscription-details :company="$company" />
    </div>
  </div>
@endsection
