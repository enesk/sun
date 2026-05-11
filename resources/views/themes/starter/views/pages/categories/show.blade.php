@extends('layouts.app')

@section('title', $category->name . ' — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', $category->description ?? ('Firmen in der Kategorie ' . $category->name . '. ' . $category->companies_count . ' Unternehmen gefunden.'))

@if(request('q') || request('sort') || request('city') || request('page'))
@section('meta_robots', 'noindex, follow')
@endif
@section('canonical', route('portal.categories.show', $category->slug))

@section('content')

    {{-- Schema.org: CollectionPage für Kategorie --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $category->name,
        'description' => $category->description ?? ('Firmen in der Kategorie ' . $category->name),
        'url' => route('portal.categories.show', $category->slug),
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
    @include('components.breadcrumb', ['items' => $breadcrumb])

    <div class="container mx-auto px-4 pb-12">

        {{-- Kategorie-Header --}}
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                @if($category->icon)
                    <i data-lucide="{{ $category->icon }}" class="w-8 h-8 text-portal-primary-dark" aria-hidden="true"></i>
                @endif
                <h1 class="text-2xl font-bold text-base-content">{{ $category->name }}</h1>
            </div>
            @if($category->description)
                <p class="text-sm text-base-content/60 max-w-2xl">{{ $category->description }}</p>
            @endif
            <p class="text-sm text-base-content/50 mt-1">
                {{ number_format($companies->total()) }} {{ $companies->total() === 1 ? 'Unternehmen' : 'Unternehmen' }} in dieser Kategorie
            </p>
        </div>

        {{-- Suchleiste (innerhalb Kategorie) --}}
        <div class="mb-8">
            <form action="{{ route('portal.categories.show', $category->slug) }}" method="GET" role="search"
                  class="bg-base-100 rounded-xl shadow-sm border border-base-200 p-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <label for="category-search" class="sr-only">In {{ $category->name }} suchen</label>
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="search" name="q" id="category-search"
                               value="{{ request('q') }}"
                               placeholder="In {{ $category->name }} suchen..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-base-300 bg-base-100 text-base-content placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent transition-shadow ring-portal"
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn-portal px-5 py-2.5 rounded-lg text-sm transition-opacity hover:opacity-90 shrink-0">
                        Suchen
                    </button>
                </div>
            </form>
        </div>

        {{-- Sortierung --}}
        <div class="flex items-center justify-end gap-2 mb-6">
            <label for="cat-sort-select" class="text-sm text-base-content/60 shrink-0">Sortierung:</label>
            <select id="cat-sort-select"
                    class="py-1.5 px-3 rounded-lg border border-base-300 bg-base-100 text-sm focus:outline-none focus:ring-2 focus:border-transparent ring-portal"
                    onchange="window.location.href=this.value">
                <option value="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}" {{ ($sort ?? 'name') === 'name' ? 'selected' : '' }}>A–Z</option>
                <option value="{{ request()->fullUrlWithQuery(['sort' => 'rating']) }}" {{ ($sort ?? '') === 'rating' ? 'selected' : '' }}>Beste Bewertung</option>
                <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" {{ ($sort ?? '') === 'newest' ? 'selected' : '' }}>Neueste</option>
            </select>
        </div>

        {{-- Main Content --}}
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Sidebar --}}
            <div class="hidden lg:block lg:w-64 shrink-0">
                @include('components.sidebar', [
                    'categories' => $allCategories,
                    'cities' => $cities,
                    'activeCategory' => $category->slug,
                    'activeCity' => request('city'),
                ])
            </div>

            {{-- Mobile Filter --}}
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
                        'categories' => $allCategories,
                        'cities' => $cities,
                        'activeCategory' => $category->slug,
                        'activeCity' => request('city'),
                    ])
                </div>
            </div>

            {{-- Firmen-Grid --}}
            <div class="flex-1 min-w-0">
                @if($companies->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($companies as $company)
                            @include('components.company-card', ['company' => $company, 'layout' => $themeOptions['listing_layout'] ?? 'grid'])
                        @endforeach
                    </div>

                    <div class="mt-8">
                        @include('components.pagination', ['paginator' => $companies])
                    </div>
                @else
                    @include('components.empty-state', [
                        'icon' => 'search',
                        'title' => 'Keine Firmen in ' . $category->name,
                        'message' => request('q')
                            ? 'Für "' . e(request('q')) . '" wurden keine Ergebnisse in dieser Kategorie gefunden.'
                            : 'In dieser Kategorie sind noch keine Firmen eingetragen.',
                        'action' => ['url' => route('portal.companies.index'), 'label' => 'Alle Firmen anzeigen', 'icon' => 'back'],
                    ])
                @endif
            </div>
        </div>
    </div>

@endsection
