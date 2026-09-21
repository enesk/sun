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

  <x-ad-slot position="footer_above" />
</div>
@endsection
