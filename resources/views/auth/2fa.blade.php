<x-layouts.focus>
    <x-slot name="left">
        <div class="flex-1 flex flex-col justify-center items-center px-4 py-8 md:px-10">
            <div class="w-full max-w-md">

                <div class="md:hidden text-center mb-8">
                    <h1 class="text-2xl font-bold text-portal-primary-dark">Zwei-Faktor-Authentifizierung</h1>
                </div>

                <div class="glass p-6 md:p-8" style="border-radius: var(--portal-radius-lg, 0.75rem);">

                    {{-- Shield Icon --}}
                    <div class="flex justify-center mb-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1);">
                            <svg class="w-8 h-8 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-base-content text-center mb-2">
                        Verifizierung
                    </h2>
                    <p class="text-sm text-base-content/60 text-center mb-6">
                        Öffnen Sie Ihre Authenticator-App und geben Sie den Code ein.
                    </p>

                    <form method="POST" class="space-y-4">
                        @csrf

                        <div>
                            <label for="{{ $input }}" class="block text-sm font-medium text-base-content mb-1.5">
                                Authentifizierungscode
                            </label>
                            <input id="{{ $input }}" type="text" name="{{ $input }}"
                                   required autofocus minlength="6" placeholder="123456"
                                   class="w-full px-4 py-3 rounded-lg border border-base-300 bg-white text-base-content placeholder-base-content/40 text-sm text-center text-2xl tracking-[0.5em] font-mono
                                          focus:outline-none focus:ring-2 focus:border-transparent transition-shadow duration-200"
                                   style="--tw-ring-color: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.3);">
                        </div>

                        @if($errors->isNotEmpty())
                            @foreach ($errors->all() as $error)
                                <p class="text-xs text-red-600 text-center" role="alert">{{ $error }}</p>
                            @endforeach
                        @endif

                        <p class="text-xs text-base-content/50 text-center">
                            Falls Sie keinen Zugang zu Ihrem Gerät haben, verwenden Sie einen Wiederherstellungscode.
                        </p>

                        <button type="submit"
                                class="w-full btn-portal py-3 text-sm font-semibold rounded-lg transition-all duration-200 hover:shadow-lg touch-target">
                            Verifizieren
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="right">
        <div class="px-10 lg:px-14">
            <h1 class="text-3xl lg:text-4xl font-bold text-white leading-tight">
                Zusätzliche
                <span class="block mt-1" style="color: var(--portal-accent, #F59E0B);">Sicherheit</span>
            </h1>
            <p class="mt-4 text-white/80 text-lg leading-relaxed">
                Ihr Konto ist durch Zwei-Faktor-Authentifizierung geschützt.
            </p>
        </div>
    </x-slot>
</x-layouts.focus>
