@extends('layouts.app')

@section('title', $metaTitle)
@section('meta_description', $metaDescription)

@if(request('q') || request('sort') || request('category') || request('page'))
@section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.cities.show', $city->slug))

@section('content')

    {{-- Schema.org: CollectionPage mit areaServed --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => "Firmen in {$city->name}",
        'description' => $metaDescription,
        'url' => route('portal.cities.show', $city->slug),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => route('home'),
        ],
        'areaServed' => array_filter([
            '@type' => 'City',
            'name' => $city->name,
            'geo' => ($city->latitude && $city->longitude) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
            ] : null,
        ]),
        'numberOfItems' => $companies->total(),
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Städte', 'url' => route('portal.cities.index')],
        ['label' => $city->name],
    ]])

    {{-- Stadt-Hero --}}
    <div class="city-hero mb-8" role="banner">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center max-w-2xl mx-auto">
                <div class="city-hero__badge mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $city->companies_count }} {{ $city->companies_count === 1 ? 'Unternehmen' : 'Unternehmen' }}
                    @if($categories->isNotEmpty())
                        &middot; {{ $categories->count() }} {{ $categories->count() === 1 ? 'Kategorie' : 'Kategorien' }}
                    @endif
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Firmen in {{ $city->name }}</h1>
                @if($city->administrative_area_level_1)
                    <p class="text-white/80 text-sm sm:text-base">{{ $city->administrative_area_level_1 }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-12">

        {{-- Introtext (KI-generiert oder manuell) --}}
        @if($city->cityContent?->intro_text)
            <div class="city-intro mb-10" x-data="{ expanded: false }">
                <div class="city-intro__text"
                     x-ref="introText"
                     :class="{ 'line-clamp-4 sm:line-clamp-none': !expanded }">
                    {!! $city->cityContent->intro_text !!}
                </div>
                <button class="city-intro__toggle"
                        @click="expanded = !expanded"
                        x-text="expanded ? 'Weniger anzeigen' : 'Weiterlesen'"
                        aria-expanded="false"
                        :aria-expanded="expanded.toString()">
                    Weiterlesen
                </button>
            </div>
        @endif

        {{-- Suchleiste --}}
        <div class="mb-8">
            <form action="{{ route('portal.cities.show', $city->slug) }}" method="GET" role="search"
                  class="bg-base-100 rounded-xl shadow-sm border border-base-200 p-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <label for="city-company-search" class="sr-only">In {{ $city->name }} suchen</label>
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="search" name="q" id="city-company-search"
                               value="{{ request('q') }}"
                               placeholder="Firma in {{ $city->name }} suchen..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-base-300 bg-base-100 text-base-content placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent ring-portal"
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn-portal px-5 py-2.5 rounded-lg text-sm transition-opacity hover:opacity-90 shrink-0">
                        Suchen
                    </button>
                </div>
            </form>
        </div>

        {{-- Kategorie-Pill-Filter --}}
        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-6" role="list" aria-label="Kategorie-Filter">
                <a href="{{ route('portal.cities.show', $city->slug) }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                          {{ !request('category') ? 'bg-portal-primary/10 text-portal-primary-dark' : 'bg-base-200 text-base-content/60 hover:bg-base-300' }}"
                   role="listitem">
                    Alle
                </a>
                @foreach($categories->take(15) as $category)
                    <a href="{{ route('portal.cities.show', ['slug' => $city->slug, 'category' => $category->slug]) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                              {{ request('category') === $category->slug ? 'bg-portal-primary/10 text-portal-primary-dark' : 'bg-base-200 text-base-content/60 hover:bg-base-300' }}"
                       role="listitem">
                        {{ $category->name }}
                        <span class="text-xs opacity-60">({{ $category->companies_count }})</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Sortierung --}}
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-base-content/60">
                {{ number_format($companies->total()) }} {{ $companies->total() === 1 ? 'Ergebnis' : 'Ergebnisse' }}
            </p>
            <div class="flex items-center gap-2">
                <label for="city-sort-select" class="text-sm text-base-content/60 shrink-0">Sortierung:</label>
                <select id="city-sort-select"
                        class="py-1.5 px-3 rounded-lg border border-base-300 bg-base-100 text-sm focus:outline-none focus:ring-2 focus:border-transparent ring-portal"
                        onchange="window.location.href=this.value">
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}" {{ ($sort ?? 'name') === 'name' ? 'selected' : '' }}>A–Z</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'rating']) }}" {{ ($sort ?? '') === 'rating' ? 'selected' : '' }}>Beste Bewertung</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" {{ ($sort ?? '') === 'newest' ? 'selected' : '' }}>Neueste</option>
                </select>
            </div>
        </div>

        {{-- Firmen-Grid --}}
        @if($companies->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($companies as $company)
                    @include('components.company-card', ['company' => $company, 'layout' => 'grid'])
                @endforeach
            </div>

            <div class="mt-8">
                @include('components.pagination', ['paginator' => $companies])
            </div>
        @else
            @include('components.empty-state', [
                'icon' => 'search',
                'title' => 'Keine Firmen in ' . $city->name,
                'message' => request('q')
                    ? 'Für "' . e(request('q')) . '" wurden keine Ergebnisse in ' . $city->name . ' gefunden.'
                    : 'In ' . $city->name . ' sind noch keine Firmen eingetragen.',
                'action' => ['url' => route('portal.cities.index'), 'label' => 'Alle Städte anzeigen', 'icon' => 'back'],
            ])
        @endif

        {{-- Verwandte Städte --}}
        @if($relatedCities->isNotEmpty())
            <section class="mt-12 pt-8 border-t border-base-200" aria-labelledby="related-cities-heading">
                <h2 id="related-cities-heading" class="text-lg font-bold text-base-content mb-4">
                    Weitere Städte in {{ $city->administrative_area_level_1 ?? 'der Region' }}
                </h2>
                <div class="city-related">
                    @foreach($relatedCities as $related)
                        <a href="{{ route('portal.cities.show', $related->slug) }}" class="city-related__link">
                            <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $related->name }}
                            <span class="text-xs opacity-50">({{ $related->companies_count }})</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

@endsection
