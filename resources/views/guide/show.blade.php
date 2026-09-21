{{--
    Ratgeber-Artikel /ratgeber/{slug} (#17) nach article_blueprint
    (design/ratgeber-template.md, belegt in design/guide-frontend.md, §3).
    Daten: GuideController::show() / GuidePageData::article().

    Meta, Canonical, Robots und OG setzt der SeoService — dieses Template
    schreibt keine Meta-Angaben. Artikel ohne Thema (mode 'legacy') rendern
    hier ohne Stand-Zeile und ohne Changelog.

    Alles Aufklappbare ist <details>; JavaScript ist nur Zugabe.
--}}
@extends('layouts.app')

@section('content')
    @push('scripts')
        @include('guide.partials.jsonld', ['graph' => $jsonLd])
    @endpush

    @php
        $isTopic = $article['mode'] === 'topic';
        $hero = $article['hero_image'];
    @endphp

    <div class="ratgeber-page">
        {{-- Block 1: Brotkrume, Titel, Titelbild --}}
        @include('guide.partials.breadcrumb', ['items' => $breadcrumb])

        <div class="ratgeber-layout">
            <article class="ratgeber-layout__main" id="blog-article">
                <h1 class="ratgeber-title">{{ $article['title'] }}</h1>

                @if($hero)
                    <figure class="ratgeber-hero">
                        <picture>
                            @if(!empty($hero['webp']))
                                <source type="image/webp" srcset="{{ $hero['webp'] }}" sizes="(min-width: 1024px) 720px, 100vw">
                            @endif
                            <img src="{{ $hero['src'] }}"
                                 @if(!empty($hero['srcset'])) srcset="{{ $hero['srcset'] }}" sizes="(min-width: 1024px) 720px, 100vw" @endif
                                 width="{{ $hero['width'] }}"
                                 height="{{ $hero['height'] }}"
                                 alt="{{ $hero['alt'] }}"
                                 fetchpriority="high"
                                 decoding="async">
                        </picture>
                        @if(!empty($hero['credit_html']))
                            <figcaption class="ratgeber-hero__credit">{!! $hero['credit_html'] !!}</figcaption>
                        @elseif(!empty($hero['credit']))
                            <figcaption class="ratgeber-hero__credit">{{ $hero['credit'] }}</figcaption>
                        @endif
                    </figure>
                @endif

                {{-- Block 2: Kurzantwort — vor Inhaltsverzeichnis und jeder Anzeige --}}
                @if($article['short_answer'])
                    <section class="ratgeber-short-answer" aria-labelledby="short-answer-heading">
                        <h2 id="short-answer-heading" class="ratgeber-short-answer__kicker">Kurz gesagt</h2>
                        <p class="ratgeber-short-answer__text">{{ $article['short_answer'] }}</p>
                    </section>
                @endif

                {{-- Block 3: Stand-Zeile (kompakt) und Transparenzzeile --}}
                @if($isTopic)
                    @include('guide.partials.status-line', [
                        'publishedAt' => $article['published_at'],
                        'updatedAt' => $article['updated_at'],
                        'checkedAt' => $article['checked_at'],
                        'hasChangelog' => $article['changelog'] !== [],
                        'variant' => 'kompakt',
                    ])
                @endif
                <p class="ratgeber-transparency">
                    @if($article['reading_time'])
                        Lesezeit {{ $article['reading_time'] }} {{ $article['reading_time'] === 1 ? 'Minute' : 'Minuten' }}
                        <span aria-hidden="true">&middot;</span>
                    @endif
                    <a href="{{ route('portal.blog.editorial') }}">So entsteht dieser Ratgeber</a>
                </p>

                {{-- Block 4: Inhaltsverzeichnis (unter 1024 px; darueber in der Seitenspalte) --}}
                @include('guide.partials.toc', [
                    'toc' => $article['toc'],
                    'hasFaq' => $article['faq'] !== [],
                    'variant' => 'inline',
                ])

                {{-- Block 5: Key-Facts --}}
                @include('guide.partials.key-facts', ['keyFacts' => $article['key_facts']])

                {{-- Anzeige fruehestens nach Block 5 --}}
                <x-ad-slot position="content_after_intro" />

                {{-- Block 6: Hauptteil. Abschnitts-IDs aus der Gliederung (OutlineAnchors) --}}
                <div class="blog-detail-prose ratgeber-prose">
                    {!! $article['body_before'] !!}
                </div>

                {{-- Block 7: Verweisleiste Firmensuche — ohne Anzeige davor oder danach --}}
                @include('guide.partials.cta', ['cta' => $article['cta'], 'variant' => 'bar'])

                @if($article['body_after'] !== '')
                    <div class="blog-detail-prose ratgeber-prose">
                        {!! $article['body_after'] !!}
                    </div>
                @endif

                {{-- Block 8: FAQ --}}
                @include('guide.partials.faq', ['faq' => $article['faq']])

                {{-- Block 9: Stand-Zeile (vollstaendig) → Was ist neu? → Quellen --}}
                @if($isTopic || $article['sources'] !== [])
                    <section class="ratgeber-aktualitaet" aria-label="Aktualität und Quellen">
                        @if($isTopic)
                            @include('guide.partials.status-line', [
                                'publishedAt' => $article['published_at'],
                                'updatedAt' => $article['updated_at'],
                                'checkedAt' => $article['checked_at'],
                                'hasChangelog' => false,
                                'variant' => 'vollstaendig',
                            ])
                            @include('guide.partials.changelog', ['changelog' => $article['changelog']])
                        @endif
                        @include('guide.partials.sources', ['sources' => $article['sources']])
                    </section>
                @endif

                {{-- Block 10: CTA-Box, einziger primaerer Aufruf --}}
                @include('guide.partials.cta', ['cta' => $article['cta'], 'variant' => 'box'])

                {{-- Block 11: Autorenbox --}}
                @include('ratgeber.partials.author', [
                    'authorName' => $authorName,
                    'authorUrl' => $authorUrl,
                    'modifiedAt' => $article['checked_at']?->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->locale('de'),
                    'logoUrl' => $logoUrl,
                ])

                {{-- Block 13: verwandte Themen derselben Kategorie --}}
                @if($article['related'] !== [])
                    <section class="ratgeber-section" aria-labelledby="ratgeber-related-heading">
                        <h2 id="ratgeber-related-heading" class="ratgeber-section__heading">Verwandte Themen</h2>
                        <div class="ratgeber-list">
                            @foreach($article['related'] as $card)
                                @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
                            @endforeach
                        </div>
                    </section>
                @endif
            </article>

            {{-- Seitenspalte ab 1024 px: sidebar_top, klebendes Inhaltsverzeichnis, sidebar_sticky --}}
            <aside class="ratgeber-sidebar" aria-label="Inhalt und Hinweise">
                <x-ad-slot position="sidebar_top" />
                <div class="ratgeber-sidebar__sticky">
                    @include('guide.partials.toc', [
                        'toc' => $article['toc'],
                        'hasFaq' => $article['faq'] !== [],
                        'variant' => 'sidebar',
                    ])
                    <x-ad-slot position="sidebar_sticky" />
                </div>
            </aside>
        </div>

        <x-ad-slot position="footer_above" />
    </div>
@endsection
