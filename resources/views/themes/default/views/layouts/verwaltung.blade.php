<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Verwaltung') — {{ $currentTenant->name ?? config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">

    @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.favicon_path'))
        <link rel="icon" href="{{ asset($currentTenant->getAttribute('branding.favicon_path')) }}">
    @endif

    @vite(['resources/css/app.css'])
    {!! $tenantStyles ?? '' !!}

    {{-- Dashboard-specific tokens --}}
    <style>
        :root {
            --dash-sidebar-width: 16rem;
            --dash-sidebar-width-collapsed: 4rem;
            --dash-header-height: 3.5rem;
            --dash-bottom-bar-height: 4rem;
            --dash-content-max-width: 1280px;
        }

        /* Sidebar active state — uses portal tokens */
        .dash-nav-active {
            background-color: rgba(var(--portal-primary-rgb, 59 130 246), 0.08);
            color: var(--portal-primary-dark, #1e40af);
            font-weight: 600;
        }

        .dash-nav-active .dash-nav-icon {
            color: var(--portal-primary, #3b82f6);
        }

        /* Sidebar group label */
        .dash-nav-group {
            font-size: 0.675rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(0, 0, 0, 0.35);
            padding: 0.75rem 0.75rem 0.25rem;
        }

        /* Toast animations */
        .dash-toast-enter {
            animation: dashToastIn 0.3s ease-out;
        }
        .dash-toast-leave {
            animation: dashToastOut 0.3s ease-in forwards;
        }
        @keyframes dashToastIn {
            from { opacity: 0; transform: translateY(-0.5rem); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes dashToastOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-0.5rem); }
        }

        /* Safe area padding for mobile bottom bar */
        .safe-area-pb {
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        /* === FIX: Sidebar Desktop-Layout (Tailwind v4 specificity workaround) === */
        @media (min-width: 768px) {
            .dash-sidebar {
                position: static !important;
                transform: none !important;
                inset: auto !important;
                z-index: auto !important;
                width: var(--dash-sidebar-width) !important;
                flex-shrink: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                overflow-y: auto !important;
                height: 100% !important;
            }
        }

        /* Mobile: sidebar as overlay */
        @media (max-width: 767px) {
            .dash-sidebar {
                position: fixed;
                top: var(--dash-header-height);
                bottom: 0;
                left: 0;
                z-index: 40;
                width: 16rem;
            }
        }
    </style>

    @livewireStyles
    @stack('styles')

    @include('partials.analytics')
</head>
<body class="min-h-screen flex flex-col bg-base-100 text-base-content antialiased"
      x-data="dashboardApp()"
      x-cloak>

    {{-- Skip to Content (Accessibility) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded focus:shadow-lg focus:text-sm focus:font-medium"
       style="color: var(--portal-primary-dark, #1e40af);">
        Zum Inhalt springen
    </a>

    {{-- ================================================================ --}}
    {{-- HEADER — Compact, portal-branded                                --}}
    {{-- ================================================================ --}}
    <header class="sticky top-0 z-40 bg-base-100/95 backdrop-blur-sm border-b border-base-200"
            style="height: var(--dash-header-height);">
        <div class="h-full px-4 flex items-center justify-between">
            {{-- Left: Mobile menu toggle + Logo + Back to Portal --}}
            <div class="flex items-center gap-3">
                {{-- Mobile sidebar toggle --}}
                <button @click="sidebarOpen = !sidebarOpen"
                        class="md:hidden -ml-1 p-1.5 rounded-lg text-base-content/60 hover:bg-base-200 transition-colors"
                        aria-label="Navigation öffnen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>

                <a href="{{ route('home') }}"
                   class="flex items-center gap-2 text-sm text-base-content/60 hover:text-base-content transition-colors"
                   title="Zurück zum Portal">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span class="hidden sm:inline font-medium">{{ $currentTenant->name ?? config('app.name') }}</span>
                </a>

                <span class="text-base-content/20 hidden sm:inline">|</span>
                <span class="font-semibold text-sm" style="color: var(--portal-primary-dark, #1e40af);">Verwaltung</span>
            </div>

            {{-- Right: User menu --}}
            <div class="flex items-center gap-3" x-data="{ userMenuOpen: false }">
                <div class="relative">
                    <button @click="userMenuOpen = !userMenuOpen"
                            @click.outside="userMenuOpen = false"
                            class="flex items-center gap-2 text-sm text-base-content/70 hover:text-base-content transition-colors p-1.5 rounded-lg hover:bg-base-200">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                              style="background-color: var(--portal-primary, #3b82f6);">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                        <svg class="w-4 h-4 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    {{-- User dropdown --}}
                    <div x-show="userMenuOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-base-100 rounded-lg shadow-lg border border-base-200 py-1 z-50">
                        <a href="{{ route('verwaltung.profile') }}" class="block px-4 py-2 text-sm text-base-content/70 hover:bg-base-200 hover:text-base-content">
                            Profil & Sicherheit
                        </a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-base-content/70 hover:bg-base-200 hover:text-base-content">
                                Portal ansehen
                            </a>
                        @endif
                        <div class="border-t border-base-200 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                Abmelden
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden" style="height: calc(100vh - var(--dash-header-height));">
        {{-- ================================================================ --}}
        {{-- SIDEBAR — Desktop: always visible, Mobile: slide-over           --}}
        {{-- ================================================================ --}}

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-black/30 md:hidden"
             aria-hidden="true"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="dash-sidebar bg-base-100 border-r border-base-200 transition-transform duration-200 ease-in-out"
               aria-label="Dashboard-Navigation">

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                @php
                    $navGroups = $navigationItems ?? [];
                @endphp

                @foreach($navGroups as $group)
                    @php
                        $visibleItems = collect($group['items'])->where('visible', true);
                    @endphp

                    @if($visibleItems->isEmpty())
                        @continue
                    @endif

                    @if($group['group'])
                        <div class="dash-nav-group @if(!$loop->first) mt-2 @endif">
                            {{ $group['group'] }}
                        </div>
                    @elseif(!$loop->first)
                        <div class="my-2 border-t border-base-200"></div>
                    @endif

                    <div class="space-y-0.5">
                        @foreach($visibleItems as $item)
                            @php
                                $isActive = request()->routeIs($item['route'] . '*');
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
                                      {{ $isActive ? 'dash-nav-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                               @if($isActive) aria-current="page" @endif>
                                @include('partials.verwaltung.icon', ['icon' => $item['icon'], 'active' => $isActive])
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            {{-- Sidebar footer: Portal link --}}
            <div class="p-3 border-t border-base-200">
                <a href="{{ route('home') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors text-sm text-base-content/60 hover:text-base-content"
                   target="_blank">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                    Portal öffnen
                </a>
            </div>
        </aside>

        {{-- ================================================================ --}}
        {{-- MAIN CONTENT                                                    --}}
        {{-- ================================================================ --}}
        <main id="main-content" class="flex-1 min-w-0 overflow-y-auto pb-20 md:pb-0" role="main">

            {{-- Breadcrumbs --}}
            @if(!empty($breadcrumbs))
                <nav class="px-4 sm:px-6 lg:px-8 pt-4 pb-1" aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1.5 text-sm text-base-content/50">
                        <li>
                            <a href="{{ route('verwaltung.index') }}" class="hover:text-base-content transition-colors">
                                Verwaltung
                            </a>
                        </li>
                        @foreach($breadcrumbs as $crumb)
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                                @if(isset($crumb['url']))
                                    <a href="{{ $crumb['url'] }}" class="hover:text-base-content transition-colors">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="text-base-content font-medium">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            {{-- Page content --}}
            <div class="px-4 sm:px-6 lg:px-8 py-6" style="max-width: var(--dash-content-max-width);">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- ================================================================ --}}
    {{-- MOBILE BOTTOM-TAB-BAR                                           --}}
    {{-- ================================================================ --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-base-100/95 backdrop-blur-sm border-t border-base-200 safe-area-pb"
         aria-label="Mobile Navigation"
         style="height: var(--dash-bottom-bar-height);">
        <div class="grid grid-cols-5 h-full">
            @php
                $mobileItems = [
                    ['route' => 'verwaltung.index', 'label' => 'Übersicht', 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
                    ['route' => 'verwaltung.companies.index', 'label' => 'Firmen', 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                    ['route' => 'verwaltung.reviews.index', 'label' => 'Reviews', 'icon' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
                    ['route' => 'verwaltung.users.index', 'label' => 'Team', 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
                    ['route' => 'verwaltung.settings.general', 'label' => 'Mehr', 'icon' => 'M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z'],
                ];
            @endphp

            @foreach($mobileItems as $item)
                @php
                    $isActive = request()->routeIs($item['route'] . '*');
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-xs transition-colors
                          {{ $isActive ? 'font-medium' : 'text-base-content/50' }}"
                   style="{{ $isActive ? 'color: var(--portal-primary, #3b82f6);' : '' }}"
                   @if($isActive) aria-current="page" @endif>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $isActive ? '2.5' : '1.5' }}" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    {{-- ================================================================ --}}
    {{-- TOAST NOTIFICATIONS (Livewire → Alpine)                         --}}
    {{-- ================================================================ --}}
    <div x-data="toastManager()"
         @toast.window="addToast($event.detail)"
         class="fixed top-16 right-4 z-50 space-y-2 w-80"
         aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="dash-toast-enter"
                 x-transition:leave="dash-toast-leave"
                 class="flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg border text-sm"
                 :class="{
                     'bg-green-50 border-green-200 text-green-800': toast.type === 'success',
                     'bg-red-50 border-red-200 text-red-800': toast.type === 'error',
                     'bg-blue-50 border-blue-200 text-blue-800': toast.type === 'info',
                     'bg-yellow-50 border-yellow-200 text-yellow-800': toast.type === 'warning',
                 }">
                <span x-text="toast.message" class="flex-1"></span>
                <button @click="removeToast(toast.id)" class="shrink-0 opacity-50 hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    @vite(['resources/js/app.js'])
    @livewireScripts

    <script>
        function dashboardApp() {
            return {
                sidebarOpen: false,
            }
        }

        function toastManager() {
            return {
                toasts: [],
                nextId: 0,
                addToast({ type = 'info', message = '', duration = 5000 }) {
                    const id = this.nextId++;
                    this.toasts.push({ id, type, message, visible: true });
                    if (duration > 0) {
                        setTimeout(() => this.removeToast(id), duration);
                    }
                },
                removeToast(id) {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) toast.visible = false;
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 300);
                }
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
