@extends('layouts.app')

@section('title', ($post->meta_title ?: $post->title) . ' — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', $post->meta_description ?: $post->excerpt_or_truncated)

@section('content')

    {{-- Schema.org: Article --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->meta_description ?: $post->excerpt_or_truncated,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at->toIso8601String(),
        'url' => route('portal.blog.show', $post->slug),
        'image' => $post->featured_image_url,
        'wordCount' => str_word_count(strip_tags($post->body)),
        'author' => [
            '@type' => 'Organization',
            'name' => $currentTenant->name ?? config('app.name'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => url('/'),
        ],
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => url('/'),
        ],
        'articleSection' => $post->category?->name,
        'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Reading Progress Bar --}}
    <div class="blog-reading-progress" id="reading-progress" aria-hidden="true"></div>

    {{-- ========== ARTICLE HERO ========== --}}
    <section class="blog-detail-hero {{ $post->featured_image_url ? 'blog-detail-hero--has-image' : '' }}">
        @if($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}"
                 alt="{{ $post->title }}"
                 class="blog-detail-hero__bg">
        @endif
        <div class="blog-detail-hero__overlay"></div>
        <div class="blog-detail-hero__inner">
            {{-- Kategorie --}}
            @if($post->category)
                <a href="{{ route('portal.blog.category', $post->category->slug) }}"
                   class="blog-detail-hero__badge">
                    {{ $post->category->name }}
                </a>
            @endif

            {{-- Titel --}}
            <h1 class="blog-detail-hero__title">{{ $post->title }}</h1>

            {{-- Meta --}}
            <div class="blog-detail-hero__meta">
                <time datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->formatted_date }}
                </time>
                @if($post->reading_time_minutes)
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ $post->reading_time_minutes }} Min. Lesezeit</span>
                @endif
                @if($post->view_count > 0)
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ number_format($post->view_count) }} Aufrufe</span>
                @endif
            </div>

            {{-- Autor --}}
            <div class="blog-detail-hero__author">
                <div class="blog-detail-hero__author-avatar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                </div>
                <span>Redaktion {{ $currentTenant->name ?? config('app.name') }}</span>
            </div>
        </div>
    </section>

    {{-- ========== CONTENT AREA ========== --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="blog-detail-layout">

            {{-- Hauptbereich --}}
            <article class="blog-detail-article" id="blog-article">

                {{-- Table of Contents --}}
                <nav x-data="blogToc()" x-init="init()" x-show="headings.length >= 3" x-cloak
                     class="blog-detail-toc" aria-label="Inhaltsverzeichnis">
                    <div class="blog-detail-toc__header">
                        <svg class="w-5 h-5 text-[var(--portal-primary,#3B82F6)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        <button @click="open = !open" class="blog-detail-toc__toggle"
                                :aria-expanded="open.toString()">
                            <span class="font-semibold text-[#1E293B]">Inhaltsverzeichnis</span>
                            <svg class="w-4 h-4 text-[#94A3B8] transition-transform duration-200"
                                 :class="open ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>
                    <ol x-show="open" x-transition class="blog-detail-toc__list">
                        <template x-for="(h, i) in headings" :key="i">
                            <li :class="h.tag === 'H3' ? 'pl-4' : ''">
                                <a :href="'#' + h.id"
                                   class="blog-detail-toc__link"
                                   x-text="h.text"></a>
                            </li>
                        </template>
                    </ol>
                </nav>

                {{-- Artikel-Content (Markdown → HTML) --}}
                <div class="blog-detail-prose" id="blog-prose">
                    {!! Str::markdown($post->body) !!}
                </div>

                {{-- CTA-Banner --}}
                <div class="blog-detail-cta">
                    <div>
                        <h3 class="blog-detail-cta__title">Den passenden Dienstleister finden</h3>
                        <p class="blog-detail-cta__text">
                            Durchsuchen Sie unser Verzeichnis und finden Sie den richtigen Ansprechpartner in Ihrer Nähe.
                        </p>
                    </div>
                    <a href="{{ route('portal.companies.index') }}" class="blog-detail-cta__btn">
                        Firmen durchsuchen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>

                {{-- Tags --}}
                @if($post->tags->isNotEmpty())
                    <div class="blog-detail-tags">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('portal.blog.tag', $tag->slug) }}"
                               class="blog-detail-tags__item">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Social Sharing --}}
                <div class="blog-detail-share">
                    <span class="blog-detail-share__label">Artikel teilen</span>
                    <div class="blog-detail-share__buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('portal.blog.show', $post->slug)) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--facebook"
                           aria-label="Auf Facebook teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('portal.blog.show', $post->slug)) }}&text={{ urlencode($post->title) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--twitter"
                           aria-label="Auf X teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(route('portal.blog.show', $post->slug)) }}&title={{ urlencode($post->title) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--linkedin"
                           aria-label="Auf LinkedIn teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($post->title . ' — ' . route('portal.blog.show', $post->slug)) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--whatsapp"
                           aria-label="Per WhatsApp teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        <button @click="navigator.clipboard.writeText('{{ route('portal.blog.show', $post->slug) }}'); $dispatch('toast', { type: 'success', message: 'Link kopiert!' })"
                                class="blog-detail-share__btn blog-detail-share__btn--copy"
                                aria-label="Link kopieren">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Autoren-Box --}}
                <div class="blog-detail-author">
                    <div class="blog-detail-author__avatar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-[#94A3B8] uppercase tracking-wider mb-0.5">Verfasst von</p>
                        <p class="font-semibold text-[#1E293B]">Redaktion {{ $currentTenant->name ?? config('app.name') }}</p>
                        <p class="text-sm text-[#64748B] mt-1 leading-relaxed">
                            Unser Redaktionsteam recherchiert und verfasst praxisnahe Ratgeber-Artikel rund um lokale Dienstleister, Handwerker und kleine Unternehmen.
                        </p>
                    </div>
                </div>

                {{-- Vorheriger / Nächster --}}
                @if($previousPost || $nextPost)
                    <nav class="blog-detail-pager" aria-label="Weitere Artikel">
                        @if($previousPost)
                            <a href="{{ route('portal.blog.show', $previousPost->slug) }}"
                               class="blog-detail-pager__link">
                                <span class="blog-detail-pager__label">&larr; Vorheriger Artikel</span>
                                <span class="blog-detail-pager__title">{{ $previousPost->title }}</span>
                            </a>
                        @else
                            <div></div>
                        @endif
                        @if($nextPost)
                            <a href="{{ route('portal.blog.show', $nextPost->slug) }}"
                               class="blog-detail-pager__link blog-detail-pager__link--next">
                                <span class="blog-detail-pager__label">Nächster Artikel &rarr;</span>
                                <span class="blog-detail-pager__title">{{ $nextPost->title }}</span>
                            </a>
                        @endif
                    </nav>
                @endif

            </article>

            {{-- Sidebar --}}
            @include('pages.blog._sidebar', [
                'categories' => $categories,
                'popularTags' => $popularTags,
                'recentPosts' => collect(),
            ])

        </div>
    </div>

    {{-- ========== RELATED POSTS ========== --}}
    @if($relatedPosts->isNotEmpty())
        <section class="blog-detail-related">
            <div class="container mx-auto px-4">
                <h2 class="blog-detail-related__title">Das könnte Sie auch interessieren</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($relatedPosts as $related)
                        @include('pages.blog._post-card', ['post' => $related])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Alpine.js: TOC + Reading Progress --}}
    @push('scripts')
    <script>
    function blogToc() {
        return {
            headings: [],
            open: true,
            init() {
                const prose = document.getElementById('blog-prose');
                if (!prose) return;
                const hTags = prose.querySelectorAll('h2, h3');
                this.headings = Array.from(hTags).map((h, i) => {
                    if (!h.id) h.id = 'section-' + i;
                    return { id: h.id, text: h.textContent.trim(), tag: h.tagName };
                });
            }
        };
    }

    (function() {
        const bar = document.getElementById('reading-progress');
        const article = document.getElementById('blog-article');
        if (!bar || !article) return;

        function update() {
            const rect = article.getBoundingClientRect();
            const total = article.offsetHeight - window.innerHeight;
            if (total <= 0) { bar.style.width = '100%'; return; }
            const pct = Math.min(100, Math.max(0, (-rect.top / total) * 100));
            bar.style.width = pct + '%';
        }

        window.addEventListener('scroll', update, { passive: true });
        update();
    })();
    </script>
    @endpush

@endsection
