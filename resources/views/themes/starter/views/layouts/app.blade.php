<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- SEO Meta --}}
    <title>@yield('title', ($currentTenant->name ?? config('app.name')))</title>
    <meta name="description" content="@yield('meta_description', '')">
    @hasSection('meta_robots')
        <meta name="robots" content="@yield('meta_robots')">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('title', ($currentTenant->name ?? config('app.name')))">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="{{ $currentTenant->name ?? config('app.name') }}">
    <meta property="og:locale" content="de_DE">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
        <meta property="og:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
    @endif

    {{-- Twitter Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', ($currentTenant->name ?? config('app.name')))">
    <meta name="twitter:description" content="@yield('meta_description', '')">
    @hasSection('og_image')
        <meta name="twitter:image" content="@yield('og_image')">
    @elseif(!empty($currentTenant) && $currentTenant->getAttribute('branding.og_image_path'))
        <meta name="twitter:image" content="{{ asset($currentTenant->getAttribute('branding.og_image_path')) }}">
    @endif

    {{-- Favicon --}}
    @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
        <link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
    @endif

    {{-- Canonical --}}
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- 1. Base CSS (Tailwind + DaisyUI) --}}
    @vite(['resources/css/app.css'])

    {{-- 2. Tenant CSS Variables (injected by ResolveTheme middleware) --}}
    {!! $tenantStyles ?? '' !!}

    @livewireStyles
    @stack('styles')

    {{-- Lucide Icons (Category icons etc.) --}}
    <script src="https://unpkg.com/lucide@0.574.0/dist/umd/lucide.min.js" defer></script>

    @include('partials.analytics')

    {{-- Auto Ads --}}
    <x-ad-slot position="auto_ads" />
</head>
<body class="min-h-screen flex flex-col bg-base-100 text-base-content antialiased pb-[60px] lg:pb-0">

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

    {{-- Global Mobile Sticky CTA --}}
    @php
        $ctaDomain = request()->getHost();
        $ctaTexts = [
            'makler-finden.com' => 'Kostenlose Vermittlung an passende Makler in Ihrer Region',
        ];
        $ctaText = $ctaTexts[$ctaDomain] ?? 'Angebote einholen & bis 30% sparen';
    @endphp
    <div class="fixed bottom-0 left-0 right-0 z-50 p-3 bg-base-100/95 backdrop-blur-sm border-t border-base-200 shadow-lg lg:hidden safe-area-bottom">
        <a href="{{ url('/anfragen') }}" class="btn-portal w-full flex items-center justify-center gap-2 py-3 rounded-lg text-sm font-semibold ripple">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            {{ $ctaText }}
        </a>
    </div>

    {{-- Ad: Mobile Sticky Bottom (Anchor-Ad 320×50) --}}
    <div id="mobile-sticky-ad" class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-base-100/90 backdrop-blur-sm safe-area-bottom">
        <x-ad-slot position="mobile_sticky_bottom" />
    </div>

    {{-- LEGAL-3: DSGVO Cookie-Consent --}}
    @include('partials.cookie-consent')
</body>
</html>
