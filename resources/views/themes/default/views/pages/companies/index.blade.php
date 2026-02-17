@extends('layouts.app')

@section('title', 'Firmenverzeichnis — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Alle Unternehmen im Überblick. Finden Sie lokale Firmen, Handwerker und Dienstleister in Ihrer Nähe.')

@if(request('q') || request('sort') || request('category') || request('city') || request('page'))
@section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.companies.index'))

@section('content')

    {{-- Schema.org: CollectionPage --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Firmenverzeichnis',
        'description' => 'Alle Unternehmen im Überblick. Finden Sie lokale Firmen, Handwerker und Dienstleister in Ihrer Nähe.',
        'url' => route('portal.companies.index'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => route('home'),
        ],
        'numberOfItems' => $companies->total(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Firmenverzeichnis'],
    ]])

    <div class="container mx-auto px-4 pb-12">

        {{-- Suchleiste --}}
        <div class="mb-8">
            @include('components.search-bar', [
                'action' => route('portal.companies.index'),
                'showFilters' => true,
                'categories' => $categories,
                'cities' => $cities,
            ])
        </div>

        {{-- Ergebniskopf --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-base-content">
                    @if(request('q'))
                        Suchergebnisse für &ldquo;{{ request('q') }}&rdquo;
                    @else
                        Firmenverzeichnis
                    @endif
                </h1>
                <p class="text-sm text-base-content/60 mt-1">
                    {{ number_format($companies->total()) }} {{ $companies->total() === 1 ? 'Unternehmen' : 'Unternehmen' }} gefunden
                </p>
            </div>

            {{-- Sortierung --}}
            <div class="flex items-center gap-2">
                <label for="sort-select" class="text-sm text-base-content/60 shrink-0">Sortierung:</label>
                <select id="sort-select"
                        class="py-1.5 px-3 rounded-lg border border-base-300 bg-base-100 text-sm focus:outline-none focus:ring-2 focus:border-transparent ring-portal"
                        onchange="window.location.href=this.value">
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'az']) }}" {{ $sort === 'az' ? 'selected' : '' }}>A–Z</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'rating']) }}" {{ $sort === 'rating' ? 'selected' : '' }}>Beste Bewertung</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" {{ $sort === 'newest' ? 'selected' : '' }}>Neueste</option>
                </select>
            </div>
        </div>

        {{-- Main Content: Sidebar + Firmen-Grid --}}
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Sidebar (Desktop) --}}
            <div class="hidden lg:block lg:w-64 shrink-0">
                @include('components.sidebar', [
                    'categories' => $categories,
                    'cities' => $cities,
                    'activeCategory' => request('category'),
                    'activeCity' => request('city'),
                ])
            </div>

            {{-- Mobile Filter Toggle --}}
            <div class="lg:hidden" x-data="{ open: false }">
                <button @click="open = !open"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg border border-base-300 text-sm text-base-content/70 hover:bg-base-200 transition-colors mb-4"
                        :aria-expanded="open">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter & Kategorien
                </button>
                <div x-show="open" x-collapse class="mb-6">
                    @include('components.sidebar', [
                        'categories' => $categories,
                        'cities' => $cities,
                        'activeCategory' => request('category'),
                        'activeCity' => request('city'),
                    ])
                </div>
            </div>

            {{-- Firmen-Liste --}}
            <div class="flex-1 min-w-0">
                @if($companies->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($companies as $company)
                            @include('components.company-card', ['company' => $company, 'layout' => $themeOptions['listing_layout'] ?? 'grid'])
                        @endforeach
                    </div>

                    {{-- Paginierung --}}
                    <div class="mt-8">
                        @include('components.pagination', ['paginator' => $companies])
                    </div>
                @else
                    @include('components.empty-state', [
                        'icon' => 'search',
                        'title' => 'Keine Firmen gefunden',
                        'message' => request('q')
                            ? 'Für "' . e(request('q')) . '" wurden keine Ergebnisse gefunden. Versuchen Sie einen anderen Suchbegriff oder setzen Sie die Filter zurück.'
                            : 'In dieser Kategorie sind noch keine Firmen eingetragen.',
                        'action' => ['url' => route('portal.companies.index'), 'label' => 'Alle Firmen anzeigen', 'icon' => 'back'],
                    ])
                @endif
            </div>
        </div>
    </div>

@endsection
