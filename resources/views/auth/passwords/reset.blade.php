<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">Neues Passwort festlegen</h1>
                </div>

                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    <h2 class="text-xl font-semibold text-base-content mb-1 hidden md:block">Neues Passwort</h2>
                    <p class="text-sm text-base-content/60 mb-6 hidden md:block">Wählen Sie ein sicheres Passwort für Ihr Konto.</p>

                    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="block text-sm font-medium text-base-content mb-1.5">E-Mail-Adresse</label>
                            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}"
                                   required autofocus autocomplete="email"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content text-sm
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                            @error('email')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-base-content mb-1.5">Neues Passwort</label>
                            <input id="password" type="password" name="password" required autocomplete="new-password"
                                   placeholder="Mindestens 8 Zeichen"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                            @error('password')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-base-content mb-1.5">Passwort bestätigen</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                   autocomplete="new-password" placeholder="Passwort wiederholen"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                        </div>

                        <button type="submit"
                                class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
                            Passwort zurücksetzen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Fast geschafft!
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Wählen Sie ein neues Passwort und Sie sind sofort wieder drin.
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
