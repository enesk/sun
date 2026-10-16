{{--
    Kategorieseite /ratgeber/kategorie/{slug} (#17) im Theme sun-v2. Gleiche
    Daten wie guide/category (GuideController::category()), Layout layouts.sun.
    Meta setzt der SeoService. Liste statt Raster, Reihenfolge der Themenliste.
--}}
@extends('layouts.sun')

@php
    $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;

    // Listen-Anker (#5): nach der 5. Themenkarte. Die Liste ist hier
    // einspaltig (flex flex-col gap-4), darum ist der Anker zulaessig —
    // anders als im Karten-Raster der Uebersicht. `minFollowing: 1` haelt die
    // bestehende Bedingung "nur ab 6 Karten".
    $feedAds = \App\Ads\Support\ListSlotHelper::make([5 => 'guide_index_in_feed_5'], $topics->count(), 1);
@endphp

@push('scripts')
    @include('guide.partials.jsonld', ['graph' => $jsonLd])
@endpush

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">
  @include('guide.partials.breadcrumb', ['items' => $breadcrumb])

  <div class="mt-4 grid gap-8 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
    <div class="min-w-0 max-w-3xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $category['name'] }}</h1>

      @if($category['intro_html'])
        <div class="mt-3 space-y-3 text-base md:text-lg leading-relaxed text-zinc-700 [&_a]:text-brand [&_a]:underline">{!! $category['intro_html'] !!}</div>
      @elseif($category['description'])
        <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">{{ $category['description'] }}</p>
      @endif

      <p class="mt-3 text-sm text-zinc-500">
        {{ $total }} Ratgeber
        @if($updatedAt)
          · aktualisiert <time datetime="{{ $updatedAt->copy()->timezone($tz)->toDateString() }}">{{ $updatedAt->copy()->timezone($tz)->format('d.m.Y') }}</time>
        @endif
      </p>

      <div class="mt-6 flex flex-col gap-4">
        @foreach($topics as $card)
          @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h2'])

          {{-- Anzeige nach der 5. Karte, nur wenn mindestens 6 Karten folgen --}}
          {{-- Altsystem, faellt in SUN-AD-011 --}}
          @if($loop->iteration === 5 && $loop->count >= 6)
            <x-ad-slot position="content_after_intro" />
          @endif

          @if($feedSlot = $feedAds->keyAfter($loop->iteration))
            <x-sun.ad-slot :key="$feedSlot" />
          @endif
        @endforeach
      </div>

      @if($topics->hasPages())
        <x-sun.pagination :paginator="$topics" :pages="\App\Themes\SunV2\PageWindow::for($topics)" />
      @endif

      <x-sun.ad-slot key="guide_index_after_first_block" wrap="mt-12" />

      @if($otherCategories !== [])
        <section class="mt-12 xl:hidden" aria-labelledby="ratgeber-more-heading">
          <h2 id="ratgeber-more-heading" class="text-2xl font-semibold text-zinc-900">Weitere Themen</h2>
          <ul class="mt-4 flex flex-wrap gap-2" role="list">
            @foreach($otherCategories as $other)
              <li><a href="{{ $other['url'] }}" class="pill hover:bg-zinc-200">{{ $other['name'] }}</a></li>
            @endforeach
          </ul>
        </section>
      @endif
    </div>

    <aside class="hidden xl:block" aria-label="Weitere Themen">
      {{-- Altsystem, faellt in SUN-AD-011. `guide_sidebar_top` ist kein
           `guide_index`-Anker, der Katalog hat hier nur die klebende
           Seitenspalte (docs/ad-slots.md 10). --}}
      <x-ad-slot position="sidebar_top" />
      <div class="sticky top-24 flex flex-col gap-4">
        @if($otherCategories !== [])
          <nav class="card p-5" aria-labelledby="ratgeber-sidebar-more">
            <h2 id="ratgeber-sidebar-more" class="font-semibold text-zinc-900">Weitere Themen</h2>
            <ul class="mt-2 space-y-1 text-sm" role="list">
              @foreach($otherCategories as $other)
                <li><a href="{{ $other['url'] }}" class="block py-1 hover:text-brand">{{ $other['name'] }}</a></li>
              @endforeach
            </ul>
          </nav>
        @endif
        {{-- Altsystem, faellt in SUN-AD-011 --}}
        <x-ad-slot position="sidebar_sticky" />

        <x-sun.ad-slot key="guide_index_sidebar_sticky" />
      </div>
    </aside>
  </div>

  {{-- Altsystem, faellt in SUN-AD-011 --}}
  <x-ad-slot position="footer_above" />

  <x-sun.ad-slot key="guide_index_before_footer" wrap="mt-12" />
</div>
@endsection
