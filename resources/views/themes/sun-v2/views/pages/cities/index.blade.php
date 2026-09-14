{{--
    Staedteuebersicht /staedte im Theme sun-v2, im Aufbau der Stadtseite (pages/cities/show).
    Daten: $citiesSun aus App\Themes\SunV2\CityIndexViewComposer.
    Ohne Land: groesste Staedte + Bundeslaender; mit ?land=: alle Orte des Landes von A bis Z.
    Beide Varianten sind indexierbar, Canonical jeweils auf sich selbst.
--}}
@extends('layouts.sun')

@php
    $plural = config('themes.sun-v2.search.branch_plural');
    $portalName = $currentTenant->name ?? config('app.name');
    $canonical = $citiesSun['land']
        ? route('portal.cities.index', ['land' => $citiesSun['landSlug']])
        : route('portal.cities.index');
@endphp

@section('title', $citiesSun['heading'].' | '.$portalName)
@section('meta_description', $citiesSun['intro'] ?? $citiesSun['heading'])
@section('canonical', $canonical)
@if($citiesSun['term'] !== '')
    @section('meta_robots', 'noindex, follow')
@endif

@section('content')

<!-- ========== HERO ========== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
      <a href="{{ route('home') }}" class="hover:text-brand">Start</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      @if($citiesSun['land'])
        <a href="{{ route('portal.cities.index') }}" class="hover:text-brand">Städte</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ $citiesSun['land'] }}</span>
      @else
        <span class="text-zinc-900">Städte</span>
      @endif
    </nav>

    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $citiesSun['heading'] }}</h1>
      @if($citiesSun['intro'])
        <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ $citiesSun['intro'] }}</p>
      @endif
    </div>

    <form action="{{ route('portal.cities.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6 md:mt-8 max-w-2xl">
      @if($citiesSun['land'])
        <input type="hidden" name="land" value="{{ $citiesSun['landSlug'] }}">
      @endif
      <div class="grid gap-3 grid-cols-[1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="suche">Stadt oder PLZ</label>
          <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="suche" name="suche" type="search" class="input pl-10" value="{{ $citiesSun['term'] }}" placeholder="{{ $citiesSun['land'] ? 'Ort in '.$citiesSun['land'].' oder PLZ' : 'Stadt oder PLZ, z. B. Hamburg' }}" autocomplete="address-level2">
        </div>
        <button type="submit" class="btn-primary px-4 sm:px-5">Stadt finden</button>
      </div>
    </form>

    @if($citiesSun['states'] !== [])
      <div class="mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 flex items-center gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="Bundesland">
        <span class="text-sm text-zinc-500 mr-1 whitespace-nowrap">Bundesland:</span>
        @foreach($citiesSun['states'] as $state)
          <a href="{{ $state['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $state['active']]) aria-pressed="{{ $state['active'] ? 'true' : 'false' }}" role="button">{{ $state['label'] }}</a>
        @endforeach
      </div>
    @endif

    <dl class="mt-8 md:mt-10 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 max-w-2xl">
      <div><dt class="text-sm text-zinc-500">Betriebe</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($citiesSun['totalCompanies'], 0, ',', '.') }}</dd></div>
      <div><dt class="text-sm text-zinc-500">{{ $citiesSun['land'] ? 'Orte' : 'Städte' }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($citiesSun['totalCities'], 0, ',', '.') }}</dd></div>
      @unless($citiesSun['land'])
        <div><dt class="text-sm text-zinc-500">Bundesländer</dt><dd class="text-xl font-semibold text-zinc-900">{{ count($citiesSun['states']) }}</dd></div>
      @endunless
    </dl>
  </div>
</section>

<div class="container-portal pt-10 md:pt-12 pb-12 md:pb-16">

  @if($citiesSun['results'] !== null)
    <section class="mb-12 md:mb-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $citiesSun['results']->isEmpty() ? 'Keine Stadt' : ($citiesSun['results']->count() === \App\Themes\SunV2\CityIndexViewComposer::SEARCH_LIMIT ? 'Mindestens '.\App\Themes\SunV2\CityIndexViewComposer::SEARCH_LIMIT.' Orte' : $citiesSun['results']->count().' '.($citiesSun['results']->count() === 1 ? 'Ort' : 'Orte')) }} zu „{{ $citiesSun['term'] }}“</h2>
      @if($citiesSun['results']->isEmpty())
        <div class="mt-4 card p-6 md:p-10 max-w-3xl">
          <div class="flex flex-col sm:flex-row sm:items-start gap-5">
            <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
            <div class="flex-1 min-w-0">
              <p class="text-zinc-700 leading-relaxed">Für „{{ $citiesSun['term'] }}“ ist {{ $citiesSun['land'] ? 'in '.$citiesSun['land'].' ' : '' }}kein Ort mit eingetragenem Betrieb dabei. Prüf die Schreibweise oder such nach der nächstgrößeren Stadt.</p>
              <div class="mt-6"><a href="{{ $citiesSun['land'] ? route('portal.cities.index', ['land' => $citiesSun['landSlug']]) : route('portal.cities.index') }}" class="btn-secondary">Suche zurücksetzen</a></div>
            </div>
          </div>
        </div>
      @else
        <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
          @foreach($citiesSun['results'] as $city)
            <li><a href="{{ route('portal.cities.show', $city['slug']) }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="min-w-0 truncate"><span class="font-medium text-zinc-900">{{ $city['name'] }}</span> <span class="text-sm text-zinc-500">{{ $city['zip'] }}</span></span><span class="text-sm text-zinc-500">{{ number_format($city['count'], 0, ',', '.') }}</span></a></li>
          @endforeach
        </ul>
      @endif
    </section>
  @endif

  @if($citiesSun['top']->isEmpty())
    <div class="card p-6 md:p-10 max-w-3xl">
      <div class="flex flex-col sm:flex-row sm:items-start gap-5">
        <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
        <div class="flex-1 min-w-0">
          <h2 class="text-2xl font-semibold text-zinc-900">Noch keine Städte</h2>
          <p class="mt-2 text-zinc-700 leading-relaxed">Es ist noch kein Betrieb mit Ort eingetragen.</p>
          <div class="mt-6"><a href="{{ route('portal.companies.index', ['sort' => 'rating']) }}" class="btn-primary">Alle {{ $plural }} anzeigen</a></div>
        </div>
      </div>
    </div>
  @else
    <section>
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $citiesSun['land'] ? 'Größte Orte in '.$citiesSun['land'] : 'Größte Städte' }}</h2>
      <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @foreach($citiesSun['top'] as $city)
          <a href="{{ route('portal.cities.show', $city['slug']) }}" class="card-interactive p-4 flex items-center gap-3 min-w-0">
            <span class="size-10 shrink-0 rounded-xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="map-pin" class="icon" /></span>
            <span class="min-w-0">
              <span class="block font-semibold text-zinc-900 truncate">{{ $city['name'] }}</span>
              <span class="block text-sm text-zinc-500">{{ number_format($city['count'], 0, ',', '.') }} {{ $city['count'] === 1 ? 'Betrieb' : 'Betriebe' }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @if($citiesSun['land'] && $citiesSun['groups']->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">Alle Orte in {{ $citiesSun['land'] }} von A bis Z</h2>
      <nav aria-label="Buchstaben" class="mt-4 flex flex-wrap gap-2">
        @foreach($citiesSun['groups']->keys() as $letter)
          <a href="#buchstabe-{{ $letter }}" class="pill-link min-w-11 justify-center">{{ $letter }}</a>
        @endforeach
      </nav>
      @foreach($citiesSun['groups'] as $letter => $group)
        <div id="buchstabe-{{ $letter }}" class="mt-8 scroll-mt-40">
          <h3 class="text-lg font-semibold text-zinc-900 border-b border-zinc-200 pb-2">{{ $letter }}</h3>
          <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
            @foreach($group as $city)
              <li><a href="{{ route('portal.cities.show', $city['slug']) }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900 truncate">{{ $city['name'] }}</span><span class="text-sm text-zinc-500">{{ number_format($city['count'], 0, ',', '.') }}</span></a></li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </section>
  @elseif(! $citiesSun['land'] && $citiesSun['states'] !== [])
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $plural }} nach Bundesland</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($citiesSun['states'] as $state)
          <li><a href="{{ $state['url'] }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $state['label'] }}</span><span class="text-sm text-zinc-500">{{ number_format($state['count'], 0, ',', '.') }} Orte</span></a></li>
        @endforeach
      </ul>
    </section>
  @endif

  <section class="mt-12 md:mt-16 max-w-prose">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ $citiesSun['seo']['headline'] }}</h2>
    @foreach($citiesSun['seo']['paragraphs'] as $paragraph)
      <p class="mt-4 text-base leading-relaxed">{{ $paragraph }}</p>
    @endforeach
  </section>
</div>
@endsection
