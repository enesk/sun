{{--
    Kategorieseite /kategorien/{slug} im Theme sun-v2, im Aufbau der Stadtseite (pages/cities/show).
    Daten: CategoryController@show (fuer alle Themes gleich): $category, $companies (paginiert),
    $allCategories, $cities (Orte mit Betrieben dieser Leistung), $sort.
    Filter wie im Controller: q, city (Ortsname), sort (rating|newest|name).
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $plural = config('themes.sun-v2.search.branch_plural');
    $count = fn (int $value) => number_format($value, 0, ',', '.');
    $total = $companies->total();
    $activeCity = (string) request('city', '');
    $term = (string) request('q', '');
    $sorts = ['rating' => 'Bewertung', 'newest' => 'Neueste', 'name' => 'Name'];
    $sortKey = array_key_exists($sort, $sorts) ? $sort : 'name';
    $url = fn (array $changes) => route('portal.categories.show', ['slug' => $category->slug, ...array_filter(array_merge(request()->only(['q', 'city', 'sort']), $changes), fn ($v) => $v !== null && $v !== '')]);
    $hasFilters = $term !== '' || $activeCity !== '';
    $heading = $total > 0
        ? $count($total).' '.$category->name.($activeCity !== '' ? ' in '.$activeCity : '')
        : 'Keine Betriebe für '.$category->name.($activeCity !== '' ? ' in '.$activeCity : '');
    $betweenAd = \App\View\Components\AdSlot::hasSlotsForPosition('listing_between_results');
@endphp

@section('title', $heading.' | '.$portalName)
@section('meta_description', $category->description ?: $count((int) $category->companies_count).' Betriebe für '.$category->name.' – mit Bewertungen, Öffnungszeiten und direkter Telefonnummer.')
@if(request()->hasAny(['q', 'city', 'sort', 'page']))
    @section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.categories.show', $category->slug))

@section('content')

<form action="{{ route('portal.categories.show', $category->slug) }}" method="get" role="search" class="sticky top-16 z-30 bg-white border-b border-zinc-200 py-3">
  @if($activeCity !== '')<input type="hidden" name="city" value="{{ $activeCity }}">@endif
  @if(request('sort'))<input type="hidden" name="sort" value="{{ $sortKey }}">@endif
  <div class="container-portal grid gap-3 grid-cols-[1fr_auto]">
    <div class="relative">
      <label class="sr-only" for="q">In {{ $category->name }} suchen</label>
      <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
      <input id="q" name="q" class="input pl-10" value="{{ $term }}" placeholder="In {{ $category->name }} suchen">
    </div>
    <button type="submit" class="btn-primary px-4 sm:px-5">Finden</button>
  </div>
</form>

<div class="container-portal pt-6 pb-12 md:pb-16">
  <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
    <a href="{{ route('home') }}" class="hover:text-brand hidden sm:inline">Start</a><span class="hidden sm:inline"><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /></span>
    <a href="{{ route('portal.categories.index') }}" class="hover:text-brand">Leistungen</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ $category->name }}</span>
  </nav>

  <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $heading }}</h1>
  @if($category->description)
    <p class="mt-3 max-w-prose text-base leading-relaxed">{{ $category->description }}</p>
  @endif

  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="Filter">
    <button type="button" class="pill-link whitespace-nowrap" data-disclosure="filter" aria-expanded="false" aria-controls="category-filter-sort">Sortierung: {{ $sorts[$sortKey] }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    @if($cities->isNotEmpty())
      <button type="button" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $activeCity !== '']) data-disclosure="filter" aria-expanded="false" aria-controls="category-filter-city">{{ $activeCity ?: 'Stadt' }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    @endif
  </div>
  <div id="category-filter-sort" hidden class="mt-3 flex flex-wrap gap-2">
    @foreach($sorts as $key => $label)
      <a href="{{ $url(['sort' => $key, 'page' => null]) }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $key === $sortKey])>{{ $label }}</a>
    @endforeach
  </div>
  @if($cities->isNotEmpty())
    <div id="category-filter-city" hidden class="mt-3 flex flex-wrap gap-2">
      @if($activeCity !== '')
        <a href="{{ $url(['city' => null]) }}" class="pill-link whitespace-nowrap">Alle Orte</a>
      @endif
      @foreach($cities->take(24) as $city)
        <a href="{{ $url(['city' => $activeCity === $city->name ? null : $city->name]) }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $activeCity === $city->name])>{{ $city->name }} <span class="text-zinc-400">{{ $count((int) $city->companies_count) }}</span></a>
      @endforeach
    </div>
  @endif

  <div class="mt-6 grid gap-8 max-w-3xl">
    <div class="flex flex-col gap-4 min-w-0">
      @forelse($companies as $company)
        <x-sun.listing-card :company="$company" />

        @if($loop->iteration === 3 && $betweenAd)
          <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:280px">
            <span class="block text-xs text-zinc-400 px-3 pt-2">Anzeige</span>
            <x-ad-slot position="listing_between_results" />
          </div>
        @endif
      @empty
        <div class="card p-6 md:p-10">
          <div class="flex flex-col sm:flex-row sm:items-start gap-5">
            <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
            <div class="flex-1 min-w-0">
              <h2 class="text-2xl font-semibold text-zinc-900">Keine passenden Betriebe</h2>
              <p class="mt-2 text-zinc-700 leading-relaxed">
                @if($hasFilters)
                  Mit den gesetzten Filtern bleibt für {{ $category->name }} kein Betrieb übrig.
                @else
                  Für {{ $category->name }} ist noch kein Betrieb eingetragen.
                @endif
              </p>
              <div class="mt-6 flex flex-col sm:flex-row gap-2">
                @if($hasFilters)
                  <a href="{{ route('portal.categories.show', $category->slug) }}" class="btn-primary">Filter zurücksetzen</a>
                @endif
                <a href="{{ route('portal.categories.index') }}" @class(['btn-secondary' => $hasFilters, 'btn-primary' => ! $hasFilters])>Alle Leistungen ansehen</a>
              </div>
            </div>
          </div>
        </div>
      @endforelse

      <x-sun.pagination :paginator="$companies" :pages="\App\Themes\SunV2\PageWindow::for($companies)" />
    </div>
  </div>

  @if($allCategories->where('id', '!=', $category->id)->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">Weitere Leistungen</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($allCategories->where('id', '!=', $category->id) as $other)
          <li><a href="{{ route('portal.categories.show', $other->slug) }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $other->name }}</span><span class="text-sm text-zinc-500">{{ $count((int) $other->companies_count) }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endif

  @if($cities->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $category->name }} nach Ort</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($cities as $city)
          <li><a href="{{ \App\Support\CityUrl::show($city) }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $city->name }}</span><span class="text-sm text-zinc-500">{{ $count((int) $city->companies_count) }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endif
</div>
@endsection
