@extends('layouts.app')

@section('title', 'Ratgeber — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Ratgeber und Tipps rund um lokale Dienstleister. Erfahren Sie alles Wichtige für Ihre Suche.')

@if(request('kategorie') || request('page'))
@section('meta_robots', 'noindex, follow')
@endif

@section('content')

    {{-- Schema.org: CollectionPage --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Ratgeber — ' . ($currentTenant->name ?? config('app.name')),
        'description' => 'Ratgeber und Tipps rund um lokale Dienstleister.',
        'url' => route('portal.blog.index'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => url('/'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => $breadcrumb])

    {{-- Hero --}}
    <div class="blog-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="blog-hero__icon">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
            <h1 class="blog-hero__title">Ratgeber</h1>
            <p class="blog-hero__subtitle">Tipps, Anleitungen und Wissenswertes rund um lokale Dienstleister</p>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-16">

        {{-- Blog-Suche --}}
        <form action="{{ route('portal.blog.search') }}" method="GET" class="max-w-xl mx-auto mb-8 -mt-6 relative z-20" role="search" aria-label="Ratgeber durchsuchen">
            <div class="blog-search">
                <svg class="blog-search__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" placeholder="Ratgeber durchsuchen..." aria-label="Suchbegriff" class="blog-search__input">
            </div>
        </form>

        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Hauptbereich --}}
            <div class="flex-1 min-w-0">

                @if($posts->isNotEmpty())
                    <div class="blog-grid">
                        @foreach($posts as $post)
                            @include('pages.blog._post-card', [
                                'post' => $post,
                                'featured' => $loop->first && $posts->currentPage() === 1,
                            ])
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($posts->hasPages())
                        <div class="mt-8">
                            {{ $posts->links() }}
                        </div>
                    @endif
                @else
                    {{-- Empty State --}}
                    <div class="text-center py-16">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#F1F5F9] mb-4">
                            <svg class="w-8 h-8 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-[#0F172A] mb-2">Noch keine Artikel vorhanden</h2>
                        <p class="text-sm text-[#64748B]">Bald finden Sie hier hilfreiche Ratgeber und Tipps.</p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            @include('pages.blog._sidebar', [
                'categories' => $categories,
                'popularTags' => $popularTags,
                'recentPosts' => $recentPosts ?? collect(),
            ])

        </div>
    </div>

@endsection
