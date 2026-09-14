{{--
    Ratgeber-Kategorie /ratgeber/kategorie/{slug} im Theme sun-v2, im Aufbau der
    Ratgeber-Uebersicht (pages/blog/index). Daten: PublicBlogController::category()
    plus $sunBlog aus App\Themes\SunV2\BlogIndexViewComposer.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $branchSingular = config('themes.sun-v2.search.branch_singular');
    $total = $posts->total();
    $intro = $category->description ?: "Alle Ratgeber zum Thema {$category->name} – verständlich erklärt, damit du Angebote prüfen und mit dem {$branchSingular} auf Augenhöhe reden kannst.";
@endphp

@section('title', $category->name.' — Ratgeber — '.$portalName)
@section('meta_description', \Illuminate\Support\Str::limit($intro, 160))
@if(request('page'))
@section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.blog.category', $category->slug))

@push('scripts')
    @include('ratgeber.partials.organization-jsonld', ['organization' => $organization ?? []])
    <script type="application/ld+json">
    {!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $category->name.' — Ratgeber — '.$portalName,
        'description' => $intro,
        'url' => route('portal.blog.category', $category->slug),
        'isPartOf' => ['@type' => 'WebSite', 'name' => $portalName, 'url' => url('/')],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')

<!-- ===== HERO ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
      <a href="{{ route('home') }}" class="hover:text-brand">Start</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      <a href="{{ route('portal.blog.index') }}" class="hover:text-brand">Ratgeber</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      <span class="text-zinc-900">{{ $category->name }}</span>
    </nav>
    <div class="mt-4 flex items-start gap-4 max-w-2xl">
      <span class="size-14 shrink-0 rounded-2xl bg-white text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon :name="$sunBlog['icon']($category->slug)" class="size-7" /></span>
      <div class="min-w-0">
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $category->name }}</h1>
        <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ $intro }}</p>
        <p class="mt-2 text-sm text-zinc-500">{{ number_format($total, 0, ',', '.') }} {{ $total === 1 ? 'Artikel' : 'Artikel' }} · <a href="{{ route('portal.blog.editorial') }}" class="hover:text-brand underline">So arbeitet unsere Redaktion</a></p>
      </div>
    </div>
    <form action="{{ route('portal.blog.search') }}" method="get" role="search" class="card shadow-lg p-3 md:p-4 mt-6 max-w-xl">
      <div class="grid gap-3 grid-cols-[1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="q">Ratgeber durchsuchen</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" class="input pl-10" placeholder="{{ $sunBlog['searchPlaceholder'] }}">
        </div>
        <button type="submit" class="btn-primary px-4 sm:px-5">Suchen</button>
      </div>
    </form>
  </div>
</section>

<div class="container-portal py-12 md:py-16 flex flex-col gap-12 md:gap-16">

  <!-- ===== ARTIKEL ===== -->
  <section>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4">
      <h2 class="text-2xl font-semibold text-zinc-900">Artikel zu {{ $category->name }}</h2>
      @if($categories->isNotEmpty())
        <nav class="flex gap-2 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto scroll-snap pb-1 min-w-0" aria-label="Thema wechseln">
          @php $activeClass = 'pill min-h-11 md:min-h-0 px-4 md:px-3 bg-brand-50 text-brand-700 border border-brand-200 whitespace-nowrap'; @endphp
          <a href="{{ route('portal.blog.index') }}" class="pill-link whitespace-nowrap">Alle</a>
          @foreach($categories as $item)
            <a href="{{ route('portal.blog.category', $item->slug) }}" class="{{ $item->id === $category->id ? $activeClass : 'pill-link whitespace-nowrap' }}" @if($item->id === $category->id) aria-current="page" @endif>{{ $item->name }}</a>
          @endforeach
        </nav>
      @endif
    </div>

    @if($posts->isEmpty())
      <div class="card p-8 mt-6 text-center">
        <span class="size-14 mx-auto rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
        <p class="mt-4 font-semibold text-zinc-900">Noch keine Artikel in {{ $category->name }}</p>
        <p class="mt-1 text-sm text-zinc-500">Schau bald wieder vorbei – oder stöber in allen Ratgebern.</p>
        <a href="{{ route('portal.blog.index') }}" class="mt-5 btn-secondary">Alle Ratgeber ansehen</a>
      </div>
    @endif

    @foreach([$posts->getCollection()->take(3), $posts->getCollection()->slice(3)] as $group)
      @if($loop->index === 1 && $group->isNotEmpty())
        <div class="mt-4">
          <x-ad-slot position="listing_between_results" />
        </div>
      @endif
      @if($group->isNotEmpty())
        <div class="{{ $loop->first ? 'mt-6' : 'mt-4' }} grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          @foreach($group as $post)
            <a href="{{ route('portal.blog.show', $post->slug) }}" class="card-interactive overflow-hidden flex flex-col">
              @if($post->featured_image_url)
                <img src="{{ $post->featured_image_url }}" alt="" width="640" height="360" class="aspect-video w-full object-cover" loading="lazy">
              @else
                <div class="aspect-video w-full bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon :name="$sunBlog['icon']($category->slug)" class="size-14" /></div>
              @endif
              <div class="p-5 flex flex-col gap-2 flex-1">
                <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $post->title }}</h3>
                <p class="text-sm text-zinc-500 line-clamp-2">{{ $post->excerpt_or_truncated }}</p>
                @if($post->reading_time_minutes)
                  <span class="text-sm text-zinc-500 mt-auto pt-1">{{ $post->reading_time_minutes }} Min. Lesezeit</span>
                @endif
              </div>
            </a>
          @endforeach
        </div>
      @endif
    @endforeach

    <div class="mt-8">
      <x-sun.pagination :paginator="$posts" :pages="\App\Themes\SunV2\PageWindow::for($posts)" />
    </div>
  </section>

  <!-- ===== CTA ===== -->
  <section class="card p-5 md:p-8 bg-brand-50 border-brand-100">
    <div class="lg:flex lg:items-center lg:gap-10">
      <div class="flex-1 max-w-prose">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ $sunBlog['cta']['headline'] }}</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">{{ $sunBlog['cta']['text'] }}</p>
      </div>
      <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="mt-5 lg:mt-0 lg:w-80 shrink-0 grid gap-3">
        <label class="sr-only" for="cta-ort">Ort oder PLZ</label>
        <input id="cta-ort" name="ort" class="input" placeholder="Ort oder PLZ" autocomplete="postal-code">
        <button type="submit" class="btn-primary">{{ $branchSingular }} finden</button>
      </form>
    </div>
  </section>

</div>
@endsection
