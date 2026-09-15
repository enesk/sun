{{--
    Stellenanzeigen im Theme sun-v2 (Vorlage elektrikerportal-jobs.html).

    Vorerst reine Gestaltung: Stellen, Zahlen, Filter und Linklisten sind
    Beispielinhalte aus der Vorlage und NICHT an PublicJobController
    angebunden. Filter-Knoepfe, Job-Mail und Pagination haben noch keine
    Funktion — die Anbindung folgt in einem eigenen Schritt.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');

    $jobs = [
        ['initials' => 'EM', 'title' => 'Elektroniker für Energie- und Gebäudetechnik (m/w/d)', 'company' => 'Elektro Müller GmbH', 'badge' => 'Top-Anzeige', 'top' => true, 'location' => '20251 Hamburg · 3 km', 'type' => 'Vollzeit, unbefristet', 'salary' => '3.400 – 4.100 € / Monat', 'tags' => ['Firmenwagen', 'Meisterbetrieb', 'Weiterbildung bezahlt'], 'age' => 'Vor 2 Tagen'],
        ['initials' => 'ES', 'title' => 'Obermonteur Elektrotechnik (m/w/d)', 'company' => 'Elbstrom Elektrotechnik', 'badge' => 'Neu', 'top' => false, 'location' => '22767 Hamburg · 5 km', 'type' => 'Vollzeit', 'salary' => 'ab 4.200 € / Monat', 'tags' => ['Führungsrolle', 'Firmenwagen', '30 Tage Urlaub'], 'age' => 'Vor 3 Tagen'],
        ['initials' => 'EN', 'title' => 'Ausbildung Elektroniker/in Energie- und Gebäudetechnik', 'company' => 'Elektro Nord Barmbek', 'badge' => null, 'top' => false, 'location' => '22305 Hamburg · 6 km', 'type' => 'Ausbildung ab 08/2027', 'salary' => '1.000 – 1.300 € / Monat', 'tags' => ['Ausbildung', 'Übernahme geplant', 'Fahrtkosten'], 'age' => 'Vor 1 Woche'],
        ['initials' => 'WB', 'title' => 'Servicetechniker Wallbox & Ladeinfrastruktur (m/w/d)', 'company' => 'Watt & Bolt GmbH', 'badge' => null, 'top' => false, 'location' => '22765 Hamburg · 7 km', 'type' => 'Vollzeit', 'salary' => '3.600 – 4.400 € / Monat', 'tags' => ['E-Mobilität', 'Servicewagen', 'Keine Montage auswärts'], 'age' => 'Vor 1 Woche'],
        ['initials' => 'LA', 'title' => 'Elektrohelfer (m/w/d) in Teilzeit', 'company' => 'Lichtwerk Altona', 'badge' => null, 'top' => false, 'location' => '22765 Hamburg · 6 km', 'type' => 'Teilzeit, 25 Std.', 'salary' => null, 'tags' => ['Quereinstieg möglich', 'Feste Arbeitszeiten'], 'age' => 'Vor 2 Wochen'],
        ['initials' => 'KS', 'title' => 'KNX-Programmierer / Systemintegrator (m/w/d)', 'company' => 'KNX Systeme Hamburg', 'badge' => null, 'top' => false, 'location' => '20537 Hamburg · 9 km', 'type' => 'Vollzeit, hybrid', 'salary' => '4.000 – 5.200 € / Monat', 'tags' => ['KNX', 'Homeoffice möglich', 'Zertifizierung bezahlt'], 'age' => 'Vor 2 Wochen'],
    ];

    $jobCount = 1842;

    $filters = [
        ['label' => __('portal.layout.filters.sort', ['sortierung' => __('portal.layout.filters.sort_newest')]), 'dropdown' => true],
        ['label' => __('portal.jobs.filters.type'), 'dropdown' => true],
        ['label' => __('portal.jobs.filters.with_salary'), 'dropdown' => false],
        ['label' => __('portal.jobs.filters.apprenticeship'), 'dropdown' => false],
        ['label' => __('portal.jobs.filters.career_change'), 'dropdown' => false],
        ['label' => __('portal.jobs.filters.new_this_week'), 'dropdown' => false],
    ];

    $professions = [
        ['Elektroniker EuG', 412], ['Obermonteur', 68], ['Servicetechniker', 144], ['Meister / Techniker', 91],
        ['Ausbildung', 176], ['Elektrohelfer', 83], ['KNX / Automation', 57], ['Bauleitung', 34],
    ];

    $cities = [
        ['Hamburg', 248], ['Berlin', 312], ['München', 207], ['Köln', 164],
        ['Frankfurt am Main', 131], ['Stuttgart', 128], ['Düsseldorf', 96], ['Leipzig', 74],
    ];

    $benefits = [
        __('portal.jobs.cta.benefit_duration'),
        __('portal.jobs.cta.benefit_inbox'),
        __('portal.jobs.cta.benefit_google'),
        __('portal.jobs.cta.benefit_profile'),
    ];

    $pageLink = 'size-11 rounded-full hover:bg-zinc-100 text-zinc-700 font-medium flex items-center justify-center';
@endphp

@section('title', __('portal.jobs.meta_title').' | '.$portalName)
@section('meta_description', trans_choice('portal.jobs.meta_description', $jobCount, ['anzahl' => number_format($jobCount, 0, ',', '.')]))

@section('content')

<!-- ===== HERO mit Job-Suche ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5">
      <a href="{{ route('home') }}" class="hover:text-brand">{{ __('portal.layout.breadcrumb.home') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ __('portal.layout.header.jobs') }}</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.jobs.headline') }}</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ trans_choice('portal.jobs.meta_description', $jobCount, ['anzahl' => number_format($jobCount, 0, ',', '.')]) }}</p>
    </div>

    <form action="{{ route('portal.jobs.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6">
      <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
        <div class="relative">
          <label class="sr-only" for="q">{{ __('portal.jobs.search.what_label') }}</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" class="input pl-10" placeholder="{{ __('portal.jobs.search.what_placeholder') }}">
        </div>
        <div class="relative">
          <label class="sr-only" for="ort">{{ __('portal.layout.search_form.where_label') }}</label>
          <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="ort" name="ort" class="input pl-10" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="postal-code">
        </div>
        <select name="umkreis" class="input hidden lg:block lg:w-32" aria-label="{{ __('portal.layout.search_form.radius_label') }}"><option>{{ __('portal.layout.search_form.radius_option', ['km' => 10]) }}</option><option selected>{{ __('portal.layout.search_form.radius_option', ['km' => 25]) }}</option><option>{{ __('portal.layout.search_form.radius_option', ['km' => 50]) }}</option></select>
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
    <p class="text-sm text-zinc-500">{{ __('portal.jobs.sorted_by_date') }}</p>
  </div>

  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="{{ __('portal.layout.filters.label') }}">
    @foreach($filters as $filter)
      <button type="button" class="pill-link whitespace-nowrap">{{ $filter['label'] }}@if($filter['dropdown']) <x-sun.icon name="chevron-down" class="size-4" />@endif</button>
    @endforeach
  </div>

  <div class="mt-6 grid gap-8 xl:grid-cols-[1fr_18rem]">
    <div class="flex flex-col gap-4 min-w-0">

      @foreach($jobs as $job)
        @if($loop->index === 3)
          <x-ad-slot position="listing_between_results" />
        @endif
        <article class="card-interactive p-5 flex flex-col lg:flex-row lg:items-start gap-4 {{ $job['top'] ? 'border-l-4 border-l-brand' : '' }}">
          <div class="flex-1 min-w-0 flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <span class="size-12 rounded-2xl bg-brand-50 text-brand-700 font-bold flex items-center justify-center shrink-0" aria-hidden="true">{{ $job['initials'] }}</span>
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <h2 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="#" class="hover:text-brand">{{ $job['title'] }}</a></h2>
                  @if($job['badge'])
                    <span class="pill-brand shrink-0">{{ $job['badge'] }}</span>
                  @endif
                </div>
                <p class="mt-1 text-zinc-700">{{ $job['company'] }}</p>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <dl class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-zinc-500">
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.location') }}</dt><x-sun.icon name="map-pin" class="size-4 shrink-0" /><dd>{{ $job['location'] }}</dd></div>
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.type') }}</dt><x-sun.icon name="clock" class="size-4 shrink-0" /><dd>{{ $job['type'] }}</dd></div>
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.salary') }}</dt><x-sun.icon name="euro" class="size-4 shrink-0" /><dd class="{{ $job['salary'] ? 'text-zinc-900 font-medium' : '' }}">{{ $job['salary'] ?? __('portal.jobs.card.salary_none') }}</dd></div>
              </dl>
              <div class="mt-3 flex flex-wrap gap-2">
                @foreach($job['tags'] as $tag)
                  <span class="pill">{{ $tag }}</span>
                @endforeach
              </div>
              <p class="mt-3 text-sm text-zinc-500">{{ $job['age'] }}</p>
            </div>
          </div>
          <div class="flex lg:flex-col gap-2 lg:w-44 shrink-0">
            <a href="#" class="btn-primary flex-1">{{ __('portal.jobs.card.apply') }}</a>
            <a href="#" class="btn-secondary flex-1">{{ __('portal.jobs.card.details') }}</a>
          </div>
        </article>
      @endforeach

      <nav aria-label="{{ __('portal.layout.pagination.label') }}" class="mt-4 flex flex-wrap items-center justify-center sm:justify-between gap-3">
        <span class="btn-ghost opacity-40 pointer-events-none" aria-disabled="true">{{ __('portal.layout.pagination.previous') }}</span>
        <ul class="order-first sm:order-none w-full sm:w-auto flex items-center justify-center gap-1">
          <li><a href="#" aria-current="page" class="size-11 rounded-full bg-brand text-white font-semibold flex items-center justify-center">1</a></li>
          <li><a href="#" class="{{ $pageLink }}">2</a></li>
          <li class="hidden sm:block"><a href="#" class="{{ $pageLink }}">3</a></li>
          <li class="text-zinc-400 px-1">…</li>
          <li><a href="#" class="{{ $pageLink }}">92</a></li>
        </ul>
        <a href="#" class="btn-ghost">{{ __('portal.layout.pagination.next') }}</a>
      </nav>
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
  @foreach([__('portal.jobs.links.by_profession') => $professions, __('portal.jobs.links.by_city') => $cities] as $heading => $links)
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $heading }}</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($links as [$label, $count])
          <li><a href="#" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $label }}</span><span class="text-sm text-zinc-500">{{ $count }}</span></a></li>
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
