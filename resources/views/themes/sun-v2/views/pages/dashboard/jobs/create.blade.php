{{--
    Neue Stellenanzeige im Betriebsbereich, Theme sun-v2.
    Daten: OwnerJobController::create(). Formular: livewire.portal.dashboard.owner-job-form. Texte: lang/de/portal.php (owner.jobs.*).
--}}
@extends('layouts.panel')

@section('title', __('portal.owner.jobs.create.title'))

@section('content')
  <a href="{{ route('portal.owner.jobs.index') }}" class="btn-ghost -ml-3 px-3">
    <x-sun.icon name="arrow-left" class="icon" />{{ __('portal.owner.jobs.back') }}
  </a>
  <div class="mt-2">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.jobs.create.title') }}</h1>
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.jobs.create.intro', ['firma' => $company->name]) }}</p>
  </div>

  @livewire('portal.dashboard.owner-job-form')
@endsection
