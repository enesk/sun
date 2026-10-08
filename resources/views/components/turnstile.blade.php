{{--
    Cloudflare Turnstile am Formular (#5). Logik: App\Turnstile\View\Components\Turnstile,
    Verhalten im Browser: resources/js/turnstile.js. Geprueft wird nichts hier,
    sondern in TurnstileRule (#4).

    Der Container traegt wire:ignore: das Widget wird explizit gerendert und
    darf von einem Livewire-Re-Render nicht ersetzt werden. Der Hidden-Input
    liegt mit im ignorierten Bereich, damit das Token ein Morph ueberlebt.

    Keine Inline-Handler (CSP ohne unsafe-inline, #11) — alles haengt an
    data-Attributen, genau wie die uebrigen JS-Module des Themes.
--}}
@php
    $feldMitFehler = collect($errorFields())->first(fn (string $feld): bool => $errors->has($feld));
@endphp

<div {{ $attributes->class(['mt-5']) }} data-turnstile-field="{{ $field }}">
    <div
        id="{{ $domId }}"
        wire:ignore
        data-turnstile-root
        data-sitekey="{{ $config->siteKey }}"
        data-action="{{ $config->action->value }}"
        data-mode="{{ $config->mode->value }}"
        data-appearance="{{ $appearance() }}"
        data-size="{{ $size }}"
        data-theme="auto"
        data-language="de"
        data-field="{{ $field }}"
        data-gate="{{ $gatesSubmit() ? 'submit' : 'off' }}"
        @if($wire) data-wire-model="{{ $wire }}" @endif
    >
        {{-- Platz reservieren: der Kasten ist 300x65 und kommt erst mit api.js (CLS) --}}
        <div data-turnstile-widget @class(['max-w-full', 'min-h-[65px]' => $isVisible()])></div>
        {{-- Name immer cf-turnstile-response: so liest die Rule das Token aus einem
             klassischen POST. $field steuert nur, unter welchem Schluessel die
             Meldung erwartet wird (Livewire: die Property). --}}
        <input type="hidden" name="{{ $inputName }}" value="" data-turnstile-input>
    </div>

    {{-- Transparenzhinweis (#11). Link auf die Datenschutzerklaerung des Portals;
         der Satz steht im Platzhaltermuster der uebrigen Formularhinweise, damit
         nur der Linktext ein Anker wird. --}}
    @if($showsNotice())
        <p class="mt-2 text-xs leading-snug text-zinc-500">{!! strtr(e(__('turnstile.notice', ['datenschutz' => '[[datenschutz]]'])), [
            '[[datenschutz]]' => '<a href="'.e(route('portal.datenschutz')).'" class="underline hover:text-zinc-700">'.e(__('turnstile.notice_link')).'</a>',
        ]) !!}</p>
    @endif

    {{-- Feldfehler im Stil der uebrigen Felder, direkt unter dem Widget (kein Toast).
         Steht bewusst ausserhalb von wire:ignore, damit Livewire ihn aktualisiert. --}}
    @if($feldMitFehler !== null)
        <p class="mt-1 text-sm text-red-600" role="alert" data-turnstile-error>{{ $errors->first($feldMitFehler) }}</p>
    @endif
</div>

@once
    @push('scripts')
        {{-- Reihenfolge: erst das Modul (legt window.onTurnstileLoad), dann api.js.
             Beide laufen deferred in Dokumentreihenfolge; laedt das Modul doch
             spaeter, bootet es sich selbst ueber window.turnstile. --}}
        @vite('resources/js/turnstile.js')
        <script src="{{ $scriptUrl() }}" defer></script>
    @endpush
@endonce
