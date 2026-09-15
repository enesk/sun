{{--
    Stadtseite /staedte/{slug} im Theme sun-v2 (Vorlage elektrikerportal-stadtseite.html).
    Daten: PublicCityController::show() plus $citySun aus App\Themes\SunV2\CityViewComposer.
    Robots/Canonical setzt App\Services\Seo\SeoService::forCityPage() (#7), Title/H1 SeoService::cityMeta() (#10).
    Local Hub ($localHub): CityContentResolver::forCity(), gerendert von x-city.intro / x-city.faq (#12).
--}}
@extends('layouts.sun')

@php
    $filters = $citySun['filters'];
    $betweenAd = \App\View\Components\AdSlot::hasSlotsForPosition('listing_between_results');
@endphp

{{-- Title/Description/H1 aus SeoService::cityMeta() (#10) --}}
@section('title', $metaTitle)
@section('meta_description', $metaDescription)

@section('content')

<form action="{{ route('portal.companies.index') }}" method="get" role="search" class="sticky top-16 z-30 bg-white border-b border-zinc-200 py-3">
  <input type="hidden" name="sort" value="rating">
  <div class="container-portal grid gap-3 grid-cols-[1fr_auto] sm:grid-cols-[1fr_1fr_auto] lg:grid-cols-[1fr_1fr_auto_auto]">
    <div class="relative">
      <label class="sr-only" for="q">{{ __('portal.layout.search_form.what_label') }}</label>
      <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
      <input id="q" name="q" class="input pl-10" value="{{ $filters['q'] }}" placeholder="{{ __('portal.layout.search_form.placeholder') }}">
    </div>
    <div class="hidden sm:block relative">
      <label class="sr-only" for="ort">{{ __('portal.layout.search_form.where_label') }}</label>
      <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
      <input id="ort" name="ort" class="input pl-10" value="{{ $city->name }}" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}">
    </div>
    <select name="umkreis" class="input hidden lg:block lg:w-32" aria-label="{{ __('portal.layout.search_form.radius_label') }}">
      @foreach(\App\Services\CompanyLocationSearch::RADII as $km)
        <option value="{{ $km }}" @selected($km === \App\Services\CompanyLocationSearch::DEFAULT_RADIUS)>{{ __('portal.layout.search_form.radius_option', ['km' => $km]) }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn-primary px-4 sm:px-5">{{ __('portal.layout.search_form.submit') }}</button>
  </div>
</form>

<div class="container-portal pt-6 pb-12 md:pb-16">
  <x-sun.breadcrumb :items="\App\Support\Breadcrumb::forCity($city)" />

  <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $citySun['heading'] }}</h1>
  @if($citySun['intro'])
    <p class="mt-3 max-w-prose text-base leading-relaxed">{{ $citySun['intro'] }}</p>
  @endif

  {{-- Local Hub: Einleitung + Stadtteile, nur auf der indexierbaren Seite 1 ($localHub sonst null, #12) --}}
  @if($localHub)
    <x-city.intro :city="$city" :content="$localHub" />
  @endif

  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="{{ __('portal.layout.filters.label') }}">
    <button type="button" class="pill-link whitespace-nowrap" data-disclosure="filter" aria-expanded="false" aria-controls="city-filter-sort">{{ __('portal.layout.filters.sort', ['sortierung' => $citySun['sortLabel']]) }} <x-sun.icon name="chevron-down" class="size-4" /></button>
    @foreach($citySun['toggles'] as $toggle)
      <a href="{{ $toggle['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $toggle['active']]) aria-pressed="{{ $toggle['active'] ? 'true' : 'false' }}" role="button">{{ $toggle['label'] }}</a>
    @endforeach
  </div>
  <div id="city-filter-sort" hidden class="mt-3 flex flex-wrap gap-2">
    @foreach($citySun['sortLinks'] as $link)
      <a href="{{ $link['url'] }}" @class(['pill-link whitespace-nowrap', 'border-brand text-brand' => $link['active']])>{{ $link['label'] }}</a>
    @endforeach
  </div>

  {{-- Ohne Geodaten keine Karte: Liste einspaltig, wie in der Suche --}}
  <div class="mt-6 grid gap-8 max-w-3xl">
    <div class="flex flex-col gap-4 min-w-0">
      @forelse($companies as $company)
        <x-sun.listing-card :company="$company" />

        @if($loop->iteration === 3 && $betweenAd)
          <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:280px">
            <span class="block text-xs text-zinc-400 px-3 pt-2">{{ __('portal.layout.ad_label') }}</span>
            <x-ad-slot position="listing_between_results" />
          </div>
        @endif
      @empty
        <div class="card p-6 md:p-10">
          <div class="flex flex-col sm:flex-row sm:items-start gap-5">
            <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
            <div class="flex-1 min-w-0">
              <h2 class="text-2xl font-semibold text-zinc-900">{{ $citySun['hasFilters'] ? __('portal.empty.search.heading') : __('portal.empty.city.heading', ['stadt' => $city->name]) }}</h2>
              <p class="mt-2 text-zinc-700 leading-relaxed">
                @if($citySun['hasFilters'])
                  {{ __('portal.empty.city.text_filtered', ['stadt' => $city->name]) }}
                @else
                  {{ __('portal.empty.city.text', ['stadt' => $city->name]) }}
                @endif
              </p>
              <div class="mt-6 flex flex-col sm:flex-row gap-2">
                @if($citySun['hasFilters'])
                  <a href="{{ $citySun['resetUrl'] }}" class="btn-primary">{{ __('portal.layout.filters.reset') }}</a>
                @endif
                <a href="{{ route('portal.companies.index', ['sort' => 'rating']) }}" @class(['btn-secondary' => $citySun['hasFilters'], 'btn-primary' => ! $citySun['hasFilters']])>{{ __('portal.empty.city.show_all') }}</a>
                @unless($citySun['hasFilters'])
                  <a href="{{ route('portal.companies.create') }}" class="btn-ghost">{{ __('portal.empty.city.signup_button') }}</a>
                @endunless
              </div>
            </div>
          </div>
        </div>
      @endforelse

      <x-sun.pagination :paginator="$companies" :pages="$citySun['pages']" />
    </div>
  </div>

  {{-- FAQ des Local Hubs; FAQPage-JSON-LD aus derselben Liste setzt SeoService::forCityPage() (#12) --}}
  @if($localHub)
    <x-city.faq :city="$city" :faqs="$localHub['faqs']" />
  @endif

  @if($citySun['services'] !== [])
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.city.services_heading', ['stadt' => $city->name]) }}</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($citySun['services'] as $service)
          <li><a href="{{ $service['url'] }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $service['label'] }}</span><span class="text-sm text-zinc-500">{{ number_format($service['count'], 0, ',', '.') }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endif

  @if($citySun['nearby']->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.city.nearby_heading', ['stadt' => $city->name]) }}</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($citySun['nearby'] as $other)
          <li><a href="{{ \App\Support\CityUrl::show($other) }}" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $other->name }}</span><span class="text-sm text-zinc-500">{{ number_format($other->companies_count, 0, ',', '.') }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endif

  @if($citySun['posts']->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <x-sun.section-heading :title="__('portal.city.guide_heading', ['stadt' => $city->name])" :href="route('portal.blog.index')" :link="__('portal.city.guide_all')" />
      <div class="grid sm:grid-cols-2 gap-4">
        @foreach($citySun['posts'] as $post)
          <a href="{{ route('portal.blog.show', $post->slug) }}" class="card-interactive p-5 flex flex-col gap-2">
            @if($post->category)
              <span class="pill self-start">{{ $post->category->name }}</span>
            @endif
            <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $post->title }}</h3>
            <p class="text-sm text-zinc-500 line-clamp-2">{{ $post->excerpt_or_truncated }}</p>
            @if($post->reading_time_minutes)
              <span class="text-sm text-zinc-500 mt-auto pt-1">{{ __('portal.layout.reading_time', ['minuten' => $post->reading_time_minutes]) }}</span>
            @endif
          </a>
        @endforeach
      </div>
    </section>
  @endif

  <section class="mt-12 md:mt-16 max-w-prose">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ $citySun['seo']['headline'] }}</h2>
    @foreach($citySun['seo']['paragraphs'] as $paragraph)
      <p class="mt-4 text-base leading-relaxed">{{ $paragraph }}</p>
    @endforeach
  </section>
</div>
@endsection
