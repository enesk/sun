@extends('layouts.app')

@section('title', $company->name . ' — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', Str::limit($company->description, 160))
@section('canonical', $company->portal_url)
@section('og_type', 'business.business')
@if($company->cover_url)
@section('og_image', $company->cover_url)
@elseif($company->logo_url)
@section('og_image', $company->logo_url)
@endif

@section('content')

    {{-- VR-6 + IMG-4: Company Hero Header with optional Cover Image --}}
    @if($company->cover_url)
        {{-- Hero WITH Cover Image --}}
        <section class="company-hero company-hero--cover reveal">
            <div class="company-hero__banner">
                <img src="{{ $company->cover_url }}"
                     alt="{{ $company->name }} — Titelbild"
                     class="company-hero__banner-img"
                     loading="eager"
                     width="1200"
                     height="400">
                <div class="company-hero__banner-overlay" aria-hidden="true"></div>
            </div>
            <div class="container mx-auto px-4">
                {{-- Breadcrumb --}}
                @include('components.breadcrumb', ['items' => $breadcrumb])

                <div class="company-hero__inner company-hero__inner--cover">
                    {{-- Logo --}}
                    @if($company->logo_url)
                        <div class="company-hero__logo-wrapper company-hero__logo-wrapper--cover">
                            <img src="{{ $company->logo_url }}"
                                 alt="{{ $company->name }}"
                                 class="company-hero__logo"
                                 loading="eager">
                        </div>
                    @else
                        <div class="company-hero__logo-wrapper company-hero__logo-wrapper--cover company-hero__logo-placeholder">
                            <span>{{ mb_substr($company->name, 0, 1) }}</span>
                        </div>
                    @endif

                    {{-- Info --}}
                    <div class="company-hero__info">
                        <h1 class="company-hero__name company-hero__name--cover">{{ $company->name }}</h1>

                        @if($company->rating_count > 0)
                            <div class="company-hero__rating">
                                @include('components.star-rating', ['rating' => $company->rating, 'size' => 'md', 'showNumeric' => true])
                                <span class="company-hero__rating-count company-hero__rating-count--cover">({{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }})</span>
                            </div>
                        @endif

                        <div class="company-hero__meta">
                            @if($company->city)
                                <span class="company-hero__meta-item company-hero__meta-item--cover">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $company->city->name }}
                                </span>
                            @endif
                            @if($company->categories->isNotEmpty())
                                <span class="company-hero__meta-item company-hero__meta-item--cover">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    {{ $company->categories->first()->name }}
                                </span>
                            @endif
                        </div>

                        <div class="company-hero__badges">
                            @if($company->is_premium)
                                <span class="company-hero__badge company-hero__badge--premium">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Premium
                                </span>
                            @endif
                            @if($company->is_verified)
                                <span class="company-hero__badge company-hero__badge--verified">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Verifiziert
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Desktop CTA --}}
                    <div class="company-hero__cta">
                        @if($company->tel)
                            <a href="tel:{{ $company->tel }}" class="company-hero__cta-btn company-hero__cta-btn--primary ripple">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                Jetzt anrufen
                            </a>
                        @endif
                        @if($company->email)
                            <a href="mailto:{{ $company->email }}" class="company-hero__cta-btn company-hero__cta-btn--secondary ripple">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                E-Mail
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @else
        {{-- Hero WITHOUT Cover Image (Original Gradient) --}}
        <section class="company-hero reveal">
            <div class="container mx-auto px-4">
                <div class="flex justify-center">
                    <x-ad-slot position="after_breadcrumb" />
                </div>
                <div class="company-hero__inner">
                    @if($company->logo_url)
                        <div class="company-hero__logo-wrapper">
                            <img src="{{ $company->logo_url }}"
                                 alt="{{ $company->name }}"
                                 class="company-hero__logo"
                                 loading="eager">
                        </div>
                    @else
                        <div class="company-hero__logo-wrapper company-hero__logo-placeholder">
                            <span>{{ mb_substr($company->name, 0, 1) }}</span>
                        </div>
                    @endif

                    <div class="company-hero__info">
                        <h1 class="company-hero__name">{{ $company->name }}</h1>

                        @if($company->rating_count > 0)
                            <div class="company-hero__rating">
                                @include('components.star-rating', ['rating' => $company->rating, 'size' => 'md', 'showNumeric' => true])
                                <span class="company-hero__rating-count">({{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }})</span>
                            </div>
                        @endif

                        <div class="company-hero__meta">
                            @if($company->city)
                                <span class="company-hero__meta-item">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $company->city->name }}
                                </span>
                            @endif
                            @if($company->categories->isNotEmpty())
                                <span class="company-hero__meta-item">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    {{ $company->categories->first()->name }}
                                </span>
                            @endif
                        </div>

                        <div class="company-hero__badges">
                            @if($company->is_premium)
                                <span class="company-hero__badge company-hero__badge--premium">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Premium
                                </span>
                            @endif
                            @if($company->is_verified)
                                <span class="company-hero__badge company-hero__badge--verified">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Verifiziert
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="company-hero__cta">
                        @if($company->tel)
                            <a href="tel:{{ $company->tel }}" class="company-hero__cta-btn company-hero__cta-btn--primary ripple">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                Jetzt anrufen
                            </a>
                        @endif
                        @if($company->email)
                            <a href="mailto:{{ $company->email }}" class="company-hero__cta-btn company-hero__cta-btn--secondary ripple">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                E-Mail
                            </a>
                        @endif
                    </div>
                </div>
                <x-ad-slot position="after_company_card" />
            </div>
        </section>
    @endif

    <div class="container mx-auto px-4 pb-12">

        <div class="flex flex-col lg:flex-row gap-8 mt-8">

            {{-- Hauptbereich --}}
            <div class="flex-1 min-w-0 space-y-8">

                {{-- Ad: Before Intro --}}
                <x-ad-slot position="content_before_intro" />

                {{-- Beschreibung --}}
                @if($company->description)
                    <section class="reveal">
                        <h2 class="text-[18px] font-bold text-[#0F172A] mb-3">Über {{ $company->name }}</h2>
                        <div class="prose prose-sm max-w-none text-base-content/80">
                            {!! nl2br(e($company->description)) !!}
                        </div>
                    </section>

                    {{-- Ad: After Intro --}}
                    <x-ad-slot position="content_after_intro" />
                @endif

                {{-- PROF-1: Inline-CTA "Stimmt etwas nicht?" — nur bei ungeclaimten Firmen --}}
                @if(!$company->user_id)
                <div class="suggest-edit-inline-cta reveal">
                    <svg class="w-4 h-4 text-base-content/40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="text-sm text-base-content/50">Stimmt etwas nicht?</span>
                    <a href="{{ route('companies.suggest-edit', $company->slug) }}"
                       class="text-sm font-medium hover:underline transition-colors"
                       style="color: var(--portal-primary)"
                       aria-label="Änderung für {{ $company->name }} vorschlagen">
                        Änderung vorschlagen
                    </a>
                </div>
                @endif
                <x-ad-slot position="after_change_request" />
                {{-- Bildergalerie --}}
                @php
                    $galleryMedia = $company->relationLoaded('media')
                        ? $company->media->where('collection_name', 'gallery')->values()
                        : $company->getMedia('gallery');
                    $galleryLimit = 10;
                    $galleryVisible = $galleryMedia->take($galleryLimit);
                    $galleryRemaining = $galleryMedia->count() - $galleryLimit;
                @endphp
                @if($galleryMedia->isNotEmpty())
                    <section class="reveal" x-data="companyGallery({{ $galleryMedia->count() }})" x-cloak>
                        <div class="flex items-center gap-3 mb-4">
                            <h2 class="text-[18px] font-bold text-[#0F172A]">Bilder</h2>
                            <span class="text-sm text-[#94A3B8]">({{ $galleryMedia->count() }})</span>
                        </div>

                        {{-- Galerie: Einzeiliger Horizontal-Scroll --}}
                        <div class="company-gallery__grid">
                            @foreach($galleryVisible as $index => $media)
                                <button class="company-gallery__item"
                                        @click="open({{ $index }})"
                                        type="button"
                                        aria-label="Bild {{ $index + 1 }} von {{ $galleryMedia->count() }} vergrößern">
                                    <img src="{{ $media->getUrl('medium') }}"
                                         alt="{{ $media->name ?: $company->name . ' — Bild ' . ($index + 1) }}"
                                         class="company-gallery__img"
                                         loading="lazy"
                                         width="600"
                                         height="400">
                                    <div class="company-gallery__overlay">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                        </svg>
                                    </div>
                                </button>
                            @endforeach
                            @if($galleryRemaining > 0)
                                <button class="company-gallery__item company-gallery__more"
                                        @click="open({{ $galleryLimit }})"
                                        type="button"
                                        aria-label="Alle {{ $galleryMedia->count() }} Bilder anzeigen">
                                    <span class="company-gallery__more-text">+{{ $galleryRemaining }}</span>
                                </button>
                            @endif
                        </div>

                        {{-- Lightbox — teleported to body to escape .reveal stacking context --}}
                        <template x-teleport="body">
                            <div x-show="isOpen"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="company-gallery__lightbox"
                                 role="dialog"
                                 aria-modal="true"
                                 aria-label="Bildergalerie"
                                 @keydown.escape.window="close()"
                                 @keydown.left.window="prev()"
                                 @keydown.right.window="next()">
                                {{-- Backdrop --}}
                                <div class="company-gallery__lightbox-backdrop" @click="close()"></div>

                                {{-- Close Button --}}
                                <button @click="close()" class="company-gallery__lightbox-close" aria-label="Schließen">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>

                                {{-- Counter --}}
                                <div class="company-gallery__lightbox-counter" x-text="`${current + 1} / {{ $galleryMedia->count() }}`"></div>

                                {{-- Navigation --}}
                                @if($galleryMedia->count() > 1)
                                    <button @click="prev()" class="company-gallery__lightbox-nav company-gallery__lightbox-nav--prev" aria-label="Vorheriges Bild">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button @click="next()" class="company-gallery__lightbox-nav company-gallery__lightbox-nav--next" aria-label="Nächstes Bild">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                @endif

                                {{-- Image --}}
                                <div class="company-gallery__lightbox-content">
                                    @foreach($galleryMedia as $index => $media)
                                        <img x-show="current === {{ $index }}"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             src="{{ $media->getUrl() }}"
                                             alt="{{ $media->name ?: $company->name . ' — Bild ' . ($index + 1) }}"
                                             class="company-gallery__lightbox-img">
                                    @endforeach
                                </div>
                            </div>
                        </template>
                    </section>
                @endif
                <x-ad-slot position="after_photos" />
                {{-- Kategorien --}}
                @if($company->categories->isNotEmpty())
                    <section class="reveal" data-stagger-delay="100ms">
                        <h2 class="text-[18px] font-bold text-[#0F172A] mb-3">Kategorien</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach($company->categories as $cat)
                                <a href="{{ route('portal.categories.show', $cat->slug) }}"
                                   class="company-card__tag inline-flex items-center gap-1.5 px-3 py-1.5">
                                    @if($cat->icon)
                                        <i data-lucide="{{ $cat->icon }}" class="w-4 h-4" aria-hidden="true"></i>
                                    @endif
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                    <x-ad-slot position="after_categories" />
                @endif

                {{-- Offene Stellen (JOB-12) --}}
                @if($companyJobs->isNotEmpty())
                    <section class="reveal" data-stagger-delay="150ms">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-[18px] font-bold text-[#0F172A]">
                                Offene Stellen
                                <span class="text-sm font-normal text-[#64748B] ml-1">({{ $companyJobs->count() }})</span>
                            </h2>
                            <a href="{{ route('portal.jobs.index', ['q' => $company->name]) }}" class="group inline-flex items-center gap-1 text-sm font-semibold text-portal-primary-dark hover:text-portal-primary transition-colors">
                                Alle Stellen
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                        <div class="space-y-3">
                            @foreach($companyJobs as $job)
                                @include('components.job-card', ['job' => $job, 'layout' => 'compact'])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Bewertungen --}}
                <section class="reveal" data-stagger-delay="200ms">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <h2 class="text-[18px] font-bold text-[#0F172A]">
                                Bewertungen
                                @if($company->rating_count > 0)
                                    <span class="text-sm font-normal text-[#94A3B8]">({{ $company->rating_count }})</span>
                                @endif
                            </h2>
                            <div class="h-[3px] w-8 rounded-full bg-portal-primary opacity-60"></div>
                        </div>
                    </div>

                    {{-- Bewertungs-Zusammenfassung (wenn Bewertungen vorhanden) --}}
                    @if($company->rating_count > 0)
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-6 p-3 sm:p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0]">
                            <div class="flex items-center sm:block sm:text-center gap-3 sm:gap-0">
                                <div class="text-2xl sm:text-3xl font-bold text-[#0F172A]">{{ number_format($company->rating, 1) }}</div>
                                <div class="sm:mt-1">
                                    @include('components.star-rating', ['rating' => $company->rating, 'size' => 'sm'])
                                </div>
                                <div class="text-xs text-[#94A3B8] sm:mt-1">{{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }}</div>
                            </div>
                            <div class="hidden sm:block h-12 w-px bg-[#E2E8F0]"></div>
                            <div class="border-t sm:border-t-0 border-[#E2E8F0] pt-3 sm:pt-0 flex-1">
                                <livewire:portal.submit-review-form :company="$company" />
                            </div>
                        </div>
                    @else
                        {{-- CTA wenn noch keine Bewertungen --}}
                        <div class="mb-6">
                            <livewire:portal.submit-review-form :company="$company" />
                        </div>
                    @endif
                    <x-ad-slot position="after_review_summary" />
                    @if($company->approvedReviews->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($company->approvedReviews as $review)
                                <article class="company-review-card">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center gap-3">
                                            {{-- Avatar-Initialen --}}
                                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-semibold shrink-0"
                                                 style="background: rgba(var(--portal-primary-rgb), 0.1); color: var(--portal-primary);">
                                                {{ mb_strtoupper(mb_substr($review->author_name ?? 'A', 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-[#0F172A] text-sm">{{ $review->author_name ?? 'Anonym' }}</p>
                                                <p class="text-xs text-[#94A3B8]">{{ $review->created_at->translatedFormat('d. F Y') }}</p>
                                            </div>
                                        </div>
                                        @include('components.star-rating', ['rating' => $review->rating, 'size' => 'sm'])
                                    </div>
                                    @if($review->title)
                                        <h4 class="font-medium text-[#0F172A] text-sm mb-1 ml-12">{{ $review->title }}</h4>
                                    @endif
                                    @if($review->body)
                                        <p class="text-sm text-[#64748B] leading-relaxed ml-12">{{ $review->body }}</p>
                                    @endif

                                    {{-- Owner Response --}}
                                    @if(!empty($review->owner_response))
                                        <div class="ml-12 mt-3 pl-3 py-2" style="border-left: 2px solid var(--portal-primary);">
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <svg class="w-3.5 h-3.5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                </svg>
                                                <span class="text-xs font-semibold" style="color: var(--portal-primary)">Antwort vom Inhaber</span>
                                            </div>
                                            <p class="text-sm text-[#64748B] leading-relaxed">{{ $review->owner_response }}</p>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 px-4">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F1F5F9] flex items-center justify-center">
                                <svg class="w-6 h-6 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <p class="text-sm text-[#94A3B8] mb-2">Noch keine Bewertungen vorhanden.</p>
                            <p class="text-xs text-[#94A3B8]">Seien Sie der Erste, der dieses Unternehmen bewertet!</p>
                        </div>
                    @endif
                </section>
            </div>

            {{-- Sidebar: Kontakt-Box (Sticky on Desktop) --}}
            <aside class="lg:w-80 shrink-0 space-y-6">
              <div class="lg:sticky lg:top-28">

                  <div class="mb-5">
                      <x-ad-slot position="sidebar_top" />
                  </div>

                {{-- PROF-1: Claim-CTA für ungeclaimte Firmen --}}
                @if(!$company->user_id)
                    <div class="company-sidebar claim-cta-sidebar reveal border-2 !border-portal-primary/20 mb-5" style="background: linear-gradient(135deg, rgba(var(--portal-primary-rgb), 0.04), rgba(var(--portal-primary-rgb), 0.08));">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(var(--portal-primary-rgb), 0.12);">
                                <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-base-content text-base leading-tight">Ist das Ihr Unternehmen?</h3>
                                <p class="text-sm text-base-content/60 mt-1">Übernehmen Sie Ihren Eintrag — kostenlos. Aktualisieren Sie Ihre Daten und antworten Sie auf Bewertungen.</p>
                                <a href="{{ route('register') }}"
                                   class="btn-portal w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium ripple mt-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Jetzt kostenlos übernehmen
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
                  <x-ad-slot position="sidebar_after_claim" />
                {{-- Kontaktdaten --}}
                <div class="company-sidebar reveal mb-5" data-stagger-delay="100ms">
                    <h3 class="font-semibold text-base-content text-lg mb-1">Kontakt</h3>

                    {{-- Adresse --}}
                    @if($company->full_address)
                        <div class="flex items-start gap-3 text-sm">
                            <svg class="w-5 h-5 shrink-0 text-base-content/40 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-base-content/70">{{ $company->full_address }}</span>
                        </div>
                    @endif

                    {{-- Telefon --}}
                    @if($company->tel)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <a href="tel:{{ $company->tel }}" class="text-base-content/70 hover:text-base-content transition-colors hover:underline">
                                {{ $company->tel }}
                            </a>
                        </div>
                    @endif

                    {{-- E-Mail --}}
                    @if($company->email)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <a href="mailto:{{ $company->email }}" class="text-base-content/70 hover:text-base-content transition-colors hover:underline break-all">
                                {{ $company->email }}
                            </a>
                        </div>
                    @endif

                    {{-- Website --}}
                    @if($company->website)
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                            <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer"
                               class="text-base-content/70 hover:text-base-content transition-colors hover:underline break-all">
                                {{ parse_url($company->website, PHP_URL_HOST) ?? $company->website }}
                                <svg class="inline w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </div>
                    @endif

                    {{-- CTA Buttons --}}
                    @if($company->tel || $company->email)
                        <div class="pt-3 border-t border-base-200 space-y-2">
                            @if($company->tel)
                                <a href="tel:{{ $company->tel }}" class="btn-portal w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium ripple">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    Jetzt anrufen
                                </a>
                            @endif
                            @if($company->email)
                                <a href="mailto:{{ $company->email }}" class="btn-portal-outline w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium ripple">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    E-Mail schreiben
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
                  <x-ad-slot position="sidebar_after_contact" />
                {{-- Social Links (Premium-only) --}}
                @if($company->is_premium)
                    @php
                        $socialLinks = collect([
                            ['field' => 'social_facebook', 'label' => 'Facebook', 'icon' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z', 'color' => '#1877F2'],
                            ['field' => 'social_instagram', 'label' => 'Instagram', 'icon' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z', 'color' => '#E4405F'],
                            ['field' => 'social_linkedin', 'label' => 'LinkedIn', 'icon' => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z', 'color' => '#0A66C2'],
                            ['field' => 'social_youtube', 'label' => 'YouTube', 'icon' => 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z', 'color' => '#FF0000'],
                        ])->filter(fn ($link) => !empty($company->{$link['field']} ?? null));
                    @endphp
                    @if($socialLinks->isNotEmpty())
                        <div class="company-sidebar reveal mb-5" data-stagger-delay="125ms">
                            <h3 class="font-semibold text-base-content text-lg mb-1">Social Media</h3>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                @foreach($socialLinks as $link)
                                    <a href="{{ $company->{$link['field']} }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="w-9 h-9 rounded-lg flex items-center justify-center transition-all hover:scale-110 hover:shadow-md"
                                       style="background-color: {{ $link['color'] }}10;"
                                       aria-label="{{ $company->name }} auf {{ $link['label'] }}">
                                        <svg class="w-4.5 h-4.5" style="color: {{ $link['color'] }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="{{ $link['icon'] }}"/>
                                        </svg>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Öffnungszeiten --}}
                @if($company->openingHours->isNotEmpty())
                    @php
                        $todayIndex = now()->dayOfWeekIso - 1; // 0=Mo...6=So
                        $todayHours = $company->openingHours->firstWhere('day_of_week', $todayIndex);
                        $isOpen = false;
                        if ($todayHours && !$todayHours->is_closed && $todayHours->opens_at && $todayHours->closes_at) {
                            $nowTime = now()->format('H:i:s');
                            $isOpen = $nowTime >= $todayHours->opens_at && $nowTime <= $todayHours->closes_at;
                        }
                    @endphp
                    <div class="company-sidebar reveal mb-5" data-stagger-delay="150ms"
                         x-data="{ expanded: window.innerWidth >= 1024 }"
                         role="region"
                         aria-label="Öffnungszeiten">
                        {{-- Header mit Status-Indikator --}}
                        <button class="flex items-center justify-between w-full lg:pointer-events-none"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded.toString()">
                            <h3 class="font-semibold text-base-content text-lg">Öffnungszeiten</h3>
                            <div class="flex items-center gap-2">
                                @if($todayHours)
                                    @if($isOpen)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-600">
                                            <span class="relative flex h-2 w-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                            </span>
                                            Jetzt geöffnet
                                        </span>
                                    @elseif($todayHours->is_closed || ($todayHours->opens_at && $todayHours->closes_at))
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500">
                                            <span class="relative flex h-2 w-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                            </span>
                                            Geschlossen
                                        </span>
                                    @endif
                                @endif
                                {{-- Mobile Chevron --}}
                                <svg class="w-4 h-4 text-base-content/40 transition-transform lg:hidden"
                                     :class="{ 'rotate-180': expanded }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>

                        {{-- Tages-Liste --}}
                        <div x-show="expanded"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="mt-3 space-y-0.5">
                            @foreach($company->openingHours->sortBy('day_of_week') as $hour)
                                <div class="flex items-center justify-between py-1.5 px-2 rounded-md text-sm
                                    {{ $hour->day_of_week === $todayIndex ? 'bg-portal-primary/5 font-semibold' : '' }}">
                                    <span class="text-base-content {{ $hour->day_of_week === $todayIndex ? '' : 'text-base-content/70' }}">
                                        {{ $hour->day_name }}
                                    </span>
                                    <span class="{{ $hour->is_closed ? 'text-red-500' : ($hour->day_of_week === $todayIndex ? 'text-base-content' : 'text-base-content/70') }}">
                                        {{ $hour->formatted_time }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                  <x-ad-slot position="sidebar_opening_hours" />
                {{-- Google Maps Embed (falls Adresse vorhanden) --}}
                @if($company->full_address)
                    <div class="rounded-xl overflow-hidden border border-base-200 aspect-[4/3] reveal" data-stagger-delay="200ms">
                        <iframe
                            title="Standort von {{ $company->name }}"
                            class="w-full h-full"
                            src="https://maps.google.com/maps?q={{ urlencode($company->full_address) }}&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen>
                        </iframe>
                    </div>
                @endif
                {{-- Claim-CTA wurde nach oben verschoben (vor Kontaktdaten, PROF-1) --}}
              </div>{{-- /lg:sticky --}}
            </aside>
        </div>
        <x-ad-slot position="after_review_summary" />
        {{-- Ähnliche Firmen --}}
        @if($relatedCompanies->isNotEmpty())
            <section class="mt-12 pt-8 border-t border-base-200 reveal">
                <div class="flex items-center gap-4 mb-6">
                    <h2 class="text-[22px] font-extrabold text-[#0F172A]">Ähnliche Unternehmen</h2>
                    <div class="h-[3px] w-10 rounded-full bg-portal-primary opacity-60"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($relatedCompanies as $related)
                        @include('components.company-card', ['company' => $related, 'layout' => 'grid'])
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- VR-6: Sticky Contact-Bar (Mobile only) --}}
    @if($company->tel || $company->website)
        <div class="sticky-contact" role="complementary" aria-label="Kontaktmöglichkeiten">
            @if($company->tel)
                <a href="tel:{{ $company->tel }}" class="sticky-contact__call ripple">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Jetzt anrufen
                </a>
            @endif
            @if($company->website)
                <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer" class="sticky-contact__website ripple">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    Website
                </a>
            @endif
        </div>
    @endif

    {{-- STAT-1: Contact Click Tracking --}}
    @push('scripts')
    <script>
    (function() {
        var companyId = {{ $company->id }};
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var tracked = {};

        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (!link) return;

            var href = link.getAttribute('href') || '';
            var type = null;

            if (href.startsWith('tel:')) type = 'phone';
            else if (href.startsWith('mailto:')) type = 'email';
            else if (href.match(/^https?:\/\//) && link.target === '_blank') type = 'website';

            if (!type) return;

            var key = type + '_' + companyId;
            if (tracked[key]) return;
            tracked[key] = true;

            var payload = { company_id: companyId, contact_type: type, _token: csrfToken };

            if (navigator.sendBeacon) {
                navigator.sendBeacon('/tracking/contact-click', new Blob([JSON.stringify(payload)], { type: 'application/json' }));
            } else {
                fetch('/tracking/contact-click', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(payload),
                    keepalive: true
                });
            }
        });
    })();
    </script>
    @endpush

    {{-- Schema.org Structured Data --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $company->name,
        'description' => $company->description ? Str::limit($company->description, 250) : null,
        'address' => $company->full_address ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $company->street ? trim($company->street . ' ' . $company->house_no) : null,
            'postalCode' => $company->zipcode,
            'addressLocality' => $company->city?->name,
            'addressCountry' => 'DE',
        ]) : null,
        'telephone' => $company->tel,
        'email' => $company->email,
        'url' => $company->website,
        'aggregateRating' => $company->rating_count > 0 ? [
            '@type' => 'AggregateRating',
            'ratingValue' => $company->rating,
            'reviewCount' => $company->rating_count,
            'bestRating' => 5,
            'worstRating' => 1,
        ] : null,
        'image' => (function() use ($company) {
            $images = [];
            if ($company->cover_url) {
                $images[] = $company->cover_url;
            }
            if ($company->logo_url) {
                $images[] = $company->logo_url;
            }
            $schemaGallery = $company->relationLoaded('media')
                ? $company->media->where('collection_name', 'gallery')
                : $company->getMedia('gallery');
            foreach ($schemaGallery as $media) {
                $images[] = $media->getUrl();
            }
            return count($images) === 1 ? $images[0] : ($images ?: null);
        })(),
        'sameAs' => $company->website ?: null,
        'openingHoursSpecification' => $company->openingHours->isNotEmpty()
            ? $company->openingHours->map(fn ($h) => array_filter([
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$h->day_of_week] ?? null,
                'opens' => !$h->is_closed && $h->opens_at ? substr($h->opens_at, 0, 5) : null,
                'closes' => !$h->is_closed && $h->closes_at ? substr($h->closes_at, 0, 5) : null,
            ]))->values()->all()
            : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endpush

@endsection
