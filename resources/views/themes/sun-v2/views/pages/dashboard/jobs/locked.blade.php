{{--
    Stellenanzeigen ohne Premium, Theme sun-v2: gesperrte Ansicht wie die Premium-Karten in stats.blade.php.
    Daten: OwnerJobController::index() fuer Basis-Eintraege. Texte: lang/de/portal.php (owner.jobs.locked.*).
    Kein Preis: der steht nur auf der Premium-Seite.
--}}
@extends('layouts.panel')

@php
    // Limit des kleinsten bezahlten Pakets laut config/premium.php (#13)
    $maxJobs = (int) \App\Enums\PlanTier::Pro->limit('job_postings_active');
    $days = \App\Models\Portal\Job::EXPIRES_AFTER_DAYS;
    $features = [
        trans_choice('portal.owner.jobs.locked.features.active', $maxJobs, ['anzahl' => $maxJobs]),
        __('portal.owner.jobs.locked.features.applications'),
        __('portal.owner.jobs.locked.features.visible', ['tage' => $days]),
    ];
@endphp

@section('title', __('portal.owner.jobs.title'))

@section('content')
  <div>
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.jobs.title') }}</h1>
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.jobs.locked.intro') }}</p>
  </div>

  <div class="mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">
    <section class="card border-brand-200 p-5 md:p-6 min-w-0" aria-labelledby="sec-jobs-gesperrt">
      <p class="pill-brand mb-3"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.jobs.locked.badge') }}</p>
      <h2 id="sec-jobs-gesperrt" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.locked.title') }}</h2>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.jobs.locked.text') }}</p>

      {{-- Vorschau: zwei leere Stellenkarten --}}
      <div class="mt-4 space-y-2 opacity-70" aria-hidden="true">
        @foreach(['w-3/5', 'w-2/5'] as $width)
          <div class="rounded-xl border border-zinc-200 p-4">
            <div class="flex items-center gap-2">
              <span class="block h-4 {{ $width }} rounded-full bg-zinc-200"></span>
              <span class="block h-5 w-16 rounded-full bg-brand-100"></span>
            </div>
            <div class="mt-3 flex gap-3">
              <span class="block h-3 w-20 rounded-full bg-zinc-100"></span>
              <span class="block h-3 w-24 rounded-full bg-zinc-100"></span>
            </div>
          </div>
        @endforeach
      </div>

      <ul class="mt-4 space-y-2 text-base text-zinc-700">
        @foreach($features as $feature)
          <li class="flex items-center gap-2"><x-sun.icon name="check" class="icon text-emerald-600" />{{ $feature }}</li>
        @endforeach
      </ul>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4">
        <p class="text-sm text-zinc-500">{{ __('portal.owner.jobs.locked.hint') }}</p>
        <a href="{{ route('portal.owner.premium') }}" class="btn-secondary w-full sm:w-auto"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.jobs.locked.unlock') }}</a>
      </div>
    </section>

    <aside class="lg:sticky lg:top-24">
      <section class="rounded-2xl bg-brand-50 p-5 md:p-6" aria-labelledby="sec-premium">
        <p class="pill-brand bg-white"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.jobs.locked.badge') }}</p>
        <h2 id="sec-premium" class="mt-3 text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.locked.premium_title') }}</h2>
        <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.jobs.locked.premium_text') }}</p>
        <a href="{{ route('portal.owner.premium') }}" class="btn-primary mt-4 w-full">{{ __('portal.owner.jobs.locked.premium_cta') }}</a>
      </section>
    </aside>
  </div>
@endsection
