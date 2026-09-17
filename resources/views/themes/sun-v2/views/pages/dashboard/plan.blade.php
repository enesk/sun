{{--
    Mein Plan im Betriebsbereich, Theme sun-v2 (#17).
    Daten: OwnerDashboardController::plan(), Inhalt: Livewire portal.company.dashboard.plan.
--}}
@extends('layouts.panel')

@section('title', __('premium.plan.title'))

@section('content')
  <div>
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('premium.plan.title') }}</h1>
    <p class="mt-1 text-base text-zinc-500">{{ __('premium.plan.intro') }}</p>
  </div>

  <div class="mt-6">
    @livewire('portal.company.dashboard.plan', ['company' => $company])
  </div>
@endsection
