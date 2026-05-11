{{-- Default Theme: Floating Glasmorphism Header (VR-2) --}}
<div class="header-spacer h-16"></div>

<header
    class="header-floating fixed left-1/2 -translate-x-1/2 z-50 transition-all duration-500"
    x-data="floatingHeader()"
    x-bind="scrollBind"
    :class="scrolled ? 'header-scrolled glass w-[calc(100%-48px)] max-w-7xl top-3' : 'header-expanded w-full top-0'"
    style="transition-timing-function: cubic-bezier(0.22, 1, 0.36, 1);"
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
                <a href="{{ route('portal.cities.index') }}"
                   class="header-nav-link {{ request()->is('staedte*') ? 'header-nav-active' : '' }}">
                    Städte
                </a>
                <a href="{{ route('portal.jobs.index') }}"
                   class="header-nav-link {{ request()->is('jobs*') ? 'header-nav-active' : '' }}">
                    Stellenanzeigen
                </a>
                <a href="{{ route('portal.blog.index') }}"
                   class="header-nav-link {{ request()->is('ratgeber*') ? 'header-nav-active' : '' }}">
                    Ratgeber
                </a>
            </nav>

            {{-- Desktop: Auth + CTA --}}
            <div class="hidden md:flex items-center gap-3">
                @auth
                    @php
                        $ownedCompany = auth()->user()->getOwnedCompany();
                        $pendingClaim = $ownedCompany ? null : auth()->user()->getPendingClaimRequest();
                    @endphp

                    @if($ownedCompany)
                        {{-- Premium Badge --}}
                        @if($ownedCompany->is_premium)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                                  style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1)); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.25);">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                Premium
                            </span>
                        @endif

                        {{-- User besitzt eine Firma → Link zum öffentlichen Profil --}}
                        <a href="{{ $ownedCompany->portal_url }}"
                           class="header-nav-link font-medium">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                            Mein Firmenprofil
                        </a>
                        <a href="{{ route('portal.owner.edit') }}"
                           class="header-cta-btn ripple">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Firma bearbeiten
                        </a>
                    @elseif($pendingClaim && $pendingClaim->company)
                        {{-- User hat einen Claim-Antrag laufen → Link zur Verifizierungsseite --}}
                        <a href="{{ url('/firma/' . $pendingClaim->company->slug . '/verifizierung') }}"
                           class="header-cta-btn ripple">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Verifizierung läuft
                        </a>
                    @else
                        {{-- User hat weder Firma noch Claim → Firma eintragen --}}
                        <a href="{{ route('portal.companies.create') }}"
                           class="header-cta-btn ripple">
                            Firma eintragen
                            <svg class="w-4 h-4 ml-1 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="header-nav-link font-medium text-red-600/80 hover:text-red-700 hover:bg-red-50/50"
                                aria-label="Abmelden">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Abmelden
                        </button>
                    </form>
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
            <a href="{{ route('portal.cities.index') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('staedte*') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Städte
            </a>
            <a href="{{ route('portal.jobs.index') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('jobs*') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Stellenanzeigen
            </a>
            <a href="{{ route('portal.blog.index') }}"
               class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target
                      {{ request()->is('ratgeber*') ? 'bg-black/5 text-portal-primary-dark' : '' }}"
               @click="mobileOpen = false">
                Ratgeber
            </a>
            <div class="pt-2 border-t border-black/10 space-y-1">
                @auth
                    @php
                        // Variablen aus Desktop-Block wiederverwenden (sind im selben Scope)
                        $ownedCompany = $ownedCompany ?? auth()->user()->getOwnedCompany();
                        $pendingClaim = $pendingClaim ?? ($ownedCompany ? null : auth()->user()->getPendingClaimRequest());
                    @endphp

                    @if($ownedCompany)
                        <a href="{{ $ownedCompany->portal_url }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target"
                           @click="mobileOpen = false">
                            Mein Firmenprofil
                            @if($ownedCompany->is_premium)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                      style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1)); color: #b45309;">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                    Premium
                                </span>
                            @endif
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="block w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium text-red-600/80 transition-colors hover:bg-red-50/50 touch-target"
                                @click="mobileOpen = false">
                            <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Abmelden
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors hover:bg-black/5 touch-target"
                       @click="mobileOpen = false">
                        Anmelden
                    </a>
                @endauth

                @if(auth()->check() && isset($ownedCompany) && $ownedCompany)
                    <a href="{{ route('portal.owner.edit') }}"
                       class="block w-full text-center btn-portal text-sm py-2.5 ripple"
                       @click="mobileOpen = false">
                        Firma bearbeiten
                    </a>
                @elseif(auth()->check() && isset($pendingClaim) && $pendingClaim && $pendingClaim->company)
                    <a href="{{ url('/firma/' . $pendingClaim->company->slug . '/verifizierung') }}"
                       class="block w-full text-center btn-portal text-sm py-2.5 ripple"
                       @click="mobileOpen = false">
                        Verifizierung läuft
                    </a>
                @else
                    <a href="{{ route('portal.companies.create') }}"
                       class="block w-full text-center btn-portal text-sm py-2.5 ripple"
                       @click="mobileOpen = false">
                        Firma eintragen
                    </a>
                @endif
            </div>
        </nav>
    </div>
</header>
