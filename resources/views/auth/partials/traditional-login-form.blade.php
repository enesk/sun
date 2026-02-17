<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf

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
               autofocus
               autocomplete="email"
               placeholder="ihre@email.de"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="focus:ring-color: var(--portal-primary, #3B82F6); --tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);"
               aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}">
        @error('email')
            <p id="email-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password --}}
    <div>
        <div class="flex justify-between items-center mb-1.5">
            <label for="password" class="block text-sm font-medium text-base-content">
                Passwort
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-xs font-medium hover:underline"
                   style="color: var(--portal-primary-dark, #1E3A5F);">
                    Passwort vergessen?
                </a>
            @endif
        </div>
        <input id="password"
               type="password"
               name="password"
               required
               autocomplete="current-password"
               placeholder="Ihr Passwort"
               class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                      focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
               style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);"
               aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}">
        @error('password')
            <p id="password-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </div>

    {{-- Remember Me --}}
    <div class="flex items-center gap-2">
        <input type="checkbox"
               name="remember"
               id="remember"
               {{ old('remember') ? 'checked' : '' }}
               class="w-4 h-4 rounded border-base-300 focus:ring-2 transition-colors"
               style="accent-color: var(--portal-primary, #3B82F6);">
        <label for="remember" class="text-sm text-base-content/70">
            Angemeldet bleiben
        </label>
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

    {{-- Submit --}}
    <button type="submit"
            class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
        Anmelden
    </button>

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
