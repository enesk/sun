<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">Passwort bestätigen</h1>
                </div>

                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    <h2 class="text-xl font-semibold text-base-content mb-1 hidden md:block">Passwort bestätigen</h2>
                    <p class="text-sm text-base-content/60 mb-6 hidden md:block">Bitte bestätigen Sie Ihr Passwort, um fortzufahren.</p>

                    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="password" class="block text-sm font-medium text-base-content mb-1.5">Passwort</label>
                            <input id="password" type="password" name="password" required autocomplete="current-password"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content text-sm
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                            @error('password')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @if (Route::has('password.request'))
                            <div class="text-right">
                                <a href="{{ route('password.request') }}"
                                   class="text-xs font-medium hover:underline"
                                   style="color: var(--portal-primary-dark, #1E3A5F);">
                                    Passwort vergessen?
                                </a>
                            </div>
                        @endif

                        <button type="submit"
                                class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
                            Bestätigen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Sicherheits-Check
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Bestätigen Sie Ihr Passwort, um auf geschützte Bereiche zuzugreifen.
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
