{{--
    Stellenanzeigen im Theme sun-v2 (Vorlage elektrikerportal-jobs.html).
    Daten: PublicJobController::index() — $jobs (Paginator, Top-Jobs zuerst),
    $employmentTypes, $cities, $totalJobs, $sort.

    Suche und Filter laufen ueber die Query-Parameter des Controllers
    (q, city, type, sort). Arbeitgeber-Kasten, Job-Mail und Preis-CTA sind
    weiterhin reine Gestaltung ohne Funktion.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $jobCount = $jobs->total();
    $filtered = request()->hasAny(['q', 'city', 'type']);

    $sortLabels = [
        'newest' => __('portal.layout.filters.sort_newest'),
        'az' => __('portal.layout.filters.sort_name'),
        'salary' => __('portal.jobs.filters.sort_salary'),
    ];
    $sortLinks = collect($sortLabels)->map(fn ($label, $key) => [
        'label' => $label,
        'url' => request()->fullUrlWithQuery(['sort' => $key === 'newest' ? null : $key, 'page' => null]),
        'active' => $sort === $key,
    ]);

    $activeType = request('type');
    $typeLinks = collect($employmentTypes)
        ->filter(fn ($type) => $type['count'] > 0)
        ->map(fn ($type, $key) => [
            'label' => $type['label'],
            'count' => $type['count'],
            'url' => request()->fullUrlWithQuery(['type' => $activeType === $key ? null : $key, 'page' => null]),
            'active' => $activeType === $key,
        ]);

    $benefits = [
        __('portal.jobs.cta.benefit_duration'),
        __('portal.jobs.cta.benefit_inbox'),
        __('portal.jobs.cta.benefit_google'),
        __('portal.jobs.cta.benefit_profile'),
    ];
@endphp

@section('title', __('portal.jobs.meta_title').' | '.$portalName)
@section('meta_description', trans_choice('portal.jobs.meta_description', $totalJobs, ['anzahl' => number_format($totalJobs, 0, ',', '.')]))

@section('content')

<!-- ===== HERO mit Job-Suche ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5">
      <a href="{{ route('home') }}" class="hover:text-brand">{{ __('portal.layout.breadcrumb.home') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ __('portal.layout.header.jobs') }}</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.jobs.headline') }}</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ trans_choice('portal.jobs.meta_description', $totalJobs, ['anzahl' => number_format($totalJobs, 0, ',', '.')]) }}</p>
    </div>

    <form action="{{ route('portal.jobs.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6">
      <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="q">{{ __('portal.jobs.search.what_label') }}</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" value="{{ request('q') }}" class="input pl-10" placeholder="{{ __('portal.jobs.search.what_placeholder') }}">
        </div>
        <div class="relative">
          <label class="sr-only" for="ort">{{ __('portal.layout.search_form.where_label') }}</label>
          <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="ort" name="city" value="{{ request('city') }}" class="input pl-10" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="address-level2">
        </div>
        @if(request()->filled('type'))
          <input type="hidden" name="type" value="{{ request('type') }}">
        @endif
        @if($sort !== 'newest')
          <input type="hidden" name="sort" value="{{ $sort }}">
        @endif
        <button type="submit" class="btn-primary">{{ __('portal.jobs.search.submit') }}</button>
      </div>
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <span class="text-sm text-zinc-500 mr-1 w-full sm:w-auto">{{ __('portal.layout.search_form.popular') }}</span>
      @foreach(['Elektroniker' => 'Elektroniker EuG', 'Ausbildung' => 'Ausbildung', 'Meister' => 'Meister', 'Quereinstieg' => 'Quereinstieg'] as $query => $label)
        <a href="{{ route('portal.jobs.index', ['q' => $query]) }}" class="pill-link">{{ $label }}</a>
      @endforeach
    </div>
  </div>
</section>

<div class="container-portal pt-8 pb-12 md:pb-16">

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ trans_choice('portal.jobs.results_heading', $jobCount, ['anzahl' => number_format($jobCount, 0, ',', '.')]) }}</h2>
    @if($sort === 'newest')
      <p class="text-sm text-zinc-500">{{ __('portal.jobs.sorted_by_date') }}</p>
    @endif
  </div>

  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="{{ __('portal.layout.filters.label') }}">
    <button type="button" class="pill-link whitespace-nowrap" data-disclosure="filter" aria-expanded="false" aria-controls="jobs-filter-sort">{{ __('portal.layout.filters.sort', ['sortierung' => $sortLabels[$sort] ?? $sortLabels['newest']]) }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    @foreach($typeLinks as $link)
      <a href="{{ $link['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $link['active']]) aria-pressed="{{ $link['active'] ? 'true' : 'false' }}" role="button">{{ $link['label'] }}</a>
    @endforeach
  </div>
  <div id="jobs-filter-sort" hidden class="mt-3 flex flex-wrap gap-2">
    @foreach($sortLinks as $link)
      <a href="{{ $link['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $link['active']])>{{ $link['label'] }}</a>
    @endforeach
  </div>

  <div class="mt-6 grid gap-8 xl:grid-cols-[1fr_18rem]">
    <div class="flex flex-col gap-4 min-w-0">

      @forelse($jobs as $job)
        @if($loop->index === 3)
          <x-ad-slot position="listing_between_results" />
        @endif
        @php
            $jobUrl = route('portal.jobs.show', $job->slug);
            $jobLogo = $job->company?->getFirstMediaUrl('logo', 'thumb');
        @endphp
        <article @class(['card-interactive p-5 flex flex-col lg:flex-row lg:items-start gap-4', 'border-l-4 border-l-brand' => $job->is_top_job])>
          <div class="flex-1 min-w-0 flex flex-col gap-3">
            <div class="flex items-start gap-3">
              @if($jobLogo)
                <img src="{{ $jobLogo }}" alt="" width="48" height="48" loading="lazy" class="size-12 rounded-2xl border border-zinc-200 object-cover shrink-0">
              @else
                <span class="size-12 rounded-2xl bg-brand-50 text-brand-700 font-bold flex items-center justify-center shrink-0" aria-hidden="true">{{ \App\Themes\SunV2\ProfileViewComposer::initials($job->company?->name ?? $job->title) }}</span>
              @endif
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <h2 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ $jobUrl }}" class="hover:text-brand">{{ $job->title }}</a></h2>
                  @if($job->is_top_job)
                    <span class="pill-brand shrink-0">{{ __('portal.jobs.card.top_job') }}</span>
                  @endif
                </div>
                @if($job->company)
                  <p class="mt-1"><a href="{{ $job->company->portal_url }}" class="text-zinc-700 hover:text-brand">{{ $job->company->name }}</a></p>
                @endif
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <dl class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-zinc-500">
                @if($job->location_display)
                  <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.location') }}</dt><x-sun.icon name="map-pin" class="size-4 shrink-0" /><dd>{{ $job->location_display }}</dd></div>
                @endif
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.type') }}</dt><x-sun.icon name="clock" class="size-4 shrink-0" /><dd>{{ $job->employment_type_label }}</dd></div>
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.salary') }}</dt><x-sun.icon name="euro" class="size-4 shrink-0" /><dd @class(['text-zinc-900 font-medium' => $job->salary_display])>{{ $job->salary_display ?? __('portal.jobs.card.salary_none') }}</dd></div>
              </dl>
              @if($job->published_at)
                <p class="mt-3 text-sm text-zinc-500">{{ ucfirst($job->published_at->locale('de')->diffForHumans()) }}</p>
              @endif
            </div>
          </div>
          <div class="flex lg:flex-col gap-2 lg:w-44 shrink-0">
            <a href="{{ $jobUrl }}#bewerben" class="btn-primary flex-1" aria-label="{{ __('portal.jobs.card.apply') }}: {{ $job->title }}">{{ __('portal.jobs.card.apply') }}</a>
            <a href="{{ $jobUrl }}" class="btn-secondary flex-1" aria-label="{{ __('portal.jobs.card.details') }}: {{ $job->title }}">{{ __('portal.jobs.card.details') }}</a>
          </div>
        </article>
      @empty
        <div class="card p-5 md:p-8">
          <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.jobs.empty.heading') }}</h2>
          <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.jobs.empty.text') }}</p>
          @if($filtered)
            <a href="{{ route('portal.jobs.index') }}" class="mt-4 btn-primary">{{ __('portal.jobs.empty.show_all') }}</a>
          @endif
        </div>
      @endforelse

      <x-sun.pagination :paginator="$jobs" :pages="\App\Themes\SunV2\PageWindow::for($jobs)" />
    </div>

    <!-- ===== RECHTE SPALTE: Arbeitgeber-Verkauf ===== -->
    <aside class="hidden xl:block">
      <div class="sticky top-20 flex flex-col gap-4">
        <div class="card p-5 bg-brand-50 border-brand-100">
          <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.jobs.employer.heading') }}</h2>
          <p class="mt-1 text-zinc-700 leading-relaxed">{{ __('portal.jobs.employer.text') }}</p>
          <a href="#" class="mt-4 btn-primary w-full">{{ __('portal.jobs.employer.button') }}</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('portal.jobs.employer.price_note') }}</p>
        </div>
        <div class="card p-5">
          <h2 class="font-semibold text-zinc-900">{{ __('portal.jobs.alert.heading') }}</h2>
          <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.alert.text') }}</p>
          <form class="mt-3 grid gap-2" onsubmit="return false">
            <label class="sr-only" for="alert-mail">{{ __('portal.jobs.alert.email_label') }}</label>
            <input id="alert-mail" type="email" class="input" placeholder="{{ __('portal.jobs.alert.email_placeholder') }}">
            <button type="button" class="btn-secondary w-full">{{ __('portal.jobs.alert.button') }}</button>
          </form>
        </div>
        <x-ad-slot position="sidebar_sticky" />
      </div>
    </aside>
  </div>

  <!-- ===== ARBEITGEBER-CTA ===== -->
  <section class="mt-12 md:mt-16 card p-5 md:p-8 bg-brand-50 border-brand-100">
    <div class="lg:flex lg:items-start lg:gap-10">
      <div class="flex-1 max-w-prose">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.jobs.cta.headline') }}</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.jobs.cta.text') }}</p>
        <ul class="mt-4 space-y-2 text-zinc-700">
          @foreach($benefits as $benefit)
            <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" /><span>{{ $benefit }}</span></li>
          @endforeach
        </ul>
      </div>
      <div class="mt-6 lg:mt-0 lg:w-72 shrink-0 card p-5">
        <p class="text-sm text-zinc-500">{{ __('portal.jobs.cta.single_ad') }}</p>
        <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900">99 €<span class="text-base font-normal text-zinc-500"> {{ __('portal.jobs.cta.price_period') }}</span></p>
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.cta.price_hint') }}</p>
        <a href="#" class="mt-4 btn-primary w-full">{{ __('portal.jobs.cta.button') }}</a>
        <a href="#" class="mt-2 btn-ghost w-full">{{ __('portal.jobs.cta.prices') }}</a>
        <p class="mt-3 pt-3 border-t border-zinc-200 text-sm text-zinc-500">{{ __('portal.jobs.cta.questions') }} <a href="mailto:info@widimedia.com" class="text-brand hover:underline">{{ __('portal.jobs.cta.write_us') }}</a> {{ __('portal.jobs.cta.response_time') }}</p>
      </div>
    </div>
  </section>

  <!-- ===== JOB-MAIL (Mobile/Tablet) ===== -->
  <section class="mt-12 md:mt-16 card p-5 md:p-8 xl:hidden">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.jobs.alert.heading_mobile') }}</h2>
    <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.jobs.alert.text_mobile') }}</p>
    <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]" onsubmit="return false">
      <label class="sr-only" for="alert-ort">{{ __('portal.layout.search_form.where_placeholder') }}</label>
      <input id="alert-ort" class="input" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}">
      <label class="sr-only" for="alert-mail-m">{{ __('portal.jobs.alert.email_label') }}</label>
      <input id="alert-mail-m" type="email" class="input" placeholder="{{ __('portal.jobs.alert.email_placeholder') }}">
      <button type="button" class="btn-primary">{{ __('portal.jobs.alert.button_mobile') }}</button>
    </form>
  </section>

  <!-- ===== SEO-LINKLISTEN ===== -->
  @php
      $linkLists = [
          __('portal.jobs.links.by_type') => collect($employmentTypes)
              ->filter(fn ($type) => $type['count'] > 0)
              ->map(fn ($type, $key) => [$type['label'], $type['count'], route('portal.jobs.index', ['type' => $key])])
              ->values(),
          __('portal.jobs.links.by_city') => $cities
              ->map(fn ($city) => [$city->name, $city->jobs_count, route('portal.jobs.index', ['city' => $city->slug])])
              ->take(8),
      ];
  @endphp
  @foreach($linkLists as $heading => $links)
    @continue($links->isEmpty())
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $heading }}</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($links as [$label, $count, $url])
          <li><a href="{{ $url }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $label }}</span><span class="text-sm text-zinc-500">{{ $count }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endforeach

  <!-- ===== SEO-TEXT ===== -->
  <section class="mt-12 md:mt-16 max-w-prose">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.jobs.seo.heading') }}</h2>
    <p class="mt-4 text-base leading-relaxed">{{ __('portal.jobs.seo.text_choice') }}</p>
    <p class="mt-4 text-base leading-relaxed">{{ __('portal.jobs.seo.text_salary') }}</p>
  </section>

</div>
@endsection
