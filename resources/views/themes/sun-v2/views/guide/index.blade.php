{{--
    Ratgeber-Uebersicht /ratgeber (#17) im Theme sun-v2. Gleiche Daten wie
    guide/index (GuideController::index()), Layout layouts.sun statt layouts.app.
    Meta setzt der SeoService. Suche als reines GET-Formular.
--}}
@extends('layouts.sun')

@push('scripts')
    @include('guide.partials.jsonld', ['graph' => $jsonLd])
@endpush

@section('content')
<section class="bg-brand-50 py-10 md:py-14">
  <div class="container-portal">
    @include('guide.partials.breadcrumb', ['items' => $breadcrumb])
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Ratgeber</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">Antworten auf die häufigsten Fragen – regelmäßig geprüft und bei Änderungen aktualisiert.</p>
    </div>
    <form action="{{ route('guide.index') }}" method="get" role="search" class="mt-6 max-w-2xl flex gap-2">
      <label for="ratgeber-q" class="sr-only">Ratgeber durchsuchen</label>
      <input id="ratgeber-q" type="search" name="q" value="{{ $searchTerm }}" placeholder="Ratgeber durchsuchen" class="input flex-1 min-w-0" maxlength="100" autocomplete="off">
      <button type="submit" class="btn-primary shrink-0" aria-label="Suchen"><x-sun.icon name="search" class="icon" /><span class="hidden sm:inline">Suchen</span></button>
    </form>
  </div>
</section>

<div class="container-portal py-10 md:py-12">
  @if($searching)
    <section aria-labelledby="ratgeber-results-heading">
      <h2 id="ratgeber-results-heading" class="text-2xl font-semibold text-zinc-900">{{ count($results) }} Treffer für „{{ $searchTerm }}“</h2>
      @if($results !== [])
        <div class="mt-6 grid sm:grid-cols-2 gap-4">
          @foreach($results as $card)
            @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
          @endforeach
        </div>
      @else
        <p class="mt-4 text-zinc-700">Zu diesem Suchbegriff gibt es noch keinen Ratgeber. <a href="{{ route('guide.index') }}" class="text-brand underline">Alle Themen ansehen</a></p>
      @endif
    </section>
  @elseif($singleCategory)
    <section aria-labelledby="ratgeber-topics-heading">
      <h2 id="ratgeber-topics-heading" class="text-2xl font-semibold text-zinc-900">{{ $singleCategory['name'] }}</h2>
      <div class="mt-6 grid sm:grid-cols-2 gap-4">
        @foreach($singleCategoryTopics as $card)
          @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
        @endforeach
      </div>
    </section>
  @else
    <section aria-labelledby="ratgeber-categories-heading">
      <h2 id="ratgeber-categories-heading" class="text-2xl font-semibold text-zinc-900">Themen</h2>
      <div class="mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($tiles as $tile)
          @include('guide.partials.category-tile', ['tile' => $tile])
        @endforeach
      </div>
    </section>
  @endif

  {{-- Nach dem ersten gerenderten Block — genau einer der drei Zweige greift.
       `guide_index_in_feed_5` entfaellt auf dieser View: die Themenkarten
       stehen in einem Grid mit mehr als einer Spalte (sm:grid-cols-2), dort
       verschiebt ein zusaetzliches Kind die ganze Folgereihe
       (docs/ad-slots.md 10, Anker-Regel 3.2).
       `guide_index_sidebar_sticky` entfaellt ebenfalls: die Uebersicht ist
       auf allen Breiten einspaltig (container-portal ohne Spaltenraster), es
       gibt keine Seitenspalte, in die ein klebender Anker gehoerte. Der
       Anker steht darum nur auf der Kategorieseite — seit #27 steht das als
       `routes => ['guide.category']` im Katalog, nicht nur im Kommentar:
       das Panel bietet ihn fuer diese Seite nicht mehr als belegbar an. --}}
  <x-sun.ad-slot key="guide_index_after_first_block" wrap="mt-12" />

  @if(! $searching && $recent !== [])
    <section class="mt-12" aria-labelledby="ratgeber-recent-heading">
      <h2 id="ratgeber-recent-heading" class="text-2xl font-semibold text-zinc-900">Zuletzt aktualisiert</h2>
      <div class="mt-6 grid sm:grid-cols-2 gap-4">
        @foreach($recent as $card)
          @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
        @endforeach
      </div>
    </section>
  @endif

  {{-- Altsystem, faellt in SUN-AD-011 --}}
  <x-ad-slot position="footer_above" />

  <x-sun.ad-slot key="guide_index_before_footer" wrap="mt-12" />
</div>
@endsection
