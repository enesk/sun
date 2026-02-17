<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                {{-- Mobile Header --}}
                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">Passwort zurücksetzen</h1>
                    <p class="mt-1 text-sm text-base-content/60">Wir senden Ihnen einen Link per E-Mail.</p>
                </div>

                {{-- Glass Card --}}
                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    <h2 class="text-xl font-semibold text-base-content mb-1 hidden md:block">
                        Passwort vergessen?
                    </h2>
                    <p class="text-sm text-base-content/60 mb-6 hidden md:block">
                        Geben Sie Ihre E-Mail-Adresse ein. Wir senden Ihnen einen Link zum Zurücksetzen.
                    </p>

                    @if (session('status'))
                        <div role="alert" class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-base-content mb-1.5">
                                E-Mail-Adresse
                            </label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   required autofocus autocomplete="email" placeholder="ihre@email.de"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                            @error('email')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @if (config('app.recaptcha_enabled'))
                            <div class="my-2">{!! htmlFormSnippet() !!}</div>
                            @error('g-recaptcha-response')
                                <p class="text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        @endif

                        <button type="submit"
                                class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
                            Link senden
                        </button>
                    </form>

                </div>

                <p class="text-center text-sm text-base-content/60 mt-6">
                    <a href="{{ route('login') }}" class="font-semibold text-portal-primary-dark hover:underline">
                        Zurück zur Anmeldung
                    </a>
                </p>

            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Passwort
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">zurücksetzen</span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Kein Problem — das passiert jedem. Wir senden Ihnen einen sicheren Link per E-Mail.
            </p>
        </div>
    </x-slot>

    @if (config('app.recaptcha_enabled'))
        @push('tail')
            {!! htmlScriptTagJsApi() !!}
        @endpush
    @endif
</x-layouts.focus>
