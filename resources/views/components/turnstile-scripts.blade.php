{{--
    Die beiden Turnstile-Skripte, genau einmal je Anfrage (#53). Logik:
    App\Turnstile\View\Components\TurnstileScripts. Eingebunden von
    <x-turnstile /> selbst und zusätzlich von jedem Formular, das sein Widget
    erst nach einem Livewire-Umlauf einblendet.

    Reihenfolge: erst das Modul (legt window.onTurnstileLoad), dann api.js.
    Beide laufen deferred in Dokumentreihenfolge; lädt das Modul doch später,
    bootet es sich selbst über window.turnstile.
--}}
@once
    @push('scripts')
        @vite('resources/js/turnstile.js')
        <script src="{{ $scriptUrl() }}" defer></script>
    @endpush
@endonce
