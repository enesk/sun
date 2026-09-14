{{--
    Ratgeber-Suche /ratgeber/suche im Theme sun-v2, im Aufbau der
    Ratgeber-Kategorie (pages/blog/category). Daten: PublicBlogController::search()
    plus $sunBlog aus App\Themes\SunV2\BlogIndexViewComposer.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $branchSingular = config('themes.sun-v2.search.branch_singular');
    $total = $posts->total();
@endphp

@section('title', ($searchTerm ? 'Suche: '.$searchTerm.' — ' : '').'Ratgeber — '.$portalName)
@section('meta_robots', 'noindex, follow')

@section('content')

<!-- ===== HERO ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
      <a href="{{ route('home') }}" class="hover:text-brand">Start</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      <a href="{{ route('portal.blog.index') }}" class="hover:text-brand">Ratgeber</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      <span class="text-zinc-900">Suche</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">
        @if($searchTerm)
          Suche: „{{ $searchTerm }}“
        @else
          Ratgeber durchsuchen
        @endif
      </h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ number_format($total, 0, ',', '.') }} Artikel gefunden</p>
    </div>
    <form action="{{ route('portal.blog.search') }}" method="get" role="search" class="card shadow-lg p-3 md:p-4 mt-6 max-w-xl">
      <div class="grid gap-3 grid-cols-[1fr_auto]">
        <div class="relative">
          <label class="sr-only" for="q">Ratgeber durchsuchen</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" value="{{ $searchTerm }}" class="input pl-10" placeholder="{{ $sunBlog['searchPlaceholder'] }}">
        </div>
        <button type="submit" class="btn-primary px-4 sm:px-5">Suchen</button>
      </div>
    </form>
  </div>
</section>

<div class="container-portal py-12 md:py-16 flex flex-col gap-12 md:gap-16">

  <!-- ===== ERGEBNISSE ===== -->
  <section>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4">
      <h2 class="text-2xl font-semibold text-zinc-900">Ergebnisse</h2>
      @if($categories->isNotEmpty())
        <nav class="flex gap-2 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto scroll-snap pb-1 min-w-0" aria-label="Nach Thema stöbern">
          <a href="{{ route('portal.blog.index') }}" class="pill-link whitespace-nowrap">Alle</a>
          @foreach($categories as $item)
            <a href="{{ route('portal.blog.category', $item->slug) }}" class="pill-link whitespace-nowrap">{{ $item->name }}</a>
          @endforeach
        </nav>
      @endif
    </div>

    @if($posts->isEmpty())
      <div class="card p-8 mt-6 text-center">
        <span class="size-14 mx-auto rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
        <p class="mt-4 font-semibold text-zinc-900">Keine Artikel gefunden</p>
        <p class="mt-1 text-sm text-zinc-500">Versuch es mit einem anderen Suchbegriff – oder stöber in allen Ratgebern.</p>
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
            @include('pages.blog._card', ['post' => $post])
          @endforeach
        </div>
      @endif
    @endforeach

    <div class="mt-8">
      <x-sun.pagination :paginator="$posts" :pages="\App\Themes\SunV2\PageWindow::for($posts)" />
    </div>
  </section>

  @include('pages.blog._cta')

</div>
@endsection
