@extends('layouts.app')

@section('title', '#' . $tag->name . ' — Ratgeber — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Ratgeber-Artikel zum Thema ' . $tag->name)

@section('content')

    @include('components.breadcrumb', ['items' => $breadcrumb])

    {{-- Hero --}}
    <div class="blog-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="blog-hero__icon">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z" />
                </svg>
            </div>
            <h1 class="blog-hero__title">#{{ $tag->name }}</h1>
            <p class="blog-hero__subtitle">Alle Ratgeber-Artikel zu diesem Thema</p>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-16">
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
                        <h2 class="text-lg font-semibold text-[#0F172A] mb-2">Noch keine Artikel zu diesem Thema</h2>
                        <p class="text-sm text-[#64748B] mb-4">Schauen Sie bald wieder vorbei.</p>
                        <a href="{{ route('portal.blog.index') }}" class="text-sm font-semibold" style="color: var(--portal-primary, #3B82F6);">Alle Ratgeber ansehen &rarr;</a>
                    </div>
                @endif
            </div>

            @include('pages.blog._sidebar', [
                'categories' => $categories,
                'popularTags' => $popularTags,
                'recentPosts' => $recentPosts ?? collect(),
                'tag' => $tag,
            ])
        </div>
    </div>

@endsection
