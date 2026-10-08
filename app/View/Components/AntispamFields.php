<?php

declare(strict_types=1);

namespace App\View\Components;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\HoneypotField;
use App\AntiSpam\Support\TimingToken;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-antispam-fields /> — Honeypot und Zeitstempel am Formular (#8).
 *
 * Gehoert an JEDES oeffentliche Formular, das etwas anlegt. Geprueft wird
 * nicht hier, sondern in App\AntiSpam\SpamGuard (still) oder in den Rules
 * unter App\AntiSpam\Rules (sichtbar) — dieselbe Trennung wie bei
 * <x-turnstile /> und TurnstileRule (docs/turnstile.md §2).
 *
 * Klassisches POST-Formular: ohne Attribut.
 *
 *     <form method="POST" action="{{ route('register') }}">
 *
 *         @csrf
 *         <x-antispam-fields />
 *
 * Livewire: die Werte muessen in Properties landen, sonst sieht die Komponente
 * sie beim Absenden nicht. Dafuer gibt es
 * App\AntiSpam\Concerns\InteractsWithAntiSpam; `wire` schaltet die Bindung an.
 *
 *     <x-antispam-fields wire />
 *
 * Rendert nichts, wenn das Modul aus ist (antispam.enabled) — dann ist auch
 * kein Feld im DOM, an dem sich ein Bot orientieren koennte.
 */
class AntispamFields extends Component
{
    /** Name des Honeypots; je Session zufaellig. */
    public string $honeypotField;

    /** Name des Zeitstempel-Feldes; immer gleich, der Wert ist verschluesselt. */
    public string $timeField = TimingToken::FIELD;

    /** Verschluesselter Zeitstempel des Renderns. */
    public string $timeToken;

    public bool $honeypotEnabled;

    public bool $timingEnabled;

    public function __construct(
        /**
         * Livewire: an die Properties aus InteractsWithAntiSpam binden statt
         * blanke Inputs zu rendern.
         */
        public bool $wire = false,
    ) {
        $this->honeypotField = HoneypotField::name();
        $this->timeToken = TimingToken::issue();
        $this->honeypotEnabled = AntiSpamConfig::bool('honeypot.enabled', true);
        $this->timingEnabled = AntiSpamConfig::bool('timing.enabled', true);
    }

    /** Modul aus heisst: kein Feld im DOM. */
    public function shouldRender(): bool
    {
        return AntiSpamConfig::enabled() && ($this->honeypotEnabled || $this->timingEnabled);
    }

    public function render(): View
    {
        return view('components.antispam-fields');
    }
}
