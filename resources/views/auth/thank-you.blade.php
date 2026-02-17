<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                <div class="glass p-6 md:p-8 text-center" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    {{-- Success Icon --}}
                    <div class="flex justify-center mb-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center bg-green-100">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-base-content mb-2">
                        Willkommen!
                    </h2>

                    <p class="text-base-content/60 mb-6">
                        Ihr Konto wurde erfolgreich erstellt. Sie können jetzt Ihre Firma eintragen und sichtbar machen.
                    </p>

                    <div class="space-y-3">
                        <a href="{{ route('portal.companies.create') }}"
                           class="block w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg text-center">
                            Firma jetzt eintragen
                        </a>
                        <a href="{{ route('home') }}"
                           class="block w-full btn-portal-outline py-3 text-sm font-semibold rounded-lg transition-all duration-200 text-center">
                            Zum Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Registrierung
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">abgeschlossen!</span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Wir freuen uns, dass Sie dabei sind. Tragen Sie jetzt Ihr Unternehmen ein und werden Sie gefunden.
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
