@extends('layouts.app')

@section('title', 'Suche: ' . $searchTerm . ' — Ratgeber — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, follow')

@section('content')

    @include('components.breadcrumb', ['items' => $breadcrumb])

    {{-- Hero --}}
    <div class="blog-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="blog-hero__icon">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <h1 class="blog-hero__title">
                @if($searchTerm)
                    Suche: &ldquo;{{ $searchTerm }}&rdquo;
                @else
                    Ratgeber durchsuchen
                @endif
            </h1>
            <p class="blog-hero__subtitle">{{ $posts->total() }} {{ $posts->total() === 1 ? 'Ergebnis' : 'Ergebnisse' }} gefunden</p>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-16">

        {{-- Suchfeld --}}
        <form action="{{ route('portal.blog.search') }}" method="GET" class="max-w-xl mx-auto mb-8 -mt-6 relative z-20" role="search">
            <div class="blog-search">
                <svg class="blog-search__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="{{ $searchTerm }}" placeholder="Ratgeber durchsuchen..." aria-label="Suchbegriff" class="blog-search__input">
            </div>
        </form>

        <div class="flex flex-col lg:flex-row gap-8">
            <div class="flex-1 min-w-0">
                @if($posts->isNotEmpty())
                    <div class="blog-grid">
                        @foreach($posts as $post)
                            @include('pages.blog._post-card', ['post' => $post])
                        @endforeach
                    </div>

                    @if($posts->hasPages())
                        <div class="mt-8">{{ $posts->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-16">
                        <h2 class="text-lg font-semibold text-[#0F172A] mb-2">Keine Ergebnisse gefunden</h2>
                        <p class="text-sm text-[#64748B] mb-4">Versuchen Sie es mit einem anderen Suchbegriff.</p>
                        <a href="{{ route('portal.blog.index') }}" class="text-sm font-semibold" style="color: var(--portal-primary, #3B82F6);">Alle Ratgeber ansehen &rarr;</a>
                    </div>
                @endif
            </div>

            @include('pages.blog._sidebar', [
                'categories' => $categories,
                'popularTags' => $popularTags,
                'recentPosts' => collect(),
            ])
        </div>
    </div>

@endsection
