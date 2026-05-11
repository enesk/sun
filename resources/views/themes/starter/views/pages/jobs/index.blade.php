@extends('layouts.app')

@section('title', 'Stellenanzeigen — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Aktuelle Stellenanzeigen und Jobs in Ihrer Region. Finden Sie Ihren nächsten Job bei lokalen Unternehmen.')

@if(request('q') || request('sort') || request('type') || request('city') || request('page'))
@section('meta_robots', 'noindex, follow')
@endif

@section('content')

    {{-- Schema.org: CollectionPage --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Stellenanzeigen — ' . ($currentTenant->name ?? config('app.name')),
        'description' => 'Aktuelle Stellenanzeigen und Jobs in Ihrer Region.',
        'url' => route('portal.jobs.index'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => url('/'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', [
        'items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Stellenanzeigen'],
        ]
    ])

    {{-- Mini-Hero --}}
    <div class="job-hero mb-8" role="banner">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center max-w-2xl mx-auto">
                <div class="job-hero__counter mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ number_format($totalJobs) }} {{ $totalJobs === 1 ? 'Stelle' : 'Stellen' }} verfügbar
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">
                    @if(request('q'))
                        Stellenanzeigen für &ldquo;{{ request('q') }}&rdquo;
                    @else
                        Aktuelle Stellenanzeigen
                    @endif
                </h1>
                <p class="text-white/80 text-sm sm:text-base">
                    Finden Sie Ihren nächsten Job bei lokalen Unternehmen in Ihrer Region.
                </p>
            </div>
        </div>
        {{-- Dekorativer Kreis --}}
        <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-10 -translate-y-1/2 translate-x-1/4"
             style="background: radial-gradient(circle, white, transparent 70%);" aria-hidden="true"></div>
    </div>

    <div class="container mx-auto px-4 pb-12">

        {{-- Suchfeld --}}
        <form action="{{ route('portal.jobs.index') }}" method="GET" class="job-search mb-8" role="search" aria-label="Stellenanzeigen durchsuchen">
            <div class="job-search__input">
                <svg class="job-search__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       placeholder="Jobtitel, Beschreibung oder Stichwort..."
                       aria-label="Suchbegriff eingeben">
            </div>
            {{-- Erhaltung bestehender Filter --}}
            @if(request('type'))
                <input type="hidden" name="type" value="{{ request('type') }}">
            @endif
            @if(request('city'))
                <input type="hidden" name="city" value="{{ request('city') }}">
            @endif
            @if(request('sort') && request('sort') !== 'newest')
                <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            <button type="submit" class="px-6 py-3 rounded-xl text-sm font-medium text-white btn-portal transition-colors hover:opacity-90 shrink-0">
                Suchen
            </button>
        </form>

        {{-- Aktive Filter anzeigen --}}
        @if(request('q') || request('type') || request('city'))
            <div class="job-filter-tags max-w-3xl mx-auto" aria-label="Aktive Filter">
                <span class="text-sm text-base-content/60">Filter:</span>
                @if(request('q'))
                    <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}"
                       class="job-filter-tag" aria-label="Suchfilter „{{ request('q') }}" entfernen">
                        &ldquo;{{ request('q') }}&rdquo;
                        <svg class="job-filter-tag__remove" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if(request('type') && isset(\App\Models\Portal\Job::EMPLOYMENT_TYPES[request('type')]))
                    <a href="{{ request()->fullUrlWithQuery(['type' => null]) }}"
                       class="job-filter-tag" aria-label="Filter „{{ \App\Models\Portal\Job::EMPLOYMENT_TYPES[request('type')] }}" entfernen">
                        {{ \App\Models\Portal\Job::EMPLOYMENT_TYPES[request('type')] }}
                        <svg class="job-filter-tag__remove" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if(request('city'))
                    <a href="{{ request()->fullUrlWithQuery(['city' => null]) }}"
                       class="job-filter-tag" aria-label="Stadtfilter „{{ request('city') }}" entfernen">
                        {{ request('city') }}
                        <svg class="job-filter-tag__remove" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                <a href="{{ route('portal.jobs.index') }}" class="text-xs text-base-content/40 hover:text-base-content/60 transition-colors">
                    Alle Filter entfernen
                </a>
            </div>
        @endif

        {{-- Ergebnis-Header + Sortierung --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <p class="text-sm text-base-content/60" aria-live="polite">
                {{ number_format($jobs->total()) }} {{ $jobs->total() === 1 ? 'Stelle' : 'Stellen' }} gefunden
            </p>
            <div class="flex items-center gap-2">
                <label for="sort-select" class="text-sm text-base-content/60 shrink-0">Sortierung:</label>
                <select id="sort-select"
                        class="text-sm border border-base-200 rounded-lg px-3 py-1.5 bg-white text-base-content focus:outline-none focus:ring-2 focus:ring-portal-primary/30"
                        onchange="window.location.href=this.value"
                        aria-label="Sortierung ändern">
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" {{ $sort === 'newest' ? 'selected' : '' }}>Neueste</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'az']) }}" {{ $sort === 'az' ? 'selected' : '' }}>A–Z</option>
                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'salary']) }}" {{ $sort === 'salary' ? 'selected' : '' }}>Gehalt (höchstes zuerst)</option>
                </select>
            </div>
        </div>

        {{-- Hauptinhalt: Sidebar + Jobs --}}
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Desktop Sidebar --}}
            <aside class="hidden lg:block lg:w-64 shrink-0" aria-label="Stellenanzeigen filtern">
                <div class="sticky top-24 space-y-6">

                    {{-- Beschäftigungsart-Filter --}}
                    <nav class="job-filter__section" aria-label="Nach Beschäftigungsart filtern">
                        <h3 class="job-filter__title">Beschäftigungsart</h3>
                        <div class="space-y-0.5">
                            @foreach($employmentTypes as $slug => $type)
                                <a href="{{ request()->fullUrlWithQuery(['type' => request('type') === $slug ? null : $slug, 'page' => null]) }}"
                                   class="job-filter__item {{ request('type') === $slug ? 'job-filter__item--active' : '' }}"
                                   @if(request('type') === $slug) aria-current="true" @endif>
                                    <span>{{ $type['label'] }}</span>
                                    @if($type['count'] > 0)
                                        <span class="job-filter__count">{{ $type['count'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </nav>

                    {{-- Stadt-Filter --}}
                    @if($cities->isNotEmpty())
                        <nav class="job-filter__section" aria-label="Nach Stadt filtern">
                            <h3 class="job-filter__title">Stadt</h3>
                            <div class="space-y-0.5 max-h-64 overflow-y-auto">
                                @foreach($cities as $city)
                                    <a href="{{ request()->fullUrlWithQuery(['city' => request('city') === $city->slug ? null : $city->slug, 'page' => null]) }}"
                                       class="job-filter__item {{ request('city') === $city->slug ? 'job-filter__item--active' : '' }}"
                                       @if(request('city') === $city->slug) aria-current="true" @endif>
                                        <span class="truncate">{{ $city->name }}</span>
                                        <span class="job-filter__count shrink-0">{{ $city->jobs_count }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </nav>
                    @endif

                    {{-- CTA: Stelle ausschreiben --}}
                    <div class="p-4 rounded-xl border border-portal-primary/20 bg-portal-primary/5">
                        <h3 class="text-sm font-semibold text-base-content mb-1">Stelle ausschreiben?</h3>
                        <p class="text-xs text-base-content/60 mb-3">Erreichen Sie qualifizierte Bewerber aus Ihrer Region.</p>
                        <a href="{{ route('portal.companies.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-medium text-white btn-portal transition-colors hover:opacity-90 w-full justify-center">
                            Jetzt Firma eintragen
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </aside>

            {{-- Mobile Filter Toggle --}}
            <div class="lg:hidden" x-data="{ open: false }">
                <button @click="open = !open"
                        class="job-mobile-filter-btn mb-4"
                        :aria-expanded="open"
                        aria-controls="mobile-job-filters">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter & Kategorien
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-collapse id="mobile-job-filters" class="mb-6 p-4 rounded-xl bg-base-100 border border-base-200">
                    {{-- Beschäftigungsart --}}
                    <h3 class="text-sm font-semibold text-base-content mb-2">Beschäftigungsart</h3>
                    <div class="job-filter-chips mb-4">
                        @foreach($employmentTypes as $slug => $type)
                            <a href="{{ request()->fullUrlWithQuery(['type' => request('type') === $slug ? null : $slug, 'page' => null]) }}"
                               class="job-filter-chip {{ request('type') === $slug ? 'job-filter-chip--active' : '' }}"
                               @if(request('type') === $slug) aria-current="true" @endif>
                                {{ $type['label'] }} ({{ $type['count'] }})
                            </a>
                        @endforeach
                    </div>
                    {{-- Städte --}}
                    @if($cities->isNotEmpty())
                        <h3 class="text-sm font-semibold text-base-content mb-2">Stadt</h3>
                        <div class="job-filter-chips">
                            @foreach($cities->take(15) as $city)
                                <a href="{{ request()->fullUrlWithQuery(['city' => request('city') === $city->slug ? null : $city->slug, 'page' => null]) }}"
                                   class="job-filter-chip {{ request('city') === $city->slug ? 'job-filter-chip--active' : '' }}"
                                   @if(request('city') === $city->slug) aria-current="true" @endif>
                                    {{ $city->name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Job-Liste --}}
            <div class="flex-1 min-w-0">
                @if($jobs->isNotEmpty())
                    <div class="space-y-4" role="list" aria-label="Stellenanzeigen">
                        @foreach($jobs as $job)
                            <div role="listitem">
                                @include('components.job-card', ['job' => $job, 'layout' => 'list'])
                            </div>
                        @endforeach
                    </div>

                    {{-- Paginierung --}}
                    <div class="mt-8">
                        @include('components.pagination', ['paginator' => $jobs])
                    </div>
                @else
                    @include('components.empty-state', [
                        'icon' => 'search',
                        'title' => 'Keine Stellenanzeigen gefunden',
                        'message' => request('q')
                            ? 'Für Ihre Suche „' . request('q') . '" wurden keine Ergebnisse gefunden. Versuchen Sie andere Suchbegriffe.'
                            : 'Aktuell sind keine Stellenanzeigen verfügbar. Schauen Sie bald wieder vorbei!',
                        'action' => request('q') || request('type') || request('city')
                            ? ['url' => route('portal.jobs.index'), 'label' => 'Alle Stellen anzeigen']
                            : ['url' => route('home'), 'label' => 'Zur Startseite'],
                    ])
                @endif
            </div>
        </div>
    </div>
@endsection
