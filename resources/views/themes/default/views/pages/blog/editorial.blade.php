@extends('layouts.app')

@section('title', 'So arbeitet unsere Redaktion — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Wie die Ratgeber auf ' . ($currentTenant->name ?? config('app.name')) . ' entstehen: Themenauswahl, Entstehungsweg, Quellen, Aktualisierung.')

@section('content')

    {{-- Schema.org: Redaktionsgrundsätze der Organisation --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'So arbeitet unsere Redaktion',
        'url' => route('portal.blog.editorial'),
        'publisher' => [
            '@type' => 'Organization',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => url('/'),
            'publishingPrinciples' => route('portal.blog.editorial'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
    @endpush

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
        ['label' => 'So arbeitet unsere Redaktion'],
    ]])

    {{-- Mini-Hero --}}
    <div class="legal-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 14.25v4.125c0 1.243-1.007 2.25-2.25 2.25H5.625a1.875 1.875 0 01-1.875-1.875V6.75c0-1.036.84-1.875 1.875-1.875H9.75" />
                </svg>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">So arbeitet unsere Redaktion</h1>
            <p class="text-white/70 text-sm md:text-base">Wie die Ratgeber auf {{ $currentTenant->name ?? config('app.name') }} entstehen</p>
        </div>
    </div>

    {{-- Content Card --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="max-w-3xl mx-auto legal-card p-6 md:p-10">
            <div class="legal-content">
                {!! $content !!}
            </div>

            <p class="mt-8 pt-6 border-t border-[#E2E8F0] text-sm">
                <a href="{{ route('portal.blog.index') }}" class="hover:underline" style="color: var(--portal-primary-text, #3472D8);">
                    Zurück zum Ratgeber
                </a>
            </p>
        </div>
    </div>

@endsection
