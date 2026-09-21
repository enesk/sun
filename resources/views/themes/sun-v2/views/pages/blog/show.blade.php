{{--
    Ratgeber-Artikel im Theme sun-v2 (Vorlage elektrikerportal-ratgeber.html).
    Daten: PublicBlogController::show() bzw. RatgeberPreviewController — dieselben
    Variablen wie im Default-Theme. Meta-Angaben kommen aus ArticleSeoService (#17).

    Der Artikeltext ($bodyBefore/$bodyAfter) ist generiertes HTML ohne Klassen;
    die Gestaltung der Vorlage (H2, Tabellen, Listen) sitzt deshalb als
    Tailwind-Varianten am umschliessenden Container, nicht in eigenen CSS-Klassen.
--}}
@extends('layouts.sun')

@php
    $mainHeadings = array_values(array_filter($headings, fn (array $h): bool => $h['level'] === 2));
    if (! empty($faq)) {
        $mainHeadings[] = ['id' => 'faq', 'text' => __('portal.blog.show.faq_heading'), 'level' => 2];
    }
    $lead = $shortAnswer ?: $post->excerpt;
    $ctaCount = (int) ($region['company_count'] ?? 0);
    // Ueberschriften: viel Luft davor, wenig danach, damit sie zum folgenden
    // Absatz gehoeren statt zwischen zwei Absaetzen zu schweben.
    $proseClasses = 'space-y-4 text-base leading-relaxed [&>:first-child]:!mt-0'
        .' [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-zinc-900 [&_h2]:scroll-mt-24 [&_h2]:mt-10 [&_h2]:mb-3'
        .' [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-zinc-900 [&_h3]:scroll-mt-24 [&_h3]:mt-8 [&_h3]:mb-2'
        .' [&_a]:text-brand [&_a]:underline [&_strong]:font-semibold [&_strong]:text-zinc-900'
        .' [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2 [&_li]:marker:text-brand'
        .' [&_table]:block [&_table]:w-full [&_table]:overflow-x-auto'
        .' [&_th]:py-2 [&_th]:pr-4 [&_th]:text-left [&_th]:font-semibold [&_th]:text-zinc-900 [&_th]:border-b [&_th]:border-zinc-200'
        .' [&_td]:py-3 [&_td]:pr-4 [&_td]:border-t [&_td]:border-zinc-100'
        .' [&_blockquote]:rounded-2xl [&_blockquote]:bg-brand-50 [&_blockquote]:p-5';
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])
@section('canonical', $seo['canonical'])
@section('og_type', $seo['og_type'])

{{-- Redaktionsvorschau eines Entwurfs (#20): niemals indexieren. --}}
@if($previewMode ?? false)
@section('meta_robots', 'noindex, nofollow')
@endif

@push('scripts')
    @include('ratgeber.partials.jsonld', ['graph' => $jsonLd])
@endpush

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">
  <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
    <article class="min-w-0 max-w-3xl">

      <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-brand hidden sm:inline">{{ __('portal.layout.breadcrumb.home') }}</a><span class="hidden sm:inline"><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /></span>
        <a href="{{ route('guide.index') }}" class="hover:text-brand">{{ __('portal.layout.header.guide') }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
        <span class="text-zinc-900" aria-current="page">{{ \Illuminate\Support\Str::limit($post->title, 60) }}</span>
      </nav>

      {{-- Artikel als Karte auf dem grauen Seitengrund, wie Inhaltsverzeichnis und CTA --}}
      <div class="card mt-4 p-5 md:p-8">
      <div class="flex flex-wrap items-center gap-3">
        @if($post->category)
          <a href="{{ route('guide.category', $post->category->slug) }}" class="pill-brand">{{ $post->category->name }}</a>
        @endif
        @if($post->reading_time_minutes)
          <span class="text-sm text-zinc-500">{{ __('portal.layout.reading_time', ['minuten' => $post->reading_time_minutes]) }}</span>
        @endif
        @if($seo['modified_at'])
          <span class="text-sm text-zinc-500">{!! __('portal.blog.show.updated_at', ['datum' => '<time datetime="'.e($seo['modified_at']->toDateString()).'">'.e($seo['modified_at']->translatedFormat('j. F Y')).'</time>']) !!}</span>
        @endif
        {{-- KI-Transparenz (#27) --}}
        <a href="{{ route('portal.blog.editorial') }}" class="text-sm text-zinc-500 hover:text-brand underline">{{ __('portal.blog.show.ai_notice') }}</a>
      </div>

      <h1 class="mt-3 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $post->title }}</h1>
      @if($lead)
        <p class="mt-4 text-lg leading-relaxed text-zinc-500">{{ $lead }}</p>
      @endif

      @if($heroImage)
        {{-- Titelbild aus #16: einziges Bild oberhalb der Faltung, deshalb eager. --}}
        <figure class="mt-6">
          <picture>
            @if($heroImage['webp'])
              <source type="image/webp" srcset="{{ $heroImage['webp'] }}" sizes="(min-width: 1280px) 48rem, 100vw">
            @endif
            <img src="{{ $heroImage['src'] }}"
                 @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="(min-width: 1280px) 48rem, 100vw" @endif
                 width="{{ $heroImage['width'] }}" height="{{ $heroImage['height'] }}"
                 alt="{{ $heroImage['alt'] }}" fetchpriority="high" loading="eager" decoding="async"
                 class="aspect-video w-full rounded-2xl object-cover">
          </picture>
          @if($heroImage['credit_html'] ?? null)
            <figcaption class="mt-2 text-xs text-zinc-500 [&_a]:underline">{!! $heroImage['credit_html'] !!}</figcaption>
          @elseif($heroImage['credit'] ?? null)
            <figcaption class="mt-2 text-xs text-zinc-500">{{ $heroImage['credit'] }}</figcaption>
          @endif
        </figure>
      @endif

      <!-- Inhaltsverzeichnis -->
      @if(count($mainHeadings) >= 3)
        {{-- Liegt jetzt in der Artikelkarte: getoente Flaeche statt Karte in der Karte --}}
        <nav class="rounded-2xl bg-zinc-50 p-5 mt-6 xl:hidden" aria-labelledby="toc">
          <h2 id="toc" class="font-semibold text-zinc-900">{{ __('portal.blog.show.toc_heading') }}</h2>
          <ol class="mt-1 divide-y divide-zinc-100">
            @foreach($mainHeadings as $heading)
              <li><a href="#{{ $heading['id'] }}" class="flex items-start gap-2 min-h-11 py-2 hover:text-brand"><span class="text-zinc-400 tabular-nums">{{ $loop->iteration }}.</span><span>{{ $heading['text'] }}</span></a></li>
            @endforeach
          </ol>
        </nav>
      @endif

      @if(!empty($keyFacts['rows']))
        <div class="mt-8 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
          <table class="w-full text-base">
            <caption class="text-left font-semibold text-zinc-900 pb-2">{{ __('portal.blog.show.key_facts_caption') }}</caption>
            <tbody class="divide-y divide-zinc-100">
              @foreach($keyFacts['rows'] as $row)
                <tr><th scope="row" class="py-3 pr-4 text-left font-normal text-zinc-700">{{ $row['label'] }}</th><td class="py-3 {{ $keyFacts['numeric'] ? 'tabular-nums whitespace-nowrap' : '' }} font-semibold text-zinc-900">{{ $row['value'] }}</td></tr>
              @endforeach
            </tbody>
          </table>
          @if(!empty($keyFacts['caption']))
            <p class="mt-2 text-sm text-zinc-500">{{ $keyFacts['caption'] }}</p>
          @endif
        </div>
      @endif

      <div class="mt-8 {{ $proseClasses }}">

        {{-- Anzeige: fruehestens nach der Key-Facts-Tabelle --}}
        <x-ad-slot position="content_after_intro" />

        {!! $bodyBefore !!}

        {{-- Regionalblock — ohne Anzeige davor oder danach --}}
        @if(!empty($region))
          <section class="rounded-2xl bg-brand-50 p-5 flex gap-4" aria-labelledby="region-heading">
            <span class="text-brand shrink-0"><x-sun.icon name="map-pin" class="size-6" /></span>
            <div class="min-w-0">
              <h2 id="region-heading" class="!text-lg !mt-0 !mb-0">{{ __('portal.blog.show.region.heading', ['stadt' => $region['name']]) }}</h2>
              @if($region['intro'])
                <p class="mt-1">{{ $region['intro'] }}</p>
              @endif
              @if(!empty($region['facts']))
                <dl class="mt-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
                  @foreach($region['facts'] as $fact)
                    <dt class="text-zinc-500">{{ $fact['label'] }}</dt>
                    <dd class="font-semibold text-zinc-900 text-right">{{ $fact['value'] }}</dd>
                  @endforeach
                </dl>
              @endif
              @if($region['outro'])
                <p class="mt-3">{{ $region['outro'] }}</p>
              @endif
              <a href="{{ $region['url'] }}" class="btn-secondary mt-4 !no-underline !text-zinc-900">{{ __('portal.blog.show.region.compare', ['stadt' => $region['name']]) }}</a>
            </div>
          </section>
        @endif

        @if($bodyAfter !== '')
          {!! $bodyAfter !!}
        @endif

        @if(!empty($faq))
          <h2 id="faq">{{ __('portal.blog.show.faq_heading') }}</h2>
          <div class="divide-y divide-zinc-200 border-y border-zinc-200">
            @foreach($faq as $item)
              <details class="group py-4"><summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium text-zinc-900 min-h-11"><span>{{ $item['question'] }}</span><x-sun.icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition-transform duration-200 group-open:rotate-180" /></summary><p class="mt-3 text-zinc-700 leading-relaxed">{{ $item['answer'] }}</p></details>
            @endforeach
          </div>
        @endif
      </div>
      </div>

      <!-- CTA -->
      <div class="card p-5 md:p-8 mt-10 bg-brand-50 border-brand-100">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ !empty($region) ? __('portal.blog.show.cta.heading_city', ['stadt' => $region['name']]) : __('portal.blog.show.cta.heading_nearby') }}</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">
          @if($ctaCount > 0)
            {{ trans_choice('portal.blog.show.cta.text_count', $ctaCount, ['anzahl' => number_format($ctaCount, 0, ',', '.')]) }}
          @else
            {{ __('portal.blog.show.cta.text') }}
          @endif
        </p>
        <form action="{{ route('portal.companies.index') }}" method="get" role="search" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
          <label class="sr-only" for="cta-ort">{{ __('portal.layout.search_form.where_placeholder') }}</label>
          <input id="cta-ort" name="ort" class="input" placeholder="{{ __('portal.layout.search_form.where_placeholder') }}" value="{{ $region['name'] ?? '' }}" autocomplete="postal-code">
          <button type="submit" class="btn-primary">{{ __('portal.blog.cta.button') }}</button>
        </form>
      </div>

      <!-- Quellen und Aktualitaet (#27) -->
      <section class="card mt-8 p-5 md:p-6 text-sm text-zinc-500" @if(!empty($sources)) aria-labelledby="sources-heading" @else aria-label="{{ __('portal.blog.show.freshness_label') }}" @endif>
        <p>
          @if($seo['published_at'])
            {!! __('portal.blog.show.published_at', ['datum' => '<time datetime="'.e($seo['published_at']->toDateString()).'">'.e($seo['published_at']->translatedFormat('j. F Y')).'</time>']) !!}
          @endif
          @if($seo['published_at'] && $seo['modified_at'])
            <span aria-hidden="true">&middot;</span>
          @endif
          @if($seo['modified_at'])
            {!! __('portal.blog.show.checked_at', ['datum' => '<time datetime="'.e($seo['modified_at']->toDateString()).'">'.e($seo['modified_at']->translatedFormat('j. F Y')).'</time>']) !!}
          @endif
        </p>
        @if(!empty($sources))
          <h2 id="sources-heading" class="mt-4 font-semibold text-zinc-900">{{ __('portal.blog.show.sources_heading') }}</h2>
          <ol class="mt-2 space-y-1.5">
            @foreach($sources as $source)
              <li>
                @if($source['publisher'])<span class="font-medium text-zinc-700">{{ $source['publisher'] }}:</span>@endif
                @if($source['url'])
                  <a href="{{ $source['url'] }}" rel="nofollow noopener" target="_blank" class="hover:text-brand underline">{{ $source['title'] }}</a>
                @else
                  {{ $source['title'] }}
                @endif
                @if($source['stale_year'])
                  <span class="text-zinc-400">{{ __('portal.blog.show.source_as_of', ['jahr' => $source['stale_year']]) }}</span>
                @elseif($source['published_at'])
                  <span class="text-zinc-400">({{ $source['published_at']->translatedFormat('j. F Y') }})</span>
                @endif
              </li>
            @endforeach
          </ol>
        @endif
      </section>

      <!-- Autorenbox (#27) -->
      <div class="card p-5 mt-8 flex gap-4">
        <span class="size-12 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0" aria-hidden="true"><x-sun.icon name="shield-check" class="size-6" /></span>
        <div class="min-w-0">
          <p class="text-sm text-zinc-500">{{ __('portal.blog.show.author.label') }}</p>
          <p class="font-semibold text-zinc-900">
            @if(!empty($authorUrl))
              <a href="{{ $authorUrl }}" rel="author" class="hover:text-brand">{{ $authorName }}</a>
            @else
              {{ $authorName }}
            @endif
          </p>
          <p class="mt-1 text-sm leading-relaxed">
            {{ __('portal.blog.show.author.process') }}
            @if($seo['modified_at'])
              {!! __('portal.blog.show.author.checked_at', ['datum' => '<time datetime="'.e($seo['modified_at']->toDateString()).'">'.e($seo['modified_at']->translatedFormat('j. F Y')).'</time>']) !!}
            @endif
          </p>
          <a href="{{ route('portal.blog.editorial') }}" class="mt-2 inline-block text-sm text-brand hover:underline">{{ __('portal.blog.list.editorial_link') }}</a>
        </div>
      </div>

      {{-- Aenderungshinweise (#24) --}}
      @if(!empty($changelog ?? []))
        <section class="card mt-6 p-5 md:p-6 text-sm" aria-labelledby="changelog-heading">
          <h2 id="changelog-heading" class="font-semibold text-zinc-900">{{ __('portal.blog.show.changelog_heading') }}</h2>
          <ol class="mt-2 divide-y divide-zinc-100">
            @foreach($changelog as $entry)
              <li class="py-2">
                <time class="text-zinc-500" datetime="{{ $entry['at']->toDateString() }}">{{ __('portal.blog.show.updated_at', ['datum' => $entry['at']->translatedFormat('j. F Y')]) }}</time>
                <p class="mt-0.5">{{ $entry['summary'] }}</p>
                @if(!empty($entry['reasons']))
                  <p class="text-zinc-500">{{ __('portal.blog.show.changelog_reasons', ['anlaesse' => implode(', ', $entry['reasons'])]) }}</p>
                @endif
              </li>
            @endforeach
          </ol>
        </section>
      @endif

      <!-- Passende Artikel -->
      @if($relatedPosts->isNotEmpty())
        <section class="mt-12">
          <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.blog.show.related_heading') }}</h2>
          <div class="mt-6 grid sm:grid-cols-2 gap-4">
            @foreach($relatedPosts->take(2) as $related)
              <a href="{{ route('guide.show', $related->slug) }}" class="card-interactive p-5 flex flex-col gap-2">
                @if($related->category)
                  <span class="pill self-start">{{ $related->category->name }}</span>
                @endif
                <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $related->title }}</h3>
                <p class="text-sm text-zinc-500 line-clamp-2">{{ $related->excerpt_or_truncated }}</p>
                @if($related->reading_time_minutes)
                  <span class="text-sm text-zinc-500 mt-auto pt-1">{{ __('portal.layout.reading_time', ['minuten' => $related->reading_time_minutes]) }}</span>
                @endif
              </a>
            @endforeach
          </div>
        </section>
      @endif
    </article>

    <!-- Rechte Spalte -->
    <aside class="hidden xl:block" aria-label="{{ __('portal.blog.show.sidebar_label') }}">
      <div class="sticky top-24 flex flex-col gap-4">
        @if(count($mainHeadings) >= 3)
          <nav class="card p-5" aria-label="{{ __('portal.blog.show.toc_sidebar_label') }}">
            <p class="font-semibold text-zinc-900">{{ __('portal.blog.show.toc_sidebar_heading') }}</p>
            <ol class="mt-2 space-y-1 text-sm">
              @foreach($mainHeadings as $heading)
                <li><a href="#{{ $heading['id'] }}" class="block py-1 hover:text-brand">{{ $heading['text'] }}</a></li>
              @endforeach
            </ol>
          </nav>
        @endif
        <x-ad-slot position="sidebar_sticky" />
      </div>
    </aside>
  </div>
</div>
@endsection
