{{--
    Stellenanzeigen im Betriebsbereich, Theme sun-v2.
    Daten: OwnerJobController::index() (nur Premium, sonst jobs.locked). Texte: lang/de/portal.php (owner.jobs.*).
--}}
@extends('layouts.panel')

@section('title', __('portal.owner.jobs.title'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.jobs.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.jobs.intro', ['firma' => $company->name]) }}</p>
    </div>
    @if($canCreate)
      <a href="{{ route('portal.owner.jobs.create') }}" class="btn-primary w-full sm:w-auto">
        <x-sun.icon name="plus" class="icon" />{{ __('portal.owner.jobs.create_cta') }}
      </a>
    @endif
  </div>

  {{-- Limit erreicht --}}
  @if(! $canCreate && $activeJobs->isNotEmpty())
    <div class="card mt-6 flex items-start gap-3 p-4 md:p-5" role="note">
      <x-sun.icon name="info" class="icon mt-0.5 text-amber-500" />
      <p class="text-base text-zinc-700">{{ trans_choice('portal.owner.jobs.limit', \App\Models\Portal\Job::MAX_ACTIVE_PER_COMPANY, ['anzahl' => $activeJobs->count(), 'max' => \App\Models\Portal\Job::MAX_ACTIVE_PER_COMPANY]) }}</p>
    </div>
  @endif

  {{-- Aktive Stellen --}}
  <section class="mt-6 md:mt-8" aria-labelledby="sec-aktiv">
    <h2 id="sec-aktiv" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.active_title') }} <span class="text-base font-normal text-zinc-500">{{ $activeJobs->count() }}</span></h2>

    @if($activeJobs->isEmpty())
      <div class="card mt-4 p-6 md:p-8 text-center">
        <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700" aria-hidden="true"><x-sun.icon name="briefcase" class="size-6" /></span>
        <h3 class="mt-3 text-lg font-semibold text-zinc-900">{{ __('portal.owner.jobs.empty.title') }}</h3>
        <p class="mx-auto mt-1 max-w-md text-base text-zinc-500">{{ __('portal.owner.jobs.empty.text') }}</p>
        @if($canCreate)
          <a href="{{ route('portal.owner.jobs.create') }}" class="btn-secondary mt-4">{{ __('portal.owner.jobs.empty.cta') }}</a>
        @endif
      </div>
    @else
      <div class="mt-4 space-y-4">
        @foreach($activeJobs as $job)
          @include('pages.dashboard.jobs._job-card', ['job' => $job])
        @endforeach
      </div>
    @endif
  </section>

  {{-- Abgelaufene und pausierte Stellen --}}
  @if($expiredJobs->isNotEmpty())
    <section class="mt-8" aria-labelledby="sec-abgelaufen">
      <h2 id="sec-abgelaufen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.expired_title') }} <span class="text-base font-normal text-zinc-500">{{ $expiredJobs->count() }}</span></h2>
      <div class="mt-4 space-y-4">
        @foreach($expiredJobs as $job)
          @include('pages.dashboard.jobs._job-card', ['job' => $job, 'expired' => true])
        @endforeach
      </div>
    </section>
  @endif
@endsection
