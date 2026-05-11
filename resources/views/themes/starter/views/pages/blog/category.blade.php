@extends('layouts.app')

@section('title', $category->name . ' — Ratgeber — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', $category->description ?: 'Ratgeber-Artikel in der Kategorie ' . $category->name)

@section('content')

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => $breadcrumb])

    {{-- Hero --}}
    <div class="blog-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="blog-hero__icon">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
                </svg>
            </div>
            <h1 class="blog-hero__title">{{ $category->name }}</h1>
            @if($category->description)
                <p class="blog-hero__subtitle">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    <div class="container mx-auto px-4 pb-16">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Hauptbereich --}}
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
                        <h2 class="text-lg font-semibold text-[#0F172A] mb-2">Noch keine Artikel in dieser Kategorie</h2>
                        <p class="text-sm text-[#64748B] mb-4">Schauen Sie bald wieder vorbei.</p>
                        <a href="{{ route('portal.blog.index') }}" class="text-sm font-semibold" style="color: var(--portal-primary, #3B82F6);">Alle Ratgeber ansehen &rarr;</a>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            @include('pages.blog._sidebar', [
                'categories' => $categories,
                'popularTags' => $popularTags,
                'recentPosts' => $recentPosts ?? collect(),
                'category' => $category,
            ])

        </div>
    </div>

@endsection
