{{--
    Ratgeber-Uebersicht /ratgeber (#17, design/guide-frontend.md, §1).
    Daten: GuideController::index(). Meta, Canonical, Robots und OG setzt der
    SeoService — dieses Template schreibt keine Meta-Angaben.
    Keine Bilder ausser dem Logo im Header; Suche als reines GET-Formular.
--}}
@extends('layouts.app')

@section('content')
    @push('scripts')
        @include('guide.partials.jsonld', ['graph' => $jsonLd])
    @endpush

    <div class="ratgeber-page">
        @include('guide.partials.breadcrumb', ['items' => $breadcrumb])

        <h1 class="ratgeber-title">Ratgeber</h1>
        <p class="ratgeber-lead">Antworten auf die häufigsten Fragen – regelmäßig geprüft und bei Änderungen aktualisiert.</p>

        <form action="{{ route('guide.index') }}" method="get" role="search" class="ratgeber-search">
            <label for="ratgeber-q" class="sr-only">Ratgeber durchsuchen</label>
            <input id="ratgeber-q" type="search" name="q" value="{{ $searchTerm }}" placeholder="Ratgeber durchsuchen" class="ratgeber-search__input" maxlength="100" autocomplete="off">
            <button type="submit" class="ratgeber-search__btn" aria-label="Suchen">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                </svg>
            </button>
        </form>

        @if($searching)
            <section class="ratgeber-section" aria-labelledby="ratgeber-results-heading">
                <h2 id="ratgeber-results-heading" class="ratgeber-section__heading">
                    {{ count($results) }} Treffer für „{{ $searchTerm }}“
                </h2>
                @if($results !== [])
                    <div class="ratgeber-list">
                        @foreach($results as $card)
                            @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
                        @endforeach
                    </div>
                @else
                    <p class="ratgeber-empty">Zu diesem Suchbegriff gibt es noch keinen Ratgeber. <a href="{{ route('guide.index') }}">Alle Themen ansehen</a></p>
                @endif
            </section>
        @elseif($singleCategory)
            <section class="ratgeber-section" aria-labelledby="ratgeber-topics-heading">
                <h2 id="ratgeber-topics-heading" class="ratgeber-section__heading">{{ $singleCategory['name'] }}</h2>
                <div class="ratgeber-list">
                    @foreach($singleCategoryTopics as $card)
                        @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
                    @endforeach
                </div>
            </section>
        @else
            <section class="ratgeber-section" aria-labelledby="ratgeber-categories-heading">
                <h2 id="ratgeber-categories-heading" class="ratgeber-section__heading">Themen</h2>
                <div class="ratgeber-grid">
                    @foreach($tiles as $tile)
                        @include('guide.partials.category-tile', ['tile' => $tile])
                    @endforeach
                </div>
            </section>
        @endif

        @if(! $searching && $recent !== [])
            <section class="ratgeber-section" aria-labelledby="ratgeber-recent-heading">
                <h2 id="ratgeber-recent-heading" class="ratgeber-section__heading">Zuletzt aktualisiert</h2>
                <div class="ratgeber-list ratgeber-list--two">
                    @foreach($recent as $card)
                        @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
                    @endforeach
                </div>
            </section>
        @endif

        <x-ad-slot position="footer_above" />
    </div>
@endsection
