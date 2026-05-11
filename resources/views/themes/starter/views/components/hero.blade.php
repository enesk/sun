{{-- Hero Section (VR-3: Mesh-Gradient, Keyword-Animation, Wave-Divider) --}}
{{-- Usage: @include('components.hero', ['showSearch' => true, 'popularCategories' => $popularCategories]) --}}
@if($themeOptions['show_hero'] ?? true)
@php
    $cities = ($popularCities ?? collect())->pluck('name')->toArray();
@endphp

<section class="hero-mesh relative overflow-hidden"
         x-data="{
             words: @js($cities),
             current: 0,
             animating: false,
             direction: 'up',
             init() {
                 if (this.words.length <= 1) return;
                 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                 setInterval(() => {
                     if (this.animating) return;
                     this.animating = true;
                     this.direction = 'up';
                     setTimeout(() => {
                         this.current = (this.current + 1) % this.words.length;
                         this.direction = 'down';
                         setTimeout(() => {
                             this.animating = false;
                         }, 300);
                     }, 300);
                 }, 3000);
             }
         }">

    {{-- Mesh Gradient Background (3 animated blobs using tenant colors) --}}
    <div class="absolute inset-0" aria-hidden="true">
        <div class="hero-blob hero-blob-1"></div>
        <div class="hero-blob hero-blob-2"></div>
        <div class="hero-blob hero-blob-3"></div>
    </div>

    {{-- Subtle noise overlay for texture --}}
    <div class="absolute inset-0 opacity-[0.03] mix-blend-overlay" aria-hidden="true"
         style="background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;); background-repeat: repeat; background-size: 256px 256px;"></div>

    <div class="container mx-auto px-4 py-12 md:py-16 lg:py-20 text-center relative z-10">
        {{-- Dynamic Title with Slide Animation --}}
        <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-4 leading-tight">
            @if(count($cities) > 0)
                Finden Sie Unternehmen in
                <span class="hero-keyword-wrapper inline-block relative overflow-hidden align-bottom"
                      aria-live="polite">
                    <span class="hero-keyword inline-block transition-all duration-300"
                          :class="{
                              'hero-keyword-exit': animating && direction === 'up',
                              'hero-keyword-enter': animating && direction === 'down'
                          }"
                          style="transition-timing-function: var(--ease-spring, cubic-bezier(0.22, 1, 0.36, 1))"
                          x-text="words[current]">{{ $cities[0] ?? '' }}</span>
                    <span class="hero-keyword-highlight"></span>
                </span>
            @else
                {{ $title ?? ($currentTenant->name ?? config('app.name')) }}
            @endif
        </h1>

        @if(!empty($subtitle))
            <p class="text-base md:text-lg text-white/80 max-w-2xl mx-auto mb-8">
                {{ $subtitle }}
            </p>
        @elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.site_description'))
            <p class="text-base md:text-lg text-white/80 max-w-2xl mx-auto mb-8">
                {{ $currentTenant->getAttribute('branding.site_description') }}
            </p>
        @endif

        {{-- Inline Search (glass style) --}}
        @if($showSearch ?? true)
            <form action="{{ route('portal.companies.index') }}" method="GET" role="search" class="max-w-xl mx-auto mb-6">
                <label for="hero-search" class="sr-only">Firma suchen</label>
                <div class="hero-search-bar flex rounded-2xl overflow-hidden">
                    <input type="search" name="q" id="hero-search"
                           placeholder="Firma, Branche oder Stichwort..."
                           value="{{ request('q') }}"
                           class="flex-1 px-5 py-3.5 md:py-4 text-base text-base-content bg-white/95 backdrop-blur-sm border-0 focus:outline-none focus:ring-0 placeholder:text-base-content/40"
                           autocomplete="off">
                    <button type="submit"
                            class="px-5 md:px-6 py-3.5 md:py-4 text-white font-medium transition-all hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-white/50 bg-portal-accent ripple"
                            aria-label="Suchen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                </div>
            </form>
        @endif

        {{-- Beliebte Städte Tags --}}
        @if(($popularCities ?? collect())->isNotEmpty())
            <nav aria-label="Beliebte Städte" class="max-w-xl mx-auto">
                <p class="text-sm text-white/60 mb-2">Beliebte St&auml;dte:</p>
                <div class="flex flex-nowrap md:flex-wrap md:justify-center gap-2 overflow-x-auto pb-2 md:pb-0 -mx-4 px-4 md:mx-0 md:px-0 scrollbar-hide">
                    @foreach($popularCities as $city)
                        <a href="{{ route('portal.cities.show', $city->slug) }}"
                           class="hero-tag inline-flex items-center shrink-0 px-3.5 py-1.5 rounded-full text-sm font-medium text-white border border-white/20 transition-all backdrop-blur-sm">
                            <svg class="w-4 h-4 mr-1 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $city->name }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>

    {{-- Wave Divider (bottom, transitions to white trust-bar background) --}}
    <div class="hero-wave absolute bottom-0 left-0 w-full overflow-hidden leading-none" aria-hidden="true">
        <svg class="relative block w-full" style="height: 48px;" viewBox="0 0 1440 48" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,24 C240,48 480,0 720,24 C960,48 1200,0 1440,24 L1440,48 L0,48 Z" fill="#f8fafc"/>
        </svg>
    </div>
</section>
@endif
