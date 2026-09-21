{{--
    Ratgeber-Artikel /ratgeber/{slug} (#17) im Theme sun-v2. Gleiche Daten und
    Blockreihenfolge wie guide/show (article_blueprint, design/guide-frontend.md §3),
    aber im Layout layouts.sun statt layouts.app — sonst faellt der Theme-Finder
    auf das Default-Layout zurueck und die Seite steht im alten Design.

    Meta, Canonical, Robots und OG setzt der SeoService, layouts.sun liest sie.
    Die Partials guide.partials.* kommen aus diesem Theme (Tailwind statt ratgeber-*).
    Der Artikeltext ist generiertes HTML ohne Klassen; die Gestaltung sitzt als
    Tailwind-Varianten am umschliessenden Container.
--}}
@extends('layouts.sun')

@php
    $isTopic = $article['mode'] === 'topic';
    $hero = $article['hero_image'];
    // Ueberschriften: viel Luft davor, wenig danach, damit sie zum folgenden Absatz gehoeren
    $proseClasses = 'mt-8 space-y-4 text-base leading-relaxed [&>:first-child]:!mt-0'
        .' [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-zinc-900 [&_h2]:scroll-mt-24 [&_h2]:mt-10 [&_h2]:mb-3'
        .' [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-zinc-900 [&_h3]:scroll-mt-24 [&_h3]:mt-8 [&_h3]:mb-2'
        .' [&_a]:text-brand [&_a]:underline [&_strong]:font-semibold [&_strong]:text-zinc-900'
        .' [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2 [&_li]:marker:text-brand'
        .' [&_table]:block [&_table]:w-full [&_table]:overflow-x-auto'
        .' [&_th]:py-2 [&_th]:pr-4 [&_th]:text-left [&_th]:font-semibold [&_th]:text-zinc-900 [&_th]:border-b [&_th]:border-zinc-200'
        .' [&_td]:py-3 [&_td]:pr-4 [&_td]:border-t [&_td]:border-zinc-100'
        .' [&_blockquote]:rounded-2xl [&_blockquote]:bg-brand-50 [&_blockquote]:p-5';
@endphp

@push('scripts')
    @include('guide.partials.jsonld', ['graph' => $jsonLd])
@endpush

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">
  @include('guide.partials.breadcrumb', ['items' => $breadcrumb])

  <div class="mt-4 grid gap-8 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
    <article class="min-w-0 max-w-3xl" id="blog-article">

      {{-- Artikel als Karte auf dem grauen Seitengrund --}}
      <div class="card p-5 md:p-8">
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $article['title'] }}</h1>

        {{-- Block 3: Stand-Zeile (kompakt) und Transparenzzeile --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
          @if($isTopic)
            @include('guide.partials.status-line', [
                'publishedAt' => $article['published_at'],
                'updatedAt' => $article['updated_at'],
                'checkedAt' => $article['checked_at'],
                'hasChangelog' => $article['changelog'] !== [],
                'variant' => 'kompakt',
            ])
          @endif
          @if($article['reading_time'])
            <span>{{ __('portal.layout.reading_time', ['minuten' => $article['reading_time']]) }}</span>
          @endif
          <a href="{{ route('portal.blog.editorial') }}" class="underline hover:text-brand">So entsteht dieser Ratgeber</a>
        </div>

        @if($hero)
          {{-- Titelbild: einziges Bild oberhalb der Faltung, deshalb eager --}}
          <figure class="mt-6">
            <picture>
              @if(!empty($hero['webp']))
                <source type="image/webp" srcset="{{ $hero['webp'] }}" sizes="(min-width: 1280px) 48rem, 100vw">
              @endif
              <img src="{{ $hero['src'] }}"
                   @if(!empty($hero['srcset'])) srcset="{{ $hero['srcset'] }}" sizes="(min-width: 1280px) 48rem, 100vw" @endif
                   width="{{ $hero['width'] }}" height="{{ $hero['height'] }}"
                   alt="{{ $hero['alt'] }}" fetchpriority="high" loading="eager" decoding="async"
                   class="aspect-video w-full rounded-2xl object-cover">
            </picture>
            @if(!empty($hero['credit_html']))
              <figcaption class="mt-2 text-xs text-zinc-500 [&_a]:underline">{!! $hero['credit_html'] !!}</figcaption>
            @elseif(!empty($hero['credit']))
              <figcaption class="mt-2 text-xs text-zinc-500">{{ $hero['credit'] }}</figcaption>
            @endif
          </figure>
        @endif

        {{-- Block 2: Kurzantwort — vor Inhaltsverzeichnis und jeder Anzeige --}}
        @if($article['short_answer'])
          <section class="mt-6 rounded-2xl bg-brand-50 p-5" aria-labelledby="short-answer-heading">
            <h2 id="short-answer-heading" class="text-sm font-semibold text-brand-700">Kurz gesagt</h2>
            <p class="mt-1 text-lg leading-relaxed text-zinc-900">{{ $article['short_answer'] }}</p>
          </section>
        @endif

        {{-- Block 4: Inhaltsverzeichnis (unter xl; darueber in der Seitenspalte) --}}
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
        <div class="{{ $proseClasses }}">
          {!! $article['body_before'] !!}
        </div>

        {{-- Block 7: Verweisleiste Firmensuche — ohne Anzeige davor oder danach --}}
        @include('guide.partials.cta', ['cta' => $article['cta'], 'variant' => 'bar'])

        @if($article['body_after'] !== '')
          <div class="{{ $proseClasses }}">
            {!! $article['body_after'] !!}
          </div>
        @endif

        {{-- Block 8: FAQ --}}
        @include('guide.partials.faq', ['faq' => $article['faq']])
      </div>

      {{-- Block 9: Stand-Zeile (vollstaendig) → Was ist neu? → Quellen --}}
      @if($isTopic || $article['sources'] !== [])
        <section class="card mt-8 p-5 md:p-6 text-sm text-zinc-500" aria-label="Aktualität und Quellen">
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
        <section class="mt-12" aria-labelledby="ratgeber-related-heading">
          <h2 id="ratgeber-related-heading" class="text-2xl font-semibold text-zinc-900">Verwandte Themen</h2>
          <div class="mt-6 grid sm:grid-cols-2 gap-4">
            @foreach($article['related'] as $card)
              @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
            @endforeach
          </div>
        </section>
      @endif
    </article>

    {{-- Seitenspalte ab xl: sidebar_top, klebendes Inhaltsverzeichnis, sidebar_sticky --}}
    <aside class="hidden xl:block" aria-label="Inhalt und Hinweise">
      <x-ad-slot position="sidebar_top" />
      <div class="sticky top-24 flex flex-col gap-4">
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
