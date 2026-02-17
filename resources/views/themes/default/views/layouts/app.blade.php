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
</head>
<body class="min-h-screen flex flex-col bg-base-100 text-base-content antialiased">

    {{-- Skip to Content (Accessibility) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded focus:shadow-lg focus:text-sm focus:font-medium text-portal-primary-dark">
        Zum Inhalt springen
    </a>

    @include('partials.header')

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

    @include('partials.footer')

    @vite(['resources/js/app.js'])
    @livewireScripts
    @stack('scripts')
</body>
</html>
