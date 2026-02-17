<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">E-Mail bestätigen</h1>
                </div>

                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    {{-- Email Icon --}}
                    <div class="flex justify-center mb-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1);">
                            <svg class="w-8 h-8 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-base-content text-center mb-2">
                        Prüfen Sie Ihren Posteingang
                    </h2>

                    @if (session('sent'))
                        <div role="alert" class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm text-center">
                            Ein neuer Bestätigungslink wurde gesendet.
                        </div>
                    @endif

                    <p class="text-sm text-base-content/60 text-center mb-6">
                        Wir haben Ihnen einen Bestätigungslink per E-Mail gesendet. Bitte klicken Sie auf den Link, um Ihr Konto zu aktivieren.
                    </p>

                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit"
                                class="w-full btn-portal-outline py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
                            Erneut senden
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                E-Mail
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">bestätigen</span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Dieser Schritt ist nötig, um die Sicherheit Ihres Kontos zu gewährleisten.
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
