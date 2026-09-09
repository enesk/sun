{{--
    Autorenseite /autor/{slug} (#18).

    Eine Seite je Portal: die redaktionell verantwortliche Einheit aus
    tenant_content_settings, verlinkt aus jeder Autorenbox. Person- und
    Organization-JSON-LD kommen aus PortalProfileService.
--}}
@extends('layouts.app')

@section('title', $authorName . ' — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Autorenprofil: ' . $authorName . '. Verantwortlich für die Ratgeber auf ' . ($currentTenant->name ?? config('app.name')) . '.')
@section('canonical', $authorUrl)

@section('content')

    @push('scripts')
        @include('ratgeber.partials.organization-jsonld', ['organization' => $organization, 'person' => $person])
    @endpush

    @include('components.breadcrumb', ['items' => $breadcrumb])

    {{-- Mini-Hero --}}
    <div class="legal-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm mb-4 overflow-hidden">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                @else
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                @endif
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">{{ $authorName }}</h1>
            <p class="text-white/70 text-sm md:text-base">Redaktionell verantwortlich für die Ratgeber</p>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-16">
        <div class="max-w-3xl mx-auto legal-card p-6 md:p-10">
            <div class="legal-content">
                @if($authorBio)
                    <p>{{ $authorBio }}</p>
                @else
                    <p>
                        {{ $authorName }} verantwortet die Ratgeber auf
                        {{ $currentTenant->name ?? config('app.name') }}. Recherche und Erstentwurf
                        entstehen maschinell, Prüfung und Freigabe erfolgen redaktionell.
                    </p>
                @endif

                <p>
                    Bisher veröffentlicht: {{ $postCount }} Ratgeber.
                    Wie die Beiträge entstehen, welche Quellen genutzt werden und wann sie aktualisiert
                    werden, steht auf der Seite
                    <a href="{{ route('portal.blog.editorial') }}">So arbeitet unsere Redaktion</a>.
                </p>

                @if($authorSameAs)
                    <h2>Profile</h2>
                    <ul>
                        @foreach($authorSameAs as $link)
                            <li><a href="{{ $link }}" rel="me noopener" target="_blank">{{ $link }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if($posts->isNotEmpty())
                <h2 class="mt-10 mb-4 text-lg font-bold">Zuletzt veröffentlicht</h2>
                <ul class="space-y-3">
                    @foreach($posts as $post)
                        <li class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                            <a href="{{ route('portal.blog.show', $post->slug) }}" class="hover:underline" style="color: var(--portal-primary-text, #3472D8);">
                                {{ $post->title }}
                            </a>
                            <time class="text-sm text-[#64748B]" datetime="{{ $post->published_at?->toDateString() }}">
                                {{ $post->formatted_date }}
                            </time>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-8 pt-6 border-t border-[#E2E8F0] text-sm">
                <a href="{{ route('portal.blog.index') }}" class="hover:underline" style="color: var(--portal-primary-text, #3472D8);">
                    Zurück zum Ratgeber
                </a>
            </p>
        </div>
    </div>

@endsection
