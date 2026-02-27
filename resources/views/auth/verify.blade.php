<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                {{-- Mobile Branding --}}
                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">E-Mail bestätigen</h1>
                </div>

                {{-- Glass Card --}}
                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    {{-- Animated Email Icon --}}
                    <div class="flex justify-center mb-5">
                        <div class="verify-icon-container">
                            <div class="verify-icon-ring"></div>
                            <div class="verify-icon-badge">
                                <svg class="w-8 h-8 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-base-content text-center mb-2">
                        Prüfen Sie Ihren Posteingang
                    </h2>

                    @if(auth()->user())
                        <p class="text-sm text-center mb-1" style="color: var(--portal-primary, #3B82F6); font-weight: 600;">
                            {{ auth()->user()->email }}
                        </p>
                    @endif

                    {{-- Success Alert --}}
                    @if (session('sent'))
                        <div role="alert" class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm text-center flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Ein neuer Bestätigungslink wurde gesendet.
                        </div>
                    @endif

                    <p class="text-sm text-base-content/60 text-center mb-6">
                        Wir haben Ihnen einen Bestätigungslink per E-Mail gesendet. Klicken Sie auf den Link, um Ihr Konto zu aktivieren.
                    </p>

                    {{-- Resend mit Countdown --}}
                    <form method="POST" action="{{ route('verification.send') }}" x-data="resendTimer()">
                        @csrf
                        <button type="submit"
                                class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target"
                                x-bind:disabled="cooldown > 0"
                                x-bind:class="{ 'opacity-50 cursor-not-allowed': cooldown > 0 }"
                                @click="startCooldown()">
                            <span x-show="cooldown <= 0">Bestätigungslink erneut senden</span>
                            <span x-show="cooldown > 0" x-text="'Erneut senden in ' + cooldown + 's'" x-cloak></span>
                        </button>
                    </form>

                    {{-- Spam-Tipps --}}
                    <div class="verify-spam-tips">
                        <p class="verify-spam-tips__title">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Keine E-Mail erhalten?
                        </p>
                        <ul class="verify-spam-tips__list">
                            <li>Prüfen Sie Ihren <strong>Spam-Ordner</strong></li>
                            <li>Prüfen Sie die E-Mail-Adresse auf Tippfehler</li>
                            <li>Warten Sie 1–2 Minuten — manchmal dauert die Zustellung</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            {{-- Heading --}}
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Fast geschafft
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">— nur noch ein Klick.</span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Bestätigen Sie Ihre E-Mail-Adresse, um Ihren Eintrag freizuschalten und Ihr Unternehmen zu verwalten.
            </p>

            {{-- Progress Steps --}}
            <div class="mt-10 space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                         style="background: rgba(255,255,255,0.2); color: white;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white/60 font-medium line-through">Konto erstellt</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold animate-pulse"
                         style="background: var(--portal-accent, #F59E0B); color: white;">
                        2
                    </div>
                    <div>
                        <p class="text-white font-semibold">E-Mail bestätigen</p>
                        <p class="text-white/50 text-sm mt-0.5">Klicken Sie den Link in der E-Mail</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                         style="background: rgba(255,255,255,0.1); color: white/50;">
                        3
                    </div>
                    <div>
                        <p class="text-white/40 font-medium">Firma verwalten</p>
                    </div>
                </div>
            </div>

            {{-- Trust Signal --}}
            <div class="mt-10 pt-8 border-t border-white/20">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-white/60 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="text-white/60 text-sm">
                        Ihre Daten sind <span class="text-white font-semibold">SSL-verschlüsselt</span> und DSGVO-konform gespeichert.
                    </p>
                </div>
            </div>
        </div>
    </x-slot>
</x-layouts.focus>

@push('scripts')
<script>
    function resendTimer() {
        return {
            cooldown: {{ session('sent') ? 60 : 0 }},
            interval: null,
            startCooldown() {
                this.cooldown = 60;
                this.interval = setInterval(() => {
                    this.cooldown--;
                    if (this.cooldown <= 0) {
                        clearInterval(this.interval);
                    }
                }, 1000);
            },
            init() {
                if (this.cooldown > 0) {
                    this.startCooldown();
                }
            }
        }
    }
</script>
@endpush
