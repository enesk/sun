{{--
    Kategorieseite /ratgeber/kategorie/{slug} (#17, design/guide-frontend.md, §2).
    Daten: GuideController::category(). Meta, Canonical, Robots und OG setzt
    der SeoService. Liste statt Raster, Reihenfolge der Themenliste.
--}}
@extends('layouts.app')

@section('content')
    @push('scripts')
        @include('guide.partials.jsonld', ['graph' => $jsonLd])
    @endpush

    @php
        $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
    @endphp

    <div class="ratgeber-page">
        @include('guide.partials.breadcrumb', ['items' => $breadcrumb])

        <div class="ratgeber-layout">
            <div class="ratgeber-layout__main">
                <h1 class="ratgeber-title">{{ $category['name'] }}</h1>

                @if($category['intro_html'])
                    <div class="ratgeber-intro">{!! $category['intro_html'] !!}</div>
                @elseif($category['description'])
                    <p class="ratgeber-lead">{{ $category['description'] }}</p>
                @endif

                <p class="ratgeber-meta">
                    {{ $total }} Ratgeber
                    @if($updatedAt)
                        <span aria-hidden="true">&middot;</span>
                        aktualisiert <time datetime="{{ $updatedAt->copy()->timezone($tz)->toDateString() }}">{{ $updatedAt->copy()->timezone($tz)->format('d.m.Y') }}</time>
                    @endif
                </p>

                <div class="ratgeber-list">
                    @foreach($topics as $card)
                        @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h2'])

                        {{-- Anzeige nach der 5. Karte, nur wenn mindestens 6 Karten folgen --}}
                        @if($loop->iteration === 5 && $loop->count >= 6)
                            <x-ad-slot position="content_after_intro" />
                        @endif
                    @endforeach
                </div>

                @if($topics->hasPages())
                    <div class="ratgeber-pagination">
                        {{ $topics->links() }}
                    </div>
                @endif

                @if($otherCategories !== [])
                    <section class="ratgeber-section ratgeber-section--chips" aria-labelledby="ratgeber-more-heading">
                        <h2 id="ratgeber-more-heading" class="ratgeber-section__heading">Weitere Themen</h2>
                        <ul class="ratgeber-chips" role="list">
                            @foreach($otherCategories as $other)
                                <li><a href="{{ $other['url'] }}" class="ratgeber-chip">{{ $other['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="ratgeber-sidebar" aria-label="Weitere Themen">
                <x-ad-slot position="sidebar_top" />
                @if($otherCategories !== [])
                    <nav class="ratgeber-sidebar__card" aria-labelledby="ratgeber-sidebar-more">
                        <h2 id="ratgeber-sidebar-more" class="ratgeber-sidebar__search-title">Weitere Themen</h2>
                        <ul class="ratgeber-sidebar__links" role="list">
                            @foreach($otherCategories as $other)
                                <li><a href="{{ $other['url'] }}">{{ $other['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
                <div class="ratgeber-sidebar__sticky">
                    <x-ad-slot position="sidebar_sticky" />
                </div>
            </aside>
        </div>

        <x-ad-slot position="footer_above" />
    </div>
@endsection
