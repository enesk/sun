@extends('layouts.app')

{{-- Meta-Angaben kommen aus ArticleSeoService (#17), nicht aus diesem Template. --}}
@section('title', $seo['title'])
@section('meta_description', $seo['description'])
@section('canonical', $seo['canonical'])
@section('og_type', $seo['og_type'])

{{-- Redaktionsvorschau eines Entwurfs (#20): niemals indexieren. --}}
@if($previewMode ?? false)
@section('meta_robots', 'noindex, nofollow')
@endif
@if($seo['og_image'])
@section('og_image', $seo['og_image'])
@endif

@section('content')

    {{-- Strukturierte Daten: Article, BreadcrumbList, FAQPage, ggf. HowTo --}}
    @push('scripts')
        @include('ratgeber.partials.jsonld', ['graph' => $jsonLd])
    @endpush

    {{-- Reading Progress Bar --}}
    <div class="blog-reading-progress" id="reading-progress" aria-hidden="true"></div>

    {{-- ========== ARTICLE HERO ========== --}}
    <section class="blog-detail-hero {{ $heroImage ? 'blog-detail-hero--has-image' : '' }}">
        @if($heroImage)
            {{-- Titelbild aus #16: WebP in 1200/800/400, Alt-Text und Bildnachweis
                 stehen am Entwurf. Das Bild fuellt die Breite, deshalb sizes="100vw".
                 Eager und fetchpriority gelten ausschliesslich hier — es ist das
                 einzige Bild oberhalb der Faltung. --}}
            <picture>
                @if($heroImage['webp'])
                    <source type="image/webp" srcset="{{ $heroImage['webp'] }}" sizes="100vw">
                @endif
                <img src="{{ $heroImage['src'] }}"
                     @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="100vw" @endif
                     width="{{ $heroImage['width'] }}"
                     height="{{ $heroImage['height'] }}"
                     alt="{{ $heroImage['alt'] }}"
                     fetchpriority="high"
                     loading="eager"
                     decoding="async"
                     class="blog-detail-hero__bg">
            </picture>
        @endif
        <div class="blog-detail-hero__overlay"></div>
        <div class="blog-detail-hero__inner">
            {{-- Breadcrumb (BreadcrumbList steht als JSON-LD im Kopf) --}}
            <nav class="ratgeber-breadcrumb" aria-label="Breadcrumb">
                <ol>
                    @foreach($breadcrumb as $crumb)
                        <li>
                            @if(!$loop->last && isset($crumb['url']))
                                <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                <span aria-hidden="true">&rsaquo;</span>
                            @else
                                <span aria-current="page">{{ Str::limit($crumb['label'], 60) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

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
                @if($post->category)
                    <span aria-hidden="true">&middot;</span>
                    <a href="{{ route('portal.blog.category', $post->category->slug) }}"
                       class="blog-detail-hero__badge">{{ $post->category->name }}</a>
                @endif
            </div>

            {{-- Bildnachweis (#71). Bei Unsplash ist die verlinkte Fassung
                 Pflicht; sie steht mit dem Bild sichtbar auf der Seite und wird
                 nicht hinter einer Interaktion versteckt. --}}
            @if($heroImage && ($heroImage['credit_html'] ?? null))
                <p class="blog-detail-hero__credit">{!! $heroImage['credit_html'] !!}</p>
            @elseif($heroImage && ($heroImage['credit'] ?? null))
                <p class="blog-detail-hero__credit">{{ $heroImage['credit'] }}</p>
            @endif
        </div>
    </section>

    {{-- ========== CONTENT AREA ========== --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="blog-detail-layout">

            {{-- Hauptbereich --}}
            <article class="blog-detail-article" id="blog-article">

                {{-- Block 2: Kurzantwort — vor Inhaltsverzeichnis und vor jeder Anzeige --}}
                @if($shortAnswer)
                    <section class="ratgeber-short-answer" aria-labelledby="short-answer-heading">
                        <h2 id="short-answer-heading" class="ratgeber-short-answer__kicker">Kurz gesagt</h2>
                        <p class="ratgeber-short-answer__text">{{ $shortAnswer }}</p>
                    </section>
                @endif

                {{-- Block 3: Meta- und Transparenzzeile --}}
                <p class="ratgeber-transparency">
                    @if($seo['modified_at'])
                        Zuletzt geprüft am
                        <time datetime="{{ $seo['modified_at']->toDateString() }}">{{ $seo['modified_at']->translatedFormat('j. F Y') }}</time>
                        <span aria-hidden="true">&middot;</span>
                    @endif
                    @if($post->reading_time_minutes)
                        Lesezeit {{ $post->reading_time_minutes }} Minuten
                        <span aria-hidden="true">&middot;</span>
                    @endif
                    {{ $authorName }}
                    <span aria-hidden="true">&middot;</span>
                    <a href="{{ route('portal.blog.editorial') }}">Maschinell erstellt, redaktionell geprüft</a>
                </p>

                {{-- Block 4: Inhaltsverzeichnis (serverseitig, ohne JavaScript bedienbar).
                     Ab 1024 px steht es klebend in der Seitenspalte, deshalb hier
                     nur unterhalb dieser Breite (#83). --}}
                @include('ratgeber.partials.toc', [
                    'headings' => $headings,
                    'tocClass' => 'ratgeber-toc--inline',
                ])

                {{-- Block 5: Key-Facts-Tabelle --}}
                @include('ratgeber.partials.key-facts', ['keyFacts' => $keyFacts])

                {{-- Infografik aus denselben Key-Facts (#16) --}}
                @include('ratgeber.partials.infographic', ['infographic' => $infographic ?? null])

                {{-- Anzeige: fruehestens nach der Key-Facts-Tabelle --}}
                <x-ad-slot position="content_after_intro" />

                {{-- Block 6: Hauptteil --}}
                <div class="blog-detail-prose" id="blog-prose">
                    {!! $bodyBefore !!}
                </div>

                {{-- Block 7: Regionalblock — ohne Anzeige davor oder danach --}}
                @include('ratgeber.partials.regional-block', ['region' => $region])

                @if($bodyAfter !== '')
                    <div class="blog-detail-prose">
                        {!! $bodyAfter !!}
                    </div>
                @endif

                {{-- Block 8: FAQ --}}
                @include('ratgeber.partials.faq', ['faq' => $faq])

                {{-- Block 9: Quellen- und Aktualitaetszeile --}}
                @include('ratgeber.partials.sources', [
                    'sources' => $sources,
                    'publishedAt' => $seo['published_at'],
                    'modifiedAt' => $seo['modified_at'],
                ])

                {{-- Block 10: CTA-Box --}}
                @include('ratgeber.partials.cta', [
                    'ctaUrl' => $region['url'] ?? route('portal.companies.index'),
                    'ctaLabel' => isset($region) && $region ? 'Betriebe in ' . $region['name'] . ' ansehen' : 'Firmen durchsuchen',
                ])

                {{-- Block 12: Tags --}}
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
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($seo['canonical']) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--facebook"
                           aria-label="Auf Facebook teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode($seo['canonical']) }}&text={{ urlencode($post->title) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--twitter"
                           aria-label="Auf X teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($seo['canonical']) }}&title={{ urlencode($post->title) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--linkedin"
                           aria-label="Auf LinkedIn teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($post->title . ' — ' . $seo['canonical']) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="blog-detail-share__btn blog-detail-share__btn--whatsapp"
                           aria-label="Per WhatsApp teilen">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        <button @click="navigator.clipboard.writeText('{{ $seo['canonical'] }}'); $dispatch('toast', { type: 'success', message: 'Link kopiert!' })"
                                class="blog-detail-share__btn blog-detail-share__btn--copy"
                                aria-label="Link kopieren">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Block 11: Autorenbox --}}
                @include('ratgeber.partials.author', [
                    'authorName' => $authorName,
                    'authorUrl' => $authorUrl ?? null,
                    'modifiedAt' => $seo['modified_at'],
                    'logoUrl' => !empty($currentTenant) && $currentTenant->getAttribute('branding.logo_path')
                        ? asset($currentTenant->getAttribute('branding.logo_path'))
                        : null,
                ])

                {{-- Block 11b: Aenderungshinweise (#24) --}}
                @include('ratgeber.partials.changelog', ['changelog' => $changelog ?? []])

                {{-- Block 13: Vorheriger / Nächster --}}
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

            {{-- Sidebar nach Blueprint, Abschnitt 4 (#83): sidebar_top, klebendes
                 Inhaltsverzeichnis, Firmensuche der Region, sidebar_sticky.
                 Die Listenseiten nutzen weiterhin pages.blog._sidebar. --}}
            @include('pages.blog._sidebar-article', [
                'headings' => $headings,
                'region' => $region,
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

    {{-- Lesefortschritt: Zugabe, ueber transform bewegt und bei
         prefers-reduced-motion per CSS abgeschaltet. --}}
    @push('scripts')
    <script>
    (function() {
        const bar = document.getElementById('reading-progress');
        const article = document.getElementById('blog-article');
        if (!bar || !article) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        bar.classList.add('blog-reading-progress--active');

        function update() {
            const rect = article.getBoundingClientRect();
            const total = article.offsetHeight - window.innerHeight;
            const pct = total <= 0 ? 1 : Math.min(1, Math.max(0, -rect.top / total));
            bar.style.transform = 'scaleX(' + pct + ')';
        }

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    })();

    // Aktiven Abschnitt im Inhaltsverzeichnis hervorheben — reine Zugabe.
    (function() {
        const links = document.querySelectorAll('.ratgeber-toc__link');
        if (!links.length || !('IntersectionObserver' in window)) return;

        // Artikel und Seitenspalte tragen dieselbe Liste, je Ziel gibt es also
        // mehrere Verweise. Beide werden hervorgehoben; sichtbar ist immer nur einer.
        const map = new Map();
        links.forEach(link => {
            const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
            if (!target) return;
            if (!map.has(target)) map.set(target, []);
            map.get(target).push(link);
        });

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const group = map.get(entry.target);
                if (group && entry.isIntersecting) {
                    links.forEach(l => l.classList.remove('is-active'));
                    group.forEach(l => l.classList.add('is-active'));
                }
            });
        }, { rootMargin: '-88px 0px -70% 0px' });

        map.forEach((_, target) => observer.observe(target));
    })();
    </script>
    @endpush

@endsection
