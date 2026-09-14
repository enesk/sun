{{--
    Suchergebnisseite /firmen im Theme sun-v2 (Vorlage elektrikerportal-suche.html).
    Daten: CompanyController@index (fuer alle Themes) plus $search aus
    App\Themes\SunV2\SearchViewComposer.
--}}
@extends('layouts.sun')

@php
    $filters = $search['filters'];
    $hasMap = $search['pins']['items'] !== [];
    $betweenAd = \App\View\Components\AdSlot::hasSlotsForPosition('listing_between_results');
    $skyscraperAd = $hasMap && \App\View\Components\AdSlot::hasSlotsForPosition('sidebar_sticky');
    $center = $search['pins']['center'];
@endphp

@section('title', $search['heading'].' | '.($currentTenant->name ?? config('app.name')))
@section('meta_description', $search['heading'].' – mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.')
@if(request()->hasAny(['q', 'ort', 'umkreis', 'sort', 'city', 'category', 'min_rating', 'rated', 'open_now', 'page']))
    @section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.companies.index'))

@section('content')

<!-- ========== KOMPAKTE SUCHE (sticky unter Header) ========== -->
<form action="{{ route('portal.companies.index') }}" method="get" role="search" class="sticky top-16 z-30 bg-white border-b border-zinc-200 py-3">
  <input type="hidden" name="sort" value="{{ $filters['sort'] ?: 'rating' }}">
  <div class="container-portal grid gap-3 grid-cols-[1fr_auto] sm:grid-cols-[1fr_1fr_auto] lg:grid-cols-[1fr_1fr_auto_auto]">
    <label class="sr-only" for="q">Was suchst du?</label>
    <div class="relative">
      <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
      <input id="q" name="q" class="input pl-10" value="{{ $filters['q'] }}" placeholder="{{ $search['placeholder'] }}">
    </div>
    <div class="hidden sm:block relative">
      <label class="sr-only" for="ort">Wo?</label>
      <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
      <input id="ort" name="ort" class="input pl-10" value="{{ $filters['ort'] }}" placeholder="Ort oder PLZ">
    </div>
    <select name="umkreis" class="input hidden lg:block lg:w-32" aria-label="Umkreis">
      @foreach(\App\Services\CompanyLocationSearch::RADII as $km)
        <option value="{{ $km }}" @selected((int) ($filters['umkreis'] ?: \App\Services\CompanyLocationSearch::DEFAULT_RADIUS) === $km)>{{ $km }} km</option>
      @endforeach
    </select>
    <button type="submit" class="btn-primary px-4 sm:px-5">Finden</button>
  </div>
</form>

<div class="container-portal pt-6 pb-12 md:pb-16">

  <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5">
    <a href="{{ route('home') }}" class="hover:text-brand hidden sm:inline">Start</a><x-sun.icon name="chevron-right" class="hidden sm:block size-4 text-zinc-400" />
    @if($search['crumb'])
      <a href="{{ route('portal.companies.index') }}" class="hover:text-brand">{{ config('themes.sun-v2.search.branch_plural') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400" />
      <span class="text-zinc-900">{{ $search['crumb'] }}</span>
    @else
      <span class="text-zinc-900">{{ config('themes.sun-v2.search.branch_plural') }}</span>
    @endif
  </nav>

  <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $search['heading'] }}</h1>
  @if($companies->total() > 0)
    <p class="mt-2 text-zinc-500">{{ $search['subtitle'] }}</p>
  @endif

  <!-- ========== FILTERLEISTE (horizontal scrollbar auf Mobile) ========== -->
  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="Filter">
    <button type="button" class="pill-link whitespace-nowrap" data-disclosure="filter" aria-expanded="false" aria-controls="filter-sort">Sortierung: {{ $search['sortLabel'] }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    <button type="button" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $filters['city'] !== '']) data-disclosure="filter" aria-expanded="false" aria-controls="filter-city">{{ $filters['city'] ?: 'Stadt' }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    @foreach($search['toggles'] as $toggle)
      <a href="{{ $toggle['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $toggle['active']]) aria-pressed="{{ $toggle['active'] ? 'true' : 'false' }}" role="button">{{ $toggle['label'] }}</a>
    @endforeach
  </div>
  <div id="filter-sort" hidden class="mt-3 flex flex-wrap gap-2">
    @foreach($search['sortLinks'] as $link)
      <a href="{{ $link['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $link['active']]) @if($link['active']) aria-current="true" @endif>{{ $link['label'] }}</a>
    @endforeach
  </div>
  <div id="filter-city" hidden class="mt-3 flex flex-wrap gap-2">
    @if($filters['city'] !== '')
      <a href="{{ $search['resetCityUrl'] }}" class="pill-link whitespace-nowrap text-brand">Alle Städte</a>
    @endif
    @foreach($search['cityLinks']['chips'] as $chip)
      <a href="{{ $chip['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $chip['active']])>{{ $chip['label'] }} <span class="text-zinc-400 font-normal">{{ number_format($chip['count'], 0, ',', '.') }}</span></a>
    @endforeach
  </div>

  <div @class(['mt-6 grid gap-8', 'xl:grid-cols-[1fr_18rem]' => $hasMap, 'max-w-3xl' => ! $hasMap])>

    <!-- ========== ERGEBNISLISTE ========== -->
    <div class="flex flex-col gap-4 min-w-0">
      @forelse($companies as $company)
        <x-sun.listing-card :company="$company" :distance="$search['distances'][$company->city_id] ?? null" />

        @if($loop->iteration === 3 && $betweenAd)
          <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:280px">
            <span class="block text-xs text-zinc-400 px-3 pt-2">Anzeige</span>
            <x-ad-slot position="listing_between_results" />
          </div>
        @endif
      @empty
        @include('pages.companies._empty-state')
      @endforelse

      <x-sun.pagination :paginator="$companies" :pages="$search['pages']" />
    </div>

    <!-- ========== RECHTE SPALTE (nur Desktop, nur mit Geodaten) ========== -->
    @if($hasMap)
      <aside class="hidden xl:block">
        <div class="sticky top-36 flex flex-col gap-4">
          <div class="card overflow-hidden">
            <div class="aspect-[4/3] bg-zinc-100 relative" role="img" aria-label="Karte mit {{ count($search['pins']['items']) }} Ergebnissen">
              <x-sun.icon name="map-lines" class="absolute inset-0 size-full text-zinc-200" viewBox="0 0 400 300" />
              @foreach($search['pins']['items'] as $pin)
                <span class="absolute size-8 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand text-white text-xs font-bold flex items-center justify-center ring-4 ring-white" style="left:{{ $pin['left'] }}%;top:{{ $pin['top'] }}%">{{ $pin['number'] }}</span>
              @endforeach
            </div>
            <div class="p-4">
              <a href="https://www.openstreetmap.org/#map=11/{{ round($center['lat'], 4) }}/{{ round($center['lng'], 4) }}" class="btn-secondary w-full" target="_blank" rel="noopener noreferrer">Karte öffnen</a>
            </div>
          </div>
          @if($skyscraperAd)
            <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:600px">
              <span class="block text-xs text-zinc-400 px-3 pt-2">Anzeige</span>
              <x-ad-slot position="sidebar_sticky" />
            </div>
          @endif
        </div>
      </aside>
    @endif
  </div>

  <!-- ========== STÄDTE-CHIPS ========== -->
  @if($search['cityLinks']['chips'] !== [])
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">Ergebnisse nach Stadt eingrenzen</h2>
      <div class="mt-4 flex flex-wrap gap-2">
        @foreach($search['cityLinks']['chips'] as $chip)
          <a href="{{ $chip['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $chip['active']])>{{ $chip['label'] }} <span class="text-zinc-400 font-normal">{{ number_format($chip['count'], 0, ',', '.') }}</span></a>
        @endforeach
        @if($search['cityLinks']['more'] > 0)
          <a href="{{ route('portal.cities.index') }}" class="pill-link whitespace-nowrap text-brand">{{ $search['cityLinks']['more'] }} weitere Städte</a>
        @endif
      </div>
    </section>
  @endif

  <!-- ========== SEO-TEXT ========== -->
  <section class="mt-12 md:mt-16 max-w-prose">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ $search['seo']['headline'] }}</h2>
    @foreach($search['seo']['paragraphs'] as $paragraph)
      <p class="mt-4 text-base leading-relaxed">{{ $paragraph }}</p>
    @endforeach
  </section>

</div>

@endsection
