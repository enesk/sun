<form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="passwordStrength">
    @csrf

    {{-- Tiefenverteidigung (#8): Honeypot und verschluesselter Zeitstempel.
         Geprueft in RegisterController::register() vor der Validierung; ein
         Treffer wird still abgewiesen. --}}
    <x-antispam-fields />

    {{-- Name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-base-content mb-1.5">
            Ihr Name
        </label>
        <input id="name"
               type="text"
               name="name"
               value="{{ old('name') }}"
               required
               autofocus
               autocomplete="name"
               placeholder="Max Mustermann"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
        @error('name')
            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </div>

    {{-- Email --}}
    <div>
        <label for="email" class="block text-sm font-medium text-base-content mb-1.5">
            E-Mail-Adresse
        </label>
        <input id="email"
               type="email"
               name="email"
               value="{{ old('email') }}"
               required
               autocomplete="email"
               placeholder="ihre@email.de"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
        @error('email')
            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password with Strength Indicator --}}
    <div>
        <label for="password" class="block text-sm font-medium text-base-content mb-1.5">
            Passwort
        </label>
        <input id="password"
               type="password"
               name="password"
               required
               autocomplete="new-password"
               placeholder="Mindestens 8 Zeichen"
               x-model="password"
               @input="checkStrength()"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
        @error('password')
            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror

        {{-- Strength Bar --}}
        <div x-show="password.length > 0" x-cloak class="mt-2">
            <div class="flex gap-1 mb-1">
                <template x-for="i in 4" :key="i">
                    <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                         :class="i <= strength ? strengthColor : 'bg-base-300'"></div>
                </template>
            </div>
            <p class="text-xs" :class="strengthTextColor" x-text="strengthLabel"></p>
        </div>
    </div>

    {{-- Confirm Password --}}
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-base-content mb-1.5">
            Passwort bestätigen
        </label>
        <input id="password_confirmation"
               type="password"
               name="password_confirmation"
               required
               autocomplete="new-password"
               placeholder="Passwort wiederholen"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
    </div>

    @if (config('app.recaptcha_enabled'))
        <div class="my-2">
            {!! htmlFormSnippet() !!}
        </div>
        @error('g-recaptcha-response')
            <p class="text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
        @push('tail')
            {!! htmlScriptTagJsApi() !!}
        @endpush
    @endif

    {{-- Turnstile (#6): Pflicht fuer jede Kontoanlage, geprueft in TurnstileRule --}}
    <x-turnstile action="registration" />

    {{-- Submit --}}
    <button type="submit"
            class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
        Kostenlos registrieren
    </button>

    {{-- Terms --}}
    <p class="text-xs text-center text-base-content/50 leading-relaxed">
        Mit der Registrierung stimmen Sie unseren
        <a href="{{ route('portal.datenschutz') }}" class="underline hover:text-base-content/70">Datenschutzbestimmungen</a> zu.
    </p>

    {{-- Social Login --}}
    <x-auth.social-login>
        <x-slot name="before">
            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-base-300"></div>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="px-3 bg-white text-base-content/50">oder</span>
                </div>
            </div>
        </x-slot>
    </x-auth.social-login>
</form>

