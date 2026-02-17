{{-- Default Theme: Floating Glasmorphism Header (VR-2) --}}
<div class="header-spacer h-20"></div>

<header
    class="header-floating glass fixed top-3 left-1/2 -translate-x-1/2 w-[calc(100%-48px)] max-w-7xl z-50 transition-all duration-300"
    x-data="floatingHeader()"
    x-bind="scrollBind"
    :class="{ 'header-scrolled': scrolled }"
>
    <div class="px-4 md:px-6">
        <div class="flex items-center justify-between" :class="scrolled ? 'h-14' : 'h-16'" style="transition: height 300ms var(--ease-spring, cubic-bezier(0.22, 1, 0.36, 1))">

            {{-- Logo / Portal Name --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0 group">
                @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.logo_path'))
                    <img src="{{ asset($currentTenant->getAttribute('branding.logo_path')) }}"
                         alt="{{ $currentTenant->name ?? config('app.name') }}"
                         class="h-8 w-auto transition-transform duration-300 group-hover:scale-105"
                         :class="scrolled ? 'h-7' : 'h-8'"
                         style="transition: height 300ms var(--ease-spring)">
                @else
                    <span class="text-xl font-bold text-portal-primary-dark transition-all duration-300"
                          :class="scrolled ? 'text-lg' : 'text-xl'">
                        {{ $currentTenant->name ?? config('app.name') }}
                    </span>
                @endif
            </a>

            {{-- Desktop Navigation --}}
            <nav class="hidden md:flex items-center gap-1" aria-label="Hauptnavigation">
                <a href="{{ route('home') }}"
                   class="header-nav-link {{ request()->is('/') ? 'header-nav-active' : '' }}">
                    Startseite
                </a>
                <a href="{{ route('portal.companies.index') }}"
                   class="header-nav-link {{ request()->is('firmen*') ? 'header-nav-active' : '' }}">
                    Firmenverzeichnis
                </a>
                <a href="{{ route('portal.categories.index') }}"
                   class="header-nav-link {{ request()->is('kategorien*') ? 'header-nav-active' : '' }}">
                    Kategorien
                </a>
            </nav>

            {{-- Desktop: Auth + CTA --}}
            <div class="hidden md:flex items-center gap-3">
                @auth
                    @if(auth()->user()->companies && auth()->user()->companies->count() > 0)
                        <a href="{{ route('portal.owner.dashboard') }}"
                           class="header-nav-link font-medium">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                            Mein Firmenprofil
                        </a>
                    @endif
                    <a href="{{ route('portal.companies.create') }}"
                       class="header-cta-btn ripple">
                        Firma eintragen
                        <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="header-nav-link font-medium">
                        Anmelden
                    </a>
                    <a href="{{ route('portal.companies.create') }}"
                       class="header-cta-btn ripple">
                        Firma eintragen
                        <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                @endauth
            </div>

            {{-- Mobile: Hamburger --}}
            <button @click="mobileOpen = !mobileOpen"
                    class="md:hidden p-2 rounded-lg hover:bg-black/5 transition-colors touch-target"
                    :aria-expanded="mobileOpen.toString()"
                    aria-controls="mobile-menu"
                    aria-label="Menü öffnen">
                {{-- Animated hamburger → X --}}
                <div class="w-5 h-5 relative flex items-center justify-center">
                    <span class="absolute w-5 h-0.5 bg-current rounded-full transition-all duration-300"
                          :class="mobileOpen ? 'rotate-45' : '-translate-y-1.5'"></span>
                    <span class="absolute w-5 h-0.5 bg-current rounded-full transition-all duration-300"
                          :class="mobileOpen ? 'opacity-0 scale-0' : ''"></span>
                    <span class="absolute w-5 h-0.5 bg-current rounded-full transition-all duration-300"
                          :class="mobileOpen ? '-rotate-45' : 'translate-y-1.5'"></span>
                </div>
            </button>
        </div>
    </div>

    {{-- Mobile Navigation (Slide-down inside glass container) --}}
    <div x-show="mobileOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 max-h-0"
         x-transition:enter-end="opacity-100 max-h-96"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 max-h-96"
         x-transition:leave-end="opacity-0 max-h-0"
         x-cloak
         id="mobile-menu"
         class="md:hidden overflow-hidden border-t border-white/20"
         @keydown.escape="mobileOpen = false">
        <nav class="px-4 py-3 space-y-1" aria-label="Mobile Navigation">
            <a href="{{ route('home') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('/') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Startseite
            </a>
            <a href="{{ route('portal.companies.index') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('firmen*') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Firmenverzeichnis
            </a>
            <a href="{{ route('portal.categories.index') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('kategorien*') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Kategorien
            </a>
            <div class="pt-2 border-t border-black/10 space-y-1">
                @auth
                    @if(auth()->user()->companies && auth()->user()->companies->count() > 0)
                        <a href="{{ route('portal.owner.dashboard') }}"
                           class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target"
                           @click="mobileOpen = false">
                            Mein Firmenprofil
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                       class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target"
                       @click="mobileOpen = false">
                        Anmelden
                    </a>
                @endauth
                <a href="{{ route('portal.companies.create') }}"
                   class="block w-full text-center btn-portal text-sm py-2.5 ripple"
                   @click="mobileOpen = false">
                    Firma eintragen
                </a>
            </div>
        </nav>
    </div>
</header>
