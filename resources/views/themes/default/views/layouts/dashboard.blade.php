<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Firmenprofil') — {{ $currentTenant->name ?? config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">

    @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
        <link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
    @endif

    @vite(['resources/css/app.css'])
    {!! $tenantStyles ?? '' !!}
    @stack('styles')

    @include('partials.analytics')
</head>
<body class="min-h-screen flex flex-col bg-base-100 text-base-content antialiased">

    {{-- Skip to Content (Accessibility) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded focus:shadow-lg focus:text-sm focus:font-medium text-portal-primary-dark">
        Zum Inhalt springen
    </a>

    {{-- Dashboard Header (kompakter als Portal-Header) --}}
    <header class="sticky top-0 z-40 bg-base-100 border-b border-base-200">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                {{-- Logo + Zurück zum Portal --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-sm text-base-content/60 hover:text-base-content transition-colors" aria-label="Zurück zum Portal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span class="hidden sm:inline">{{ $currentTenant->name ?? config('app.name') }}</span>
                    </a>
                    <span class="text-base-content/20 hidden sm:inline">|</span>
                    <span class="font-semibold text-sm text-portal-primary-dark">Firmenprofil</span>
                </div>

                {{-- User Info + Logout --}}
                <div class="flex items-center gap-3">
                    <span class="text-sm text-base-content/60 hidden sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-base-content/50 hover:text-base-content transition-colors touch-target flex items-center gap-1" aria-label="Abmelden">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">Abmelden</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-1 flex">
        {{-- Desktop Sidebar --}}
        <aside class="hidden md:flex md:w-64 md:shrink-0 md:flex-col md:border-r md:border-base-200 md:bg-base-100 md:overflow-y-auto" aria-label="Dashboard-Navigation">
            <nav class="flex-1 px-3 py-4 space-y-1">
                @php
                    $navItems = [
                        ['route' => 'portal.owner.dashboard', 'label' => 'Übersicht', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'match' => 'firmenprofil'],
                        ['route' => 'portal.owner.edit', 'label' => 'Profil bearbeiten', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'match' => 'firmenprofil/bearbeiten'],
                        ['route' => 'portal.owner.reviews', 'label' => 'Bewertungen', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'match' => 'firmenprofil/bewertungen'],
                        ['route' => 'portal.owner.stats', 'label' => 'Statistiken', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'match' => 'firmenprofil/statistiken'],
                        ['route' => 'portal.owner.settings', 'label' => 'Einstellungen', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'match' => 'firmenprofil/einstellungen'],
                        ['route' => 'portal.owner.premium', 'label' => 'Premium', 'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'match' => 'firmenprofil/premium'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    @php
                        $isActive = $item['match'] === 'firmenprofil'
                            ? request()->is('firmenprofil') && !request()->is('firmenprofil/*')
                            : request()->is($item['match'] . '*');
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors touch-target
                              {{ $isActive ? 'sidebar-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                       @if($isActive) aria-current="page" @endif>
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                        </svg>
                        {{ $item['label'] }}
                        @if($item['route'] === 'portal.owner.reviews' && isset($pendingReviewCount) && $pendingReviewCount > 0)
                            <span class="badge badge-sm badge-portal-accent ml-auto">{{ $pendingReviewCount }}</span>
                        @endif
                        @if($item['route'] === 'portal.owner.premium' && isset($ownerCompany) && !$ownerCompany->is_premium)
                            <span class="badge badge-sm badge-portal-accent ml-auto">Upgrade</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            {{-- Firmenvorschau in Sidebar --}}
            @if(isset($ownerCompany))
                <div class="p-3 border-t border-base-200">
                    <a href="{{ route('portal.companies.show', $ownerCompany->url_slug) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors text-sm text-base-content/60 hover:text-base-content"
                       target="_blank">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Firmenseite ansehen
                    </a>
                </div>
            @endif
        </aside>

        {{-- Main Content --}}
        <main id="main-content" class="flex-1 min-w-0 pb-20 md:pb-0" role="main">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="alert alert-success">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="alert alert-error">
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <div class="px-4 sm:px-6 lg:px-8 py-6">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Mobile Bottom-Tab-Bar --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-base-100 border-t border-base-200 safe-area-pb" aria-label="Mobile Dashboard-Navigation">
        <div class="grid grid-cols-5 h-16">
            @php
                $mobileNav = [
                    ['route' => 'portal.owner.dashboard', 'label' => 'Übersicht', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'match' => 'firmenprofil'],
                    ['route' => 'portal.owner.edit', 'label' => 'Profil', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'match' => 'firmenprofil/bearbeiten'],
                    ['route' => 'portal.owner.reviews', 'label' => 'Reviews', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'match' => 'firmenprofil/bewertungen'],
                    ['route' => 'portal.owner.stats', 'label' => 'Statistik', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'match' => 'firmenprofil/statistiken'],
                    ['route' => 'portal.owner.premium', 'label' => 'Premium', 'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'match' => 'firmenprofil/premium'],
                ];
            @endphp

            @foreach($mobileNav as $item)
                @php
                    $isActive = $item['match'] === 'firmenprofil'
                        ? request()->is('firmenprofil') && !request()->is('firmenprofil/*')
                        : request()->is($item['match'] . '*');
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-xs transition-colors
                          {{ $isActive ? 'text-portal-primary font-medium' : 'text-base-content/50' }}"
                   @if($isActive) aria-current="page" @endif>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $isActive ? '2.5' : '2' }}" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @vite(['resources/js/app.js'])
    @livewireScripts
    @stack('scripts')
</body>
</html>
