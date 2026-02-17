<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                {{-- Mobile: Branding Header --}}
                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">
                        Willkommen zurück
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Melden Sie sich an, um Ihr Firmenprofil zu verwalten.
                    </p>
                </div>

                {{-- Glass Card --}}
                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    <h2 class="text-xl font-semibold text-base-content mb-1 hidden md:block">
                        Anmelden
                    </h2>
                    <p class="text-sm text-base-content/60 mb-6 hidden md:block">
                        Verwalten Sie Ihren Firmeneintrag und Bewertungen.
                    </p>

                    @if($isOtpLoginEnabled)
                        <livewire:auth.login.one-time-password-login />
                    @else
                        @include('auth.partials.traditional-login-form')
                    @endif

                </div>

                {{-- Register Link (unterhalb der Card) --}}
                <p class="text-center text-sm text-base-content/60 mt-6">
                    Noch kein Konto?
                    <a href="{{ route('register') }}" class="font-semibold text-portal-primary-dark hover:underline">
                        Jetzt registrieren
                    </a>
                </p>

            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            {{-- Heading --}}
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Willkommen zurück bei
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">
                    {{ $currentTenant->name ?? config('app.name') }}
                </span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Verwalten Sie Ihren Firmeneintrag, antworten Sie auf Bewertungen und erreichen Sie neue Kunden.
            </p>

            {{-- Trust Signals --}}
            <div class="mt-10 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(255,255,255,0.15);">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-medium text-sm">Sichere Verbindung</p>
                        <p class="text-white/60 text-xs mt-0.5">Ihre Daten sind verschlüsselt und geschützt.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(255,255,255,0.15);">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-medium text-sm">Sofort loslegen</p>
                        <p class="text-white/60 text-xs mt-0.5">Profil bearbeiten, Statistiken einsehen, Bewertungen beantworten.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(255,255,255,0.15);">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-medium text-sm">Bewertungen im Blick</p>
                        <p class="text-white/60 text-xs mt-0.5">Sehen und beantworten Sie Kundenbewertungen.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-layouts.focus>
