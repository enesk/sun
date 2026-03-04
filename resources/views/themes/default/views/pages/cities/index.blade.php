@extends('layouts.app')

@section('title', 'Top-Städte — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Die ' . $totalCities . ' Städte mit den meisten Unternehmen. Finden Sie ' . number_format($totalCompanies) . ' Firmen in Ihrer Nähe.')

@section('content')

    {{-- Schema.org: CollectionPage --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Top-Städte',
        'description' => 'Die Städte mit den meisten Firmeneinträgen im Überblick.',
        'url' => route('portal.cities.index'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => route('home'),
        ],
        'numberOfItems' => $totalCities,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Städte'],
    ]])

    {{-- Mini-Hero --}}
    <div class="city-hero mb-8" role="banner">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center max-w-2xl mx-auto">
                <div class="city-hero__badge mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ number_format($totalCompanies) }} Unternehmen in {{ $totalCities }} Städten
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Top {{ $totalCities }} Städte</h1>
                <p class="text-white/80 text-sm sm:text-base">Die Städte mit den meisten Unternehmen</p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-12">

        {{-- Top 20 Städte-Grid --}}
        @if($cities->isNotEmpty())
            <div class="cities-grid">
                @foreach($cities as $index => $city)
                    <a href="{{ route('portal.cities.show', $city->slug) }}"
                       class="city-card">
                        <div class="city-card__icon">
                            <span class="text-sm font-bold" style="color: var(--portal-primary)">{{ $index + 1 }}</span>
                        </div>
                        <div>
                            <div class="city-card__name">{{ $city->name }}</div>
                            <div class="city-card__count">{{ number_format($city->companies_count) }} {{ $city->companies_count === 1 ? 'Firma' : 'Firmen' }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            @include('components.empty-state', [
                'icon' => 'map-pin',
                'title' => 'Keine Städte',
                'message' => 'Es sind noch keine Städte mit Firmeneinträgen vorhanden.',
                'action' => ['url' => route('home'), 'label' => 'Zur Startseite', 'icon' => 'back'],
            ])
        @endif
    </div>

@endsection
