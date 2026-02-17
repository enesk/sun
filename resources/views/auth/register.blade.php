<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                {{-- Mobile: Branding Header --}}
                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">
                        Konto erstellen
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Registrieren Sie sich kostenlos und tragen Sie Ihre Firma ein.
                    </p>
                </div>

                {{-- Glass Card --}}
                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    <h2 class="text-xl font-semibold text-base-content mb-1 hidden md:block">
                        Registrieren
                    </h2>
                    <p class="text-sm text-base-content/60 mb-6 hidden md:block">
                        Erstellen Sie Ihr kostenloses Konto in wenigen Sekunden.
                    </p>

                    @if($isOtpLoginEnabled)
                        <livewire:auth.register.one-time-password-registration />
                    @else
                        @include('auth.partials.traditional-registration-form')
                    @endif

                </div>

                {{-- Login Link --}}
                <p class="text-center text-sm text-base-content/60 mt-6">
                    Bereits registriert?
                    <a href="{{ route('login') }}" class="font-semibold text-portal-primary-dark hover:underline">
                        Jetzt anmelden
                    </a>
                </p>

            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            {{-- Heading --}}
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Machen Sie Ihre Firma
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">
                    sichtbar.
                </span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Tausende Kunden suchen täglich nach Dienstleistern in Ihrer Region.
            </p>

            {{-- Benefits --}}
            <div class="mt-10 space-y-5">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                         style="background: var(--portal-accent, #F59E0B); color: white;">
                        1
                    </div>
                    <div>
                        <p class="text-white font-medium">Kostenlos eintragen</p>
                        <p class="text-white/60 text-sm mt-0.5">Erstellen Sie Ihren Firmeneintrag in wenigen Minuten — komplett kostenlos.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                         style="background: var(--portal-accent, #F59E0B); color: white;">
                        2
                    </div>
                    <div>
                        <p class="text-white font-medium">Gefunden werden</p>
                        <p class="text-white/60 text-sm mt-0.5">Ihr Unternehmen erscheint in der Suche und bei Google — mehr Sichtbarkeit, mehr Kunden.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                         style="background: var(--portal-accent, #F59E0B); color: white;">
                        3
                    </div>
                    <div>
                        <p class="text-white font-medium">Vertrauen aufbauen</p>
                        <p class="text-white/60 text-sm mt-0.5">Sammeln Sie Bewertungen und zeigen Sie potenziellen Kunden, was Sie können.</p>
                    </div>
                </div>
            </div>

            {{-- Social Proof --}}
            <div class="mt-10 pt-8 border-t border-white/20">
                <div class="flex items-center gap-3">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white/30 flex items-center justify-center text-xs font-bold text-white" style="background: rgba(255,255,255,0.2);">M</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white/30 flex items-center justify-center text-xs font-bold text-white" style="background: rgba(255,255,255,0.15);">S</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white/30 flex items-center justify-center text-xs font-bold text-white" style="background: rgba(255,255,255,0.1);">K</div>
                    </div>
                    <p class="text-white/70 text-sm">
                        Bereits <span class="text-white font-semibold">tausende Unternehmen</span> vertrauen uns.
                    </p>
                </div>
            </div>
        </div>
    </x-slot>
</x-layouts.focus>
