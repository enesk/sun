@php
    // Robots/Canonical: SeoService (#7) hat Vorrang, sonst die @section der Seite.
    $seo = app(\App\Services\Seo\SeoService::class);
    $metaRobots = $seo->robots() !== null ? e($seo->robots()) : ($__env->hasSection('meta_robots') ? $__env->yieldContent('meta_robots') : null);
    $canonicalUrl = $seo->canonical() !== null ? e($seo->canonical()) : $__env->yieldContent('canonical', url()->current());
    // Title, Description, OG: ebenfalls SeoService zuerst (Ratgeber #17). yieldContent() escaped selbst.
    $metaTitle = $seo->title() !== null ? e($seo->title()) : $__env->yieldContent('title', $currentTenant->name ?? config('app.name'));
    $metaDescription = $seo->description() !== null ? e($seo->description()) : $__env->yieldContent('meta_description', '');
    $metaOgType = $seo->ogType() !== null ? e($seo->ogType()) : $__env->yieldContent('og_type', 'website');
    $metaOgImage = $seo->ogImage() !== null ? e($seo->ogImage()) : ($__env->hasSection('og_image') ? $__env->yieldContent('og_image') : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- SEO Meta --}}
    <title>{!! $metaTitle !!}</title>
    <meta name="description" content="{!! $metaDescription !!}">
    @if($metaRobots !== null)
        <meta name="robots" content="{!! $metaRobots !!}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="{!! $metaTitle !!}">
    <meta property="og:description" content="{!! $metaDescription !!}">
    <meta property="og:type" content="{!! $metaOgType !!}">
    <meta property="og:url" content="{!! $canonicalUrl !!}">
    <meta property="og:site_name" content="{{ $currentTenant->name ?? config('app.name') }}">
    <meta property="og:locale" content="de_DE">
    @if($metaOgImage !== null)
        <meta property="og:image" content="{!! $metaOgImage !!}">
    @elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
        <meta property="og:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
    @endif

    {{-- Twitter Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{!! $metaTitle !!}">
    <meta name="twitter:description" content="{!! $metaDescription !!}">
    @if($metaOgImage !== null)
        <meta name="twitter:image" content="{!! $metaOgImage !!}">
    @elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
        <meta name="twitter:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
    @endif

    {{-- Favicon --}}
    @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
        <link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
    @endif

    {{-- Canonical --}}
    <link rel="canonical" href="{!! $canonicalUrl !!}">

    {{-- Schema.org JSON-LD (#8) --}}
    <x-seo.json-ld />

    {{-- 1. Base CSS (Tailwind + DaisyUI) --}}
    @vite(['resources/css/app.css'])

    {{-- 2. Tenant CSS Variables (injected by ResolveTheme middleware) --}}
    {!! $tenantStyles ?? '' !!}

    @livewireStyles
    @stack('styles')

    {{-- Lucide Icons (Category icons etc.) --}}
    <script src="https://unpkg.com/lucide@0.574.0/dist/umd/lucide.min.js" defer></script>

    @include('partials.analytics')

    {{-- Auto Ads: auf Ratgeber-Routen nicht ausliefern (Vorgabe #100) --}}
    @if(\App\View\Components\AdSlot::autoAdsAllowedHere())
        <x-ad-slot position="auto_ads" />
    @endif
</head>
<body class="min-h-screen flex flex-col bg-base-100 text-base-content antialiased @if(\App\View\Components\AdSlot::hasSlotsForPosition('mobile_sticky_bottom')) pb-[60px] lg:pb-0 @endif">

    {{-- Skip to Content (Accessibility) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded focus:shadow-lg focus:text-sm focus:font-medium text-portal-primary-dark">
        Zum Inhalt springen
    </a>

    @include('partials.header')

    {{-- Ad: Header Below --}}
    <x-ad-slot position="header_below" />

    <main id="main-content" class="flex-1" role="main">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="container mx-auto px-4 mt-4" role="alert">
                <div class="alert alert-success">
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="container mx-auto px-4 mt-4" role="alert">
                <div class="alert alert-error">
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Ad: Footer Above --}}
    <x-ad-slot position="footer_above" />

    @include('partials.footer')

    @vite(['resources/js/app.js'])
    @livewireScripts
    @stack('scripts')

    {{-- Initialize Lucide icons + re-init on Livewire/Alpine updates --}}
    <script>
        function initLucide() { if (window.lucide) lucide.createIcons(); }
        document.addEventListener('DOMContentLoaded', initLucide);
        document.addEventListener('livewire:navigated', initLucide);
        document.addEventListener('livewire:morph.updated', initLucide);
    </script>

    {{-- Ad: Mobile Sticky Bottom (Anchor-Ad 320×50) --}}
    <div id="mobile-sticky-ad" class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-base-100/90 backdrop-blur-sm safe-area-bottom">
        <x-ad-slot position="mobile_sticky_bottom" />
    </div>

    {{-- LEGAL-3: DSGVO Cookie-Consent --}}
    @include('partials.cookie-consent')
</body>
</html>
