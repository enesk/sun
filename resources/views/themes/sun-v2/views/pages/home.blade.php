{{--
    Startseite im Theme sun-v2 (Vorlage sun-v2.html).
    Daten: PortalHomeController (fuer alle Themes) plus $sun aus
    App\Themes\SunV2\HomeViewComposer.
--}}
@extends('layouts.sun')

@php
    $portalName = ($currentTenant?->terms ?? \App\Support\Tenancy\TenantTerms::defaults())['portal'];
    $companiesFormatted = number_format($totalCompanies, 0, ',', '.');
    $metaDescription = __('portal.home.meta_description', ['anzahl' => $companiesFormatted]);
@endphp

@section('title', __('portal.home.meta_title').' | '.$portalName)
@section('meta_description', $metaDescription)

@push('scripts')
<script type="application/ld+json">
{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $portalName,
    'url' => route('home'),
    'description' => $metaDescription,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
            '@type' => 'EntryPoint',
            'urlTemplate' => route('portal.companies.index').'?q={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endpush

@section('content')

<!-- ========== HERO ========== -->
<section class="bg-brand-50 py-12 md:py-24">
  <div class="container-portal">
    <div class="max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.home.hero.headline') }}</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ __('portal.home.hero.text', ['anzahl' => $companiesFormatted]) }}</p>
    </div>

    <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6 md:mt-8">
      <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
        <div>
          <label class="sr-only" for="q">{{ __('portal.layout.search_form.what_label') }}</label>
          <div class="relative">
            <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input id="q" name="q" class="input pl-10" placeholder="{{ __('portal.layout.search_form.placeholder') }}" autocomplete="off">
          </div>
        </div>
        <div>
          <label class="sr-only" for="ort">{{ __('portal.layout.search_form.where_label') }}</label>
          <div class="relative">
            <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input id="ort" name="ort" class="input pl-10" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="postal-code">
          </div>
        </div>
        <select name="umkreis" class="input hidden lg:block lg:w-32" aria-label="{{ __('portal.layout.search_form.radius_label') }}">
          <option value="10">{{ __('portal.layout.search_form.radius_option', ['km' => 10]) }}</option>
          <option value="25" selected>{{ __('portal.layout.search_form.radius_option', ['km' => 25]) }}</option>
          <option value="50">{{ __('portal.layout.search_form.radius_option', ['km' => 50]) }}</option>
        </select>
        <button type="submit" class="btn-primary">{{ __('portal.home.hero.search_button') }}</button>
      </div>
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <span class="text-sm text-zinc-500 mr-1">{{ __('portal.layout.search_form.popular') }}</span>
      @foreach($sun['hero']['popular'] as $term)
        <a href="{{ route('portal.companies.index', ['q' => $term]) }}" class="pill-link">{{ $term }}</a>
      @endforeach
    </div>

    <dl class="mt-8 md:mt-10 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4 max-w-3xl">
      <div><dt class="text-sm text-zinc-500">{{ __('portal.home.stats.businesses') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ $companiesFormatted }}</dd></div>
      <div><dt class="text-sm text-zinc-500">{{ __('portal.home.stats.reviews') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($totalReviews, 0, ',', '.') }}</dd></div>
      <div><dt class="text-sm text-zinc-500">{{ __('portal.home.stats.cities') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($totalCities, 0, ',', '.') }}</dd></div>
      @if($avgRating > 0)
        <div><dt class="text-sm text-zinc-500">{{ __('portal.home.stats.avg_rating') }}</dt><dd class="text-xl font-semibold text-zinc-900 flex items-center gap-1.5">{{ number_format($avgRating, 1, ',', '') }} <x-sun.icon name="star" class="size-5 fill-amber-500 text-amber-500" stroke-linecap="butt" stroke-linejoin="miter" /></dd></div>
      @endif
    </dl>
  </div>
</section>

<!-- ========== LEISTUNGEN ========== -->
<section class="section">
  <div class="container-portal">
    <x-sun.section-heading :title="__('portal.home.services.heading')" :href="route('portal.categories.index')" :link="__('portal.home.services.all')" />
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      @foreach($sun['services'] as $service)
        <a href="{{ route('portal.companies.index', ['q' => $service['query']]) }}" class="card-interactive p-4 md:p-5 flex flex-row items-center lg:flex-col lg:items-start gap-4 lg:gap-3">
          <span class="size-11 shrink-0 rounded-xl bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon :name="$service['icon']" class="size-6" /></span>
          <span class="min-w-0 flex-1 flex flex-col gap-0.5 lg:gap-2">
            <span class="font-semibold text-zinc-900 leading-snug">{{ $service['label'] }}</span>
            <span class="text-sm text-zinc-500">{{ trans_choice('portal.home.services.count', $service['count'], ['anzahl' => number_format($service['count'], 0, ',', '.')]) }}</span>
          </span>
        </a>
      @endforeach
    </div>
  </div>
</section>

<!-- ========== TOP BEWERTET ========== -->
@if($sun['topRated']->isNotEmpty())
<section class="section pt-0">
  <div class="container-portal">
    <x-sun.section-heading :title="__('portal.home.top_rated.heading')" :href="route('portal.companies.index', ['sort' => 'rating'])" :link="__('portal.home.top_rated.all')" />
    <div class="flex xl:grid xl:grid-cols-3 gap-4 overflow-x-auto xl:overflow-visible -mx-4 px-4 xl:mx-0 xl:px-0 pb-2 xl:pb-0 scroll-snap">
      @foreach($sun['topRated'] as $company)
        <x-sun.company-card :company="$company" />
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- ========== RATGEBER ========== -->
@if($latestPosts->isNotEmpty())
<section class="section pt-0">
  <div class="container-portal">
    <x-sun.section-heading :title="__('portal.home.guide.heading')" :href="route('portal.blog.index')" :link="__('portal.home.guide.all')" />
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($latestPosts->take(3) as $post)
        <a href="{{ route('portal.blog.show', $post->slug) }}" class="card-interactive overflow-hidden flex flex-col">
          @if($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="" width="640" height="360" class="aspect-video w-full object-cover" loading="lazy">
          @else
            <div class="aspect-video w-full bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true">
              <x-sun.icon :name="config('themes.sun-v2.brand_icon')" class="size-14" stroke-width="1.5" />
            </div>
          @endif
          <div class="p-5 flex flex-col gap-2 flex-1">
            @if($post->category)
              <span class="pill self-start">{{ $post->category->name }}</span>
            @endif
            <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $post->title }}</h3>
            <p class="text-sm text-zinc-500 line-clamp-2">{{ $post->excerpt_or_truncated }}</p>
            @if($post->reading_time_minutes)
              <span class="text-sm text-zinc-500 mt-auto pt-1">{{ __('portal.layout.reading_time', ['minuten' => $post->reading_time_minutes]) }}</span>
            @endif
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- ========== AD-SLOT ========== -->
@if(\App\View\Components\AdSlot::hasSlotsForPosition('home_content'))
<div class="container-portal pb-12 md:pb-16">
  <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:280px">
    <span class="block text-xs text-zinc-400 px-3 pt-2">{{ __('portal.layout.ad_label') }}</span>
    <x-ad-slot position="home_content" />
  </div>
</div>
@endif

<!-- ========== CTA-BAND ========== -->
<section class="bg-brand-50 section">
  <div class="container-portal">
    <div class="card p-6 md:p-10 max-w-3xl mx-auto lg:flex lg:items-center lg:gap-10">
      <div class="flex-1">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.home.cta.headline') }}</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.home.cta.text') }}</p>
        <ul class="mt-4 space-y-2 text-zinc-700">
          @foreach([__('portal.home.cta.benefit_free'), __('portal.home.cta.benefit_reviews'), __('portal.home.cta.benefit_requests')] as $benefit)
            <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />{{ $benefit }}</li>
          @endforeach
        </ul>
      </div>
      <div class="mt-6 lg:mt-0 flex flex-col gap-2 lg:w-64 shrink-0">
        <a href="{{ route('portal.companies.create') }}" class="btn-primary w-full">{{ __('portal.home.cta.button') }}</a>
        <a href="{{ route('portal.premium.pricing') }}" class="btn-ghost w-full">{{ __('portal.home.cta.premium') }}</a>
        @if($totalCompanies > 0)
          <p class="text-sm text-zinc-500 text-center mt-1">{{ trans_choice('portal.home.cta.social_proof', $totalCompanies, ['anzahl' => $companiesFormatted]) }}</p>
        @endif
      </div>
    </div>
  </div>
</section>

<!-- ========== STÄDTE ========== -->
@if($sun['cities']->isNotEmpty())
<section class="section">
  <div class="container-portal">
    <x-sun.section-heading :title="__('portal.home.cities.heading')" :href="route('portal.cities.index')" :link="__('portal.home.cities.all')" />
    <ul class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-x-6 gap-y-1">
      @foreach($sun['cities'] as $city)
        <li><a href="{{ \App\Support\CityUrl::show($city) }}" class="flex justify-between min-h-11 items-center hover:text-brand"><span class="font-medium text-zinc-900">{{ $city->name }}</span><span class="text-sm text-zinc-500">{{ number_format($city->companies_count, 0, ',', '.') }}</span></a></li>
      @endforeach
    </ul>
    <div class="mt-4">
      <a href="{{ route('portal.cities.index') }}" class="btn-secondary w-full sm:w-auto">{{ __('portal.home.cities.more') }}</a>
    </div>
  </div>
</section>
@endif

<!-- ========== SEO-TEXT ========== -->
<section class="section pt-0">
  <div class="container-portal">
    <div class="max-w-prose">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $sun['seo']['headline'] }}</h2>
      @foreach($sun['seo']['paragraphs'] as $paragraph)
        <p class="mt-4 text-base leading-relaxed">{{ $paragraph }}</p>
      @endforeach
    </div>
  </div>
</section>

@endsection
