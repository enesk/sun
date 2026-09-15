{{--
    Leistungsuebersicht /kategorien im Theme sun-v2, im Aufbau der Staedteuebersicht.
    Daten: CategoryController@index (fuer alle Themes gleich): $categories (Wurzeln mit
    children und companies_count), $totalCompanies.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $count = fn (int $value) => number_format($value, 0, ',', '.');
@endphp

@section('title', __('portal.categories.index.meta_title').' | '.$portalName)
@section('meta_description', __('portal.categories.index.meta_description', ['anzahl' => $count($totalCompanies)]))
@section('canonical', route('portal.categories.index'))

@section('content')

<!-- ========== HERO ========== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
      <a href="{{ route('home') }}" class="hover:text-brand">{{ __('portal.layout.breadcrumb.home') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ __('portal.layout.footer.services') }}</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.categories.index.heading') }}</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ trans_choice('portal.categories.index.intro', $totalCompanies, ['anzahl' => $count($totalCompanies)]) }}</p>
    </div>
    <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6 md:mt-8">
      <input type="hidden" name="sort" value="rating">
      <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="q">{{ __('portal.layout.search_form.what_label') }}</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" class="input pl-10" placeholder="{{ config('themes.sun-v2.search.placeholder') }}" autocomplete="off">
        </div>
        <div class="relative">
          <label class="sr-only" for="ort">{{ __('portal.layout.search_form.where_label') }}</label>
          <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="ort" name="ort" class="input pl-10" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="postal-code">
        </div>
        <button type="submit" class="btn-primary">{{ __('portal.categories.index.search_button') }}</button>
      </div>
    </form>
    <dl class="mt-8 md:mt-10 grid grid-cols-2 gap-x-6 gap-y-4 max-w-md">
      <div><dt class="text-sm text-zinc-500">{{ __('portal.categories.index.stats.businesses') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ $count($totalCompanies) }}</dd></div>
      <div><dt class="text-sm text-zinc-500">{{ __('portal.categories.index.stats.services') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ $count($categories->count() + $categories->sum(fn ($c) => $c->children->count())) }}</dd></div>
    </dl>
  </div>
</section>

<div class="container-portal py-12 md:py-16 flex flex-col gap-12 md:gap-16">
  @if($categories->isEmpty())
    <div class="card p-6 md:p-10 max-w-3xl">
      <div class="flex flex-col sm:flex-row sm:items-start gap-5">
        <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
        <div class="flex-1 min-w-0">
          <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.categories.index.empty.heading') }}</h2>
          <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.categories.index.empty.text') }}</p>
          <div class="mt-6"><a href="{{ route('portal.companies.index', ['sort' => 'rating']) }}" class="btn-primary">{{ __('portal.empty.search.show_all') }}</a></div>
        </div>
      </div>
    </div>
  @else
    <section>
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.categories.index.all_heading') }}</h2>
      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
        @foreach($categories as $category)
          <div class="card p-5 flex flex-col gap-3">
            <a href="{{ route('portal.categories.show', $category->slug) }}" class="flex items-start gap-4 hover:text-brand">
              <span class="size-11 shrink-0 rounded-xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon :name="config('themes.sun-v2.brand_icon')" class="size-6" /></span>
              <span class="min-w-0 flex-1">
                <span class="block font-semibold text-zinc-900 leading-snug">{{ $category->name }}</span>
                <span class="block text-sm text-zinc-500 mt-1">{{ trans_choice('portal.categories.index.count', (int) $category->companies_count, ['anzahl' => $count((int) $category->companies_count)]) }}</span>
              </span>
            </a>
            @if($category->children->isNotEmpty())
              <ul class="flex flex-wrap gap-2 pt-3 border-t border-zinc-200">
                @foreach($category->children as $child)
                  <li><a href="{{ route('portal.categories.show', $child->slug) }}" class="pill-link">{{ $child->name }} <span class="text-zinc-400">{{ $count((int) $child->companies_count) }}</span></a></li>
                @endforeach
              </ul>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif
</div>
@endsection
