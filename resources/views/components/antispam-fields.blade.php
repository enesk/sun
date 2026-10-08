{{--
    Honeypot und Zeitstempel (#8). Logik: App\View\Components\AntispamFields.
    Geprueft wird in App\AntiSpam\SpamGuard bzw. App\AntiSpam\Rules.

    Versteckt wird per sr-only (position:absolute, 1x1, clip) — bewusst NICHT
    display:none und nicht hidden: ein Teil der Bots prueft genau darauf und
    laesst solche Felder dann leer. aria-hidden und tabindex=-1 halten das Feld
    aus Tastaturweg und Screenreader heraus, der Fehlbedienung wegen.

    autocomplete="off" ist Pflicht: ohne das traegt der Browser des Besuchers
    seine gespeicherte Adresse ein und sperrt einen echten Menschen aus.

    In Livewire (wire) wird nur der Honeypot gebunden; der Zeitstempel lebt in
    der Property aus App\AntiSpam\Concerns\InteractsWithAntiSpam und braucht
    keinen Umweg ueber das DOM.
--}}
@if($honeypotEnabled)
    <div class="sr-only" aria-hidden="true">
        <label for="{{ $honeypotField }}">{{ __('antispam.honeypot_label') }}</label>
        <input
            type="text"
            id="{{ $honeypotField }}"
            name="{{ $honeypotField }}"
            value=""
            autocomplete="off"
            tabindex="-1"
            @if($wire) wire:model="antispamHoneypot" @endif
        >
    </div>
@endif

@if($timingEnabled && ! $wire)
    <input type="hidden" name="{{ $timeField }}" value="{{ $timeToken }}" autocomplete="off">
@endif
