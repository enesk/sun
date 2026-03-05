@extends('layouts.app')

@section('title', ($currentTenant->name ?? config('app.name')) . ' — Branchenportal')
@section('meta_description', $currentTenant->getAttribute('branding.site_description') ?? 'Finden Sie lokale Unternehmen in Ihrer Nähe')

@section('content')

    {{-- Schema.org: WebSite + SearchAction (für Google Suchbox) --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $currentTenant->name ?? config('app.name'),
        'url' => route('home'),
        'description' => $currentTenant->getAttribute('branding.site_description') ?? 'Finden Sie lokale Unternehmen in Ihrer Nähe',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => route('portal.companies.index') . '?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Hero --}}
    @include('components.hero', [
        'showSearch' => true,
        'subtitle' => $currentTenant->getAttribute('branding.site_description') ?? 'Finden Sie lokale Unternehmen in Ihrer Nähe',
        'popularCities' => $popularCities ?? collect(),
    ])

    {{-- Trust Bar: Social-Proof Statistiken --}}
    @include('components.trust-bar', [
        'totalCompanies' => $totalCompanies,
        'totalCities' => $totalCities ?? 0,
        'avgRating' => $avgRating ?? 0,
        'totalReviews' => $totalReviews ?? 0,
        'categories' => $categories,
    ])

    {{-- Premium / Featured Unternehmen --}}
    @if($featuredCompanies->isNotEmpty())
        <section class="section-muted py-16" aria-labelledby="featured-heading">
            <div class="container mx-auto px-4">
                <div class="flex items-end justify-between mb-10 reveal">
                    <div>
                        <h2 id="featured-heading" class="text-[28px] font-extrabold text-[#0F172A]">Premium-Einträge</h2>
                        <div class="w-10 h-[3px] rounded-sm mt-3" style="background: var(--portal-accent, #F59E0B);"></div>
                        <p class="text-base text-[#64748B] mt-2 max-w-[500px]">Ausgewählte Unternehmen mit Premium-Profil</p>
                    </div>
                    <a href="{{ route('portal.companies.index', ['premium' => 1]) }}" class="group hidden md:inline-flex items-center gap-1 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle anzeigen
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Mobile: Horizontal Scroll --}}
                <div class="md:hidden -mx-4 px-4">
                    <div class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 scrollbar-hide" role="list">
                        @foreach($featuredCompanies as $index => $company)
                            <div class="snap-start shrink-0 w-[80vw] max-w-[320px] reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms" role="listitem">
                                @include('components.company-card', ['company' => $company, 'layout' => 'grid', 'showDate' => true])
                            </div>
                        @endforeach
                    </div>
                    {{-- Scroll-Indikatoren --}}
                    <div class="flex justify-center gap-1.5 mt-3">
                        @foreach($featuredCompanies as $i => $company)
                            <span class="w-1.5 h-1.5 rounded-full bg-base-content/20 {{ $i === 0 ? 'bg-portal-primary' : '' }}" aria-hidden="true"></span>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop: Grid --}}
                <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($featuredCompanies as $index => $company)
                        <div class="reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms">
                            @include('components.company-card', ['company' => $company, 'layout' => 'grid', 'showDate' => true])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Zufällige Einträge --}}
    <section class="section-light py-16" aria-labelledby="latest-heading">
        <div class="container mx-auto px-4">
            <div class="flex items-end justify-between mb-10 reveal">
                <div>
                    <h2 id="latest-heading" class="text-[28px] font-extrabold text-[#0F172A]">Firmen entdecken</h2>
                    <div class="w-10 h-[3px] rounded-sm mt-3" style="background: var(--portal-primary, #3B82F6);"></div>
                    <p class="text-base text-[#64748B] mt-2 max-w-[500px]">Entdecken Sie Unternehmen in unserem Verzeichnis</p>
                </div>
                @if($latestCompanies->isNotEmpty())
                    <a href="{{ route('portal.companies.index') }}" class="group hidden md:inline-flex items-center gap-1 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle Firmen
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endif
            </div>

            @if($latestCompanies->isNotEmpty())
                {{-- Mobile: Horizontal Scroll --}}
                <div class="md:hidden -mx-4 px-4">
                    <div class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 scrollbar-hide" role="list">
                        @foreach($latestCompanies as $index => $company)
                            <div class="snap-start shrink-0 w-[80vw] max-w-[320px] reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms" role="listitem">
                                @include('components.company-card', ['company' => $company, 'layout' => 'grid', 'showDate' => true])
                            </div>
                        @endforeach
                    </div>
                    {{-- Scroll-Indikatoren --}}
                    <div class="flex justify-center gap-1.5 mt-3">
                        @foreach($latestCompanies as $i => $company)
                            <span class="w-1.5 h-1.5 rounded-full bg-base-content/20 {{ $i === 0 ? 'bg-portal-primary' : '' }}" aria-hidden="true"></span>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop: Grid --}}
                <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($latestCompanies as $index => $company)
                        <div class="reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms">
                            @include('components.company-card', ['company' => $company, 'layout' => 'grid', 'showDate' => true])
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty State: Keine Unternehmen --}}
                @include('components.empty-state', [
                    'icon' => 'users',
                    'title' => 'Noch keine Unternehmen eingetragen',
                    'message' => 'Seien Sie der Erste! Tragen Sie Ihr Unternehmen ein und werden Sie von Kunden in Ihrer Region gefunden.',
                    'action' => [
                        'url' => route('portal.companies.create'),
                        'label' => 'Jetzt eintragen',
                    ],
                ])
            @endif
        </div>
    </section>

    {{-- Aktuelle Stellenanzeigen (JOB-14, nur wenn Jobs vorhanden) --}}
    @if(isset($latestJobs) && $latestJobs->isNotEmpty())
        <section class="section-muted py-16" aria-labelledby="jobs-heading">
            <div class="container mx-auto px-4">
                <div class="flex items-end justify-between mb-10 reveal">
                    <div>
                        <h2 id="jobs-heading" class="text-[28px] font-extrabold text-[#0F172A]">Aktuelle Stellenanzeigen</h2>
                        <div class="w-10 h-[3px] rounded-sm mt-3" style="background: var(--portal-primary, #3B82F6);"></div>
                        <p class="text-base text-[#64748B] mt-2 max-w-[500px]">Finden Sie passende Jobs bei Unternehmen in Ihrer Region</p>
                    </div>
                    <a href="{{ route('portal.jobs.index') }}" class="group hidden md:inline-flex items-center gap-1 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle Stellenanzeigen
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Mobile: Horizontal Scroll --}}
                <div class="md:hidden -mx-4 px-4">
                    <div class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 scrollbar-hide" role="list">
                        @foreach($latestJobs as $index => $job)
                            <div class="snap-start shrink-0 w-[85vw] max-w-[400px] reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms" role="listitem">
                                @include('components.job-card', ['job' => $job, 'layout' => 'list'])
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop: 2-Column Grid --}}
                <div class="hidden md:grid md:grid-cols-2 gap-5">
                    @foreach($latestJobs as $index => $job)
                        <div class="reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms">
                            @include('components.job-card', ['job' => $job, 'layout' => 'list'])
                        </div>
                    @endforeach
                </div>

                {{-- Mobile "Alle anzeigen" Link --}}
                <div class="md:hidden text-center mt-6">
                    <a href="{{ route('portal.jobs.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle Stellenanzeigen anzeigen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Letzte Ratgeber-Artikel (nur wenn Posts vorhanden) --}}
    @if(isset($latestPosts) && $latestPosts->isNotEmpty())
        <section class="section-muted py-16" aria-labelledby="blog-heading">
            <div class="container mx-auto px-4">
                <div class="flex items-end justify-between mb-10 reveal">
                    <div>
                        <h2 id="blog-heading" class="text-[28px] font-extrabold text-[#0F172A]">Ratgeber & Tipps</h2>
                        <div class="w-10 h-[3px] rounded-sm mt-3" style="background: var(--portal-primary, #3B82F6);"></div>
                        <p class="text-base text-[#64748B] mt-2 max-w-[500px]">Aktuelle Artikel rund um Handwerk, Dienstleister und lokale Firmen</p>
                    </div>
                    <a href="{{ route('portal.blog.index') }}" class="group hidden md:inline-flex items-center gap-1 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle Artikel
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Desktop: 4-Spalten-Grid --}}
                <div class="hidden md:grid md:grid-cols-4 gap-6">
                    @foreach($latestPosts as $index => $post)
                        <div class="reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms">
                            @include('pages.blog._post-card', ['post' => $post, 'featured' => false])
                        </div>
                    @endforeach
                </div>

                {{-- Mobile: Horizontal Scroll --}}
                <div class="md:hidden -mx-4 px-4">
                    <div class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 scrollbar-hide" role="list">
                        @foreach($latestPosts as $index => $post)
                            <div class="snap-start shrink-0 w-[80vw] max-w-[320px] reveal" data-stagger-delay="{{ ($index + 1) * 100 }}ms" role="listitem">
                                @include('pages.blog._post-card', ['post' => $post, 'featured' => false])
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Mobile "Alle anzeigen" Link --}}
                <div class="md:hidden text-center mt-6">
                    <a href="{{ route('portal.blog.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                        Alle Artikel lesen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ-Sektion (FAQ-4, nur wenn FAQs vorhanden) --}}
    @if(isset($homeFaqs) && $homeFaqs->isNotEmpty())
        <section class="section-light py-16" aria-labelledby="faq-heading">
            <div class="container mx-auto px-4">
                <div class="max-w-3xl mx-auto">
                    {{-- Section Header --}}
                    <div class="text-center mb-10 reveal">
                        <h2 id="faq-heading" class="text-[28px] font-extrabold text-[#0F172A]">Häufig gestellte Fragen</h2>
                        <div class="w-10 h-[3px] rounded-sm mt-3 mx-auto" style="background: var(--portal-primary, #3B82F6);"></div>
                        <p class="text-base text-[#64748B] mt-2">Antworten auf die wichtigsten Fragen</p>
                    </div>

                    {{-- Accordion --}}
                    <div class="faq-list" role="list">
                        @foreach($homeFaqs as $index => $faq)
                            <div class="faq-item reveal" x-data="{ open: false }" data-stagger-delay="{{ ($index + 1) * 80 }}ms" role="listitem">
                                <button
                                    @click="open = !open"
                                    class="faq-question"
                                    :class="{ 'faq-question--open': open }"
                                    :aria-expanded="open.toString()"
                                    aria-controls="home-faq-{{ $index }}"
                                >
                                    <span class="faq-question__text">{{ $faq->question }}</span>
                                    <svg class="faq-question__chevron" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div
                                    id="home-faq-{{ $index }}"
                                    x-show="open"
                                    x-collapse
                                    x-cloak
                                    role="region"
                                    :aria-hidden="(!open).toString()"
                                    class="faq-answer"
                                >
                                    <div class="faq-answer__content">
                                        {!! nl2br(e($faq->answer)) !!}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Link zu /faq --}}
                    <div class="text-center mt-8 reveal">
                        <a href="{{ route('portal.faqs.index') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold transition-colors" style="color: var(--portal-primary-dark, #1E3A5F);">
                            Alle Fragen ansehen
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- CTA-Banner: Firmeninhaber-Conversion --}}
    <section class="bg-portal-gradient py-16 md:py-20" aria-labelledby="cta-heading">
        <div class="container mx-auto px-4">
            <div class="max-w-3xl mx-auto text-center">
                {{-- Headline --}}
                <h2 id="cta-heading" class="text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-4">
                    Ihr Unternehmen hier eintragen
                </h2>
                <p class="text-lg text-white/80 mb-8 max-w-xl mx-auto">
                    Werden Sie sichtbar für potenzielle Kunden in Ihrer Region. Kostenloser Eintrag in wenigen Minuten.
                </p>

                {{-- Trust-Argumente --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-8 mb-10 text-sm text-white/70">
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Kostenloser Basiseintrag
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        In 3 Minuten eingerichtet
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Bewertungen sammeln
                    </span>
                </div>

                {{-- CTA Buttons --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('portal.companies.create') }}"
                       class="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-portal-primary-dark font-semibold rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 touch-target">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Jetzt eintragen
                    </a>
                    <a href="{{ route('portal.companies.index', ['premium' => 1]) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 text-white/90 font-medium border border-white/30 rounded-xl hover:bg-white/10 transition-all duration-200 touch-target">
                        Premium-Vorteile entdecken
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Social Proof --}}
                @if($totalCompanies > 0)
                    <p class="mt-8 text-sm text-white/50">
                        Bereits {{ number_format($totalCompanies, 0, ',', '.') }} Unternehmen vertrauen uns
                    </p>
                @endif
            </div>
        </div>
    </section>

@endsection
