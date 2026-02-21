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

    {{-- Tailwind v4 fix: translate property gets stripped by Lightning CSS during build,
         but TW4 uses `translate` (not `transform`) for -translate-x-full.
         Inline style bypasses the build pipeline. --}}
    <style>
        @media (min-width: 768px) {
            .dash-sidebar {
                translate: none !important;
                transform: none !important;
            }
        }
    </style>
    {!! $tenantStyles ?? '' !!}

    @livewireStyles
    @stack('styles')

    @include('partials.analytics')
</head>
<body class="min-h-screen flex flex-col antialiased"
      style="background-color: var(--dash-bg, #f8fafc); color: var(--dash-text-primary, #1a1a2e);"
      x-data="dashboardApp()"
      x-cloak>

    {{-- Skip to Content (Accessibility) --}}
    <a href="#main-content" class="dash-skip-link">
        Zum Inhalt springen
    </a>

    {{-- Live region for dynamic announcements (screen readers) --}}
    <div id="dash-announcements" class="dash-live-region" aria-live="polite" aria-atomic="true"></div>

    {{-- ================================================================ --}}
    {{-- HEADER — Compact, portal-branded                                --}}
    {{-- ================================================================ --}}
    <header class="dash-header">
        <div class="dash-header-inner">
            {{-- Left: Mobile menu toggle + Logo + Back to Portal --}}
            <div class="dash-header-left">
                {{-- Mobile sidebar toggle --}}
                <button @click="toggleSidebar()"
                        class="dash-header-toggle"
                        aria-label="Navigation öffnen"
                        :aria-expanded="sidebarOpen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>

                <a href="{{ route('home') }}"
                   class="dash-header-brand"
                   title="Zurück zum Portal">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span class="dash-header-brand-name">{{ $currentTenant->name ?? config('app.name') }}</span>
                </a>

                <span class="dash-header-divider">|</span>
                <span class="dash-header-title">Verwaltung</span>
            </div>

            {{-- Right: User menu --}}
            <div class="dash-header-right" x-data="{ userMenuOpen: false }">
                <div class="relative">
                    <button @click="userMenuOpen = !userMenuOpen"
                            @click.outside="userMenuOpen = false"
                            @keydown.escape="userMenuOpen = false"
                            class="dash-header-user-btn"
                            aria-label="Benutzermenü"
                            aria-haspopup="true"
                            :aria-expanded="userMenuOpen">
                        <span class="dash-header-avatar" aria-hidden="true">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </span>
                        <span class="dash-header-user-name">{{ auth()->user()->name }}</span>
                        <svg class="dash-header-user-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                         @keydown.escape="userMenuOpen = false; $refs.userMenuBtn?.focus()"
                         class="dash-header-dropdown"
                         role="menu"
                         aria-label="Benutzermenü"
                         :aria-hidden="!userMenuOpen">
                        <a href="{{ route('verwaltung.profile') }}" class="dash-header-dropdown-item" role="menuitem">
                            Profil & Sicherheit
                        </a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('home') }}" class="dash-header-dropdown-item" role="menuitem">
                                Portal ansehen
                            </a>
                        @endif
                        <div class="dash-header-dropdown-divider" role="separator"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dash-header-dropdown-item dash-header-dropdown-item-danger" role="menuitem">
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
             @click="sidebarOpen = false; if (_previousFocus) _previousFocus.focus();"
             class="dash-sidebar-overlay md:hidden"
             aria-hidden="true"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="dash-sidebar transition-transform duration-200 ease-in-out"
               style="background-color: var(--dash-surface, #ffffff); border-right: 1px solid var(--dash-border, rgba(0,0,0,0.08));"
               aria-label="Dashboard-Navigation">

            <nav class="flex-1 overflow-y-auto px-3 py-4 dash-sidebar-nav">
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
                        <div class="my-2" style="border-top: 1px solid var(--dash-border, rgba(0,0,0,0.08));"></div>
                    @endif

                    <div class="space-y-0.5">
                        @foreach($visibleItems as $item)
                            @php
                                $isActive = request()->routeIs($item['route'] . '*');
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               class="dash-nav-item {{ $isActive ? 'dash-nav-active' : '' }}"
                               @if($isActive) aria-current="page" @endif>
                                @include('partials.verwaltung.icon', ['icon' => $item['icon'], 'active' => $isActive])
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            {{-- Sidebar footer: Portal link --}}
            <footer class="dash-sidebar-footer">
                <a href="{{ route('home') }}" target="_blank" rel="noopener"
                   aria-label="Portal in neuem Tab öffnen">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                    Portal öffnen
                    <span class="dash-sr-only">(öffnet in neuem Tab)</span>
                </a>
            </footer>
        </aside>

        {{-- ================================================================ --}}
        {{-- MAIN CONTENT                                                    --}}
        {{-- ================================================================ --}}
        <main id="main-content" class="dash-main" role="main">

            {{-- Breadcrumbs --}}
            @if(!empty($breadcrumbs))
                <nav class="dash-breadcrumb" aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1.5">
                        <li>
                            <a href="{{ route('verwaltung.index') }}">
                                Verwaltung
                            </a>
                        </li>
                        @foreach($breadcrumbs as $crumb)
                            <li class="flex items-center gap-1.5">
                                <svg class="dash-breadcrumb-sep" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                                @if(isset($crumb['url']))
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="dash-breadcrumb-current">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="dash-flash dash-flash-success">
                        <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="px-4 sm:px-6 lg:px-8 pt-4" role="alert">
                    <div class="dash-flash dash-flash-error">
                        <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            {{-- Page content --}}
            <div class="dash-content">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- ================================================================ --}}
    {{-- MOBILE BOTTOM-TAB-BAR                                           --}}
    {{-- ================================================================ --}}
    <nav class="dash-bottom-bar" aria-label="Mobile Navigation">
        <ul class="dash-bottom-bar-grid" role="list">
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
                <li>
                    <a href="{{ route($item['route']) }}"
                       class="dash-bottom-bar-item {{ $isActive ? 'dash-bottom-bar-item-active' : '' }}"
                       @if($isActive) aria-current="page" @endif>
                        <svg class="dash-bottom-bar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             stroke-width="{{ $isActive ? '2' : '1.5' }}" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span class="dash-bottom-bar-label">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- ================================================================ --}}
    {{-- TOAST NOTIFICATIONS (Livewire → Alpine)                         --}}
    {{-- ================================================================ --}}
    <div x-data="toastManager()"
         @toast.window="addToast($event.detail)"
         class="fixed top-16 right-4 space-y-2 w-80"
         style="z-index: var(--dash-z-toast, 60);"
         aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="dash-toast-enter"
                 x-transition:leave="dash-toast-leave"
                 class="dash-toast"
                 :class="{
                     'dash-toast-success': toast.type === 'success',
                     'dash-toast-error': toast.type === 'error',
                     'dash-toast-info': toast.type === 'info',
                     'dash-toast-warning': toast.type === 'warning',
                 }">
                <span x-text="toast.message" class="flex-1"></span>
                <button @click="removeToast(toast.id)" class="dash-toast-close" aria-label="Schließen">
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
                _previousFocus: null,

                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    if (this.sidebarOpen) {
                        this._previousFocus = document.activeElement;
                        this.$nextTick(() => {
                            const sidebar = document.querySelector('.dash-sidebar');
                            const firstLink = sidebar?.querySelector('.dash-nav-item');
                            if (firstLink) firstLink.focus();
                        });
                    } else {
                        if (this._previousFocus) this._previousFocus.focus();
                    }
                },

                closeSidebarOnEscape(e) {
                    if (e.key === 'Escape' && this.sidebarOpen && window.innerWidth < 768) {
                        this.sidebarOpen = false;
                        if (this._previousFocus) this._previousFocus.focus();
                    }
                },

                init() {
                    document.addEventListener('keydown', (e) => this.closeSidebarOnEscape(e));
                    this.announce = (msg) => {
                        const el = document.getElementById('dash-announcements');
                        if (el) { el.textContent = ''; requestAnimationFrame(() => { el.textContent = msg; }); }
                    };
                }
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
