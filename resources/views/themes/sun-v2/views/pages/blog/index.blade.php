{{--
    Ratgeber-Uebersicht im Theme sun-v2 (Vorlage elektrikerportal-ratgeber-uebersicht.html).
    Daten: PublicBlogController::index() plus $sunBlog aus
    App\Themes\SunV2\BlogIndexViewComposer.

    "Themen" und "Meistgelesen" stehen nur auf Seite 1 ohne Kategoriefilter;
    gefiltert oder weiterblaettert zaehlt allein die Artikelliste.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $activeCategory = request('kategorie');
    $isStart = ! $activeCategory && $posts->currentPage() === 1;
    $featured = $isStart ? $sunBlog['featured'] : null;
    $listPosts = $posts->getCollection()->reject(fn ($post) => $featured && $post->id === $featured->id)->values();
@endphp

@section('title', __('portal.layout.header.guide').' — '.$portalName)
@section('meta_description', \Illuminate\Support\Str::limit($sunBlog['text'], 160))

@if($activeCategory || request('page'))
@section('meta_robots', 'noindex, follow')
@endif

@push('scripts')
    @include('ratgeber.partials.organization-jsonld', ['organization' => $organization ?? []])
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => __('portal.layout.header.guide').' — '.$portalName,
        'description' => $sunBlog['text'],
        'url' => route('guide.index'),
        'isPartOf' => ['@type' => 'WebSite', 'name' => $portalName, 'url' => url('/')],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $portalName,
            'url' => url('/'),
            'publishingPrinciples' => route('portal.blog.editorial'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')

<!-- ===== HERO ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5">
      <a href="{{ route('home') }}" class="hover:text-brand">{{ __('portal.layout.breadcrumb.home') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">{{ __('portal.layout.header.guide') }}</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $sunBlog['headline'] }}</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ $sunBlog['text'] }}</p>
      <p class="mt-2 text-sm"><a href="{{ route('portal.blog.editorial') }}" class="text-zinc-500 hover:text-brand underline">{{ __('portal.blog.list.editorial_link') }}</a></p>
    </div>
    <form action="{{ route('portal.blog.search') }}" method="get" role="search" class="card shadow-lg p-3 md:p-4 mt-6 max-w-xl">
      <div class="grid gap-3 grid-cols-[1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="q">{{ __('portal.blog.list.search_label') }}</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" class="input pl-10" placeholder="{{ $sunBlog['searchPlaceholder'] }}">
        </div>
        <button type="submit" class="btn-primary px-4 sm:px-5">{{ __('portal.blog.list.search_submit') }}</button>
      </div>
    </form>
  </div>
</section>

<div class="container-portal py-12 md:py-16 flex flex-col gap-12 md:gap-16">

  @if($isStart && $categories->isNotEmpty())
  <!-- ===== KATEGORIEN ===== -->
  <section>
    <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.blog.index.topics_heading') }}</h2>
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
      @foreach($categories as $category)
        <a href="{{ route('guide.category', $category->slug) }}" class="card-interactive p-5 flex flex-row items-start lg:flex-col gap-4 lg:gap-3">
          <span class="size-11 shrink-0 rounded-xl bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon :name="$sunBlog['icon']($category->slug)" class="size-6" /></span>
          <span class="min-w-0 flex-1 flex flex-col gap-1">
            <span class="font-semibold text-zinc-900 leading-snug">{{ $category->name }}</span>
            @if($category->description)
              <span class="text-sm text-zinc-500">{{ $category->description }}</span>
            @endif
            <span class="text-sm text-zinc-400 mt-1">{{ trans_choice('portal.blog.list.article_count', $category->posts_count, ['anzahl' => number_format($category->posts_count, 0, ',', '.')]) }}</span>
          </span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  @if($featured)
  <!-- ===== MEISTGELESEN ===== -->
  <section>
    <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.blog.index.featured_heading') }}</h2>
    <div class="mt-6 flex flex-col gap-4">
      <a href="{{ route('guide.show', $featured->slug) }}" class="card-interactive overflow-hidden flex flex-col md:flex-row">
        @if($featured->featured_image_url)
          <img src="{{ $featured->featured_image_url }}" alt="" width="640" height="360" class="aspect-video md:aspect-auto md:w-2/5 shrink-0 object-cover" loading="lazy">
        @else
          <div class="aspect-video md:aspect-auto md:w-2/5 shrink-0 bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon :name="$sunBlog['icon']($featured->category?->slug)" class="size-16" /></div>
        @endif
        <div class="p-5 md:p-8 flex flex-col gap-3 min-w-0">
          <div class="flex flex-wrap items-center gap-3">
            @if($featured->category)
              <span class="pill-brand">{{ $featured->category->name }}</span>
            @endif
            @if($featured->reading_time_minutes)
              <span class="text-sm text-zinc-500">{{ __('portal.layout.reading_time', ['minuten' => $featured->reading_time_minutes]) }}</span>
            @endif
          </div>
          <h3 class="text-2xl font-semibold text-zinc-900 leading-snug">{{ $featured->title }}</h3>
          <p class="text-zinc-500 leading-relaxed">{{ $featured->excerpt_or_truncated }}</p>
        </div>
      </a>
    </div>
  </section>
  @endif

  <!-- ===== ALLE ARTIKEL ===== -->
  <section>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.blog.index.latest_heading') }}</h2>
      @if($categories->isNotEmpty())
        <nav class="flex gap-2 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto scroll-snap pb-1 min-w-0" aria-label="{{ __('portal.blog.index.filter_label') }}">
          @php $activeClass = 'pill min-h-11 md:min-h-0 px-4 md:px-3 bg-brand-50 text-brand-700 border border-brand-200 whitespace-nowrap'; @endphp
          <a href="{{ route('guide.index') }}" class="{{ $activeCategory ? 'pill-link whitespace-nowrap' : $activeClass }}" @unless($activeCategory) aria-current="page" @endunless>{{ __('portal.blog.list.filter_all') }}</a>
          @foreach($categories as $category)
            <a href="{{ route('guide.index', ['kategorie' => $category->slug]) }}" class="{{ $activeCategory === $category->slug ? $activeClass : 'pill-link whitespace-nowrap' }}" @if($activeCategory === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
          @endforeach
        </nav>
      @endif
    </div>

    @if($listPosts->isEmpty() && ! $featured)
      <div class="card p-8 mt-6 text-center">
        <span class="size-14 mx-auto rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
        <p class="mt-4 font-semibold text-zinc-900">{{ __('portal.blog.index.empty_heading') }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.blog.index.empty_text') }}</p>
      </div>
    @endif

    @foreach([$listPosts->take(3), $listPosts->slice(3)] as $group)
      @if($loop->index === 1 && $group->isNotEmpty())
        <div class="mt-4">
          <x-ad-slot position="listing_between_results" />
        </div>
      @endif
      @if($group->isNotEmpty())
        <div class="{{ $loop->first ? 'mt-6' : 'mt-4' }} grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          @foreach($group as $post)
            @include('pages.blog._card', ['post' => $post])
          @endforeach
        </div>
      @endif
    @endforeach

    @if($posts->hasMorePages())
      <div class="mt-6">
        <a href="{{ $posts->nextPageUrl() }}" class="btn-secondary w-full sm:w-auto">{{ __('portal.blog.index.load_more') }}</a>
      </div>
    @endif
  </section>

  <!-- ===== CTA ===== -->
  <section class="card p-5 md:p-8 bg-brand-50 border-brand-100">
    <div class="lg:flex lg:items-center lg:gap-10">
      <div class="flex-1 max-w-prose">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ $sunBlog['cta']['headline'] }}</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">{{ $sunBlog['cta']['text'] }}</p>
      </div>
      <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="mt-5 lg:mt-0 lg:w-80 shrink-0 grid gap-3">
        <label class="sr-only" for="cta-ort">{{ __('portal.layout.search_form.where_placeholder') }}</label>
        <input id="cta-ort" name="ort" class="input" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" autocomplete="postal-code">
        <button type="submit" class="btn-primary">{{ __('portal.blog.cta.button') }}</button>
      </form>
    </div>
  </section>

</div>
@endsection
