<?php

declare(strict_types=1);

namespace App\Turnstile\View\Components;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use App\Turnstile\View\Components\Concerns\ResolvesTurnstileConfig;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-turnstile action="registration" /> — das Widget am Formular (#5).
 *
 * Alles Wirksame kommt aus dem TurnstileConfigResolver (#3): Sitekey, Modus und
 * ob die Aktion ueberhaupt geschuetzt wird. Die Komponente entscheidet nichts
 * selbst und prueft nichts — geprueft wird ausschliesslich in TurnstileRule (#4,
 * docs/turnstile.md §2).
 *
 * Rendert nichts, wenn Modul, Portal oder Aktion aus sind. Das Widget wird
 * explizit gerendert (resources/js/turnstile.js), damit ein Livewire-Re-Render
 * es nicht zerstoert; der Container traegt deshalb wire:ignore.
 *
 * Benutzung:
 *
 *   {{-- klassisches POST-Formular: Hidden-Input cf-turnstile-response --}}
 *   <x-turnstile action="registration" />
 *
 *   {{-- Livewire: Token landet zusaetzlich in der Property --}}
 *   <x-turnstile action="company_listing" wire="turnstileToken" field="turnstileToken" />
 *
 * Blendet das Formular sein Widget erst nach einem Livewire-Umlauf ein (Schritt
 * eines Assistenten, Dialog, aufklappbares Formular), dann gehoert
 * <x-turnstile-scripts /> unbedingt daneben — sonst fehlen die Skripte (#53).
 */
class Turnstile extends Component
{
    use ResolvesTurnstileConfig;

    /** Feldname, den Cloudflare im Formular erwartet und den die Rule prueft. */
    public const FIELD = 'cf-turnstile-response';

    /** Fortlaufend je Anfrage, damit mehrere Formulare eigene DOM-Ids bekommen. */
    private static int $instances = 0;

    public string $domId;

    /** Name des Hidden-Inputs — immer der Feldname von Cloudflare. */
    public string $inputName = self::FIELD;

    public function __construct(
        string|TurnstileAction $action,
        /** Livewire-Property, in die das Token geschrieben wird (null = nur Hidden-Input). */
        public ?string $wire = null,
        /** Feld, unter dem die Fehlermeldung der Rule erwartet wird. */
        public ?string $field = null,
        /** Submit sperren, bis ein Token da ist. Null = bei sichtbarem Widget ja. */
        public ?bool $gate = null,
        /** Cloudflare-Groesse: normal (300 px), flexible oder compact. */
        public string $size = 'normal',
        /** Hinweistext am Formular. Null = ja (#11). */
        public ?bool $notice = null,
    ) {
        $this->resolveTurnstileConfig($action);

        $this->field ??= self::FIELD;
        $this->domId = 'turnstile-'.$this->action->value.(++self::$instances > 1 ? '-'.self::$instances : '');
    }

    /**
     * Felder, unter denen eine Meldung stehen kann: der Cloudflare-Feldname und
     * — bei Livewire — die Property, an der die Rule haengt.
     *
     * @return list<string>
     */
    public function errorFields(): array
    {
        return array_values(array_unique(array_filter([$this->field, $this->wire])));
    }

    /**
     * Transparenzhinweis mit Link auf die Datenschutzerklaerung (#11). Steht
     * unter jedem geschuetzten Formular, auch beim sichtbaren Kasten: die
     * Cloudflare-Marke im Widget ist keine Datenschutzinformation. Mit
     * notice="false" an einer einzelnen Stelle abschaltbar.
     */
    public function showsNotice(): bool
    {
        return $this->notice ?? true;
    }

    /** Cloudflare-Appearance: unsichtbarer Modus zeigt nur bei Interaktion etwas. */
    public function appearance(): string
    {
        return $this->config?->mode === TurnstileMode::Invisible ? 'interaction-only' : 'always';
    }

    /** Braucht der Kasten Platz im Layout (CLS) oder ist er unsichtbar? */
    public function isVisible(): bool
    {
        return $this->config?->rendersVisibleWidget() === true;
    }

    /**
     * Sperrt den Submit, bis ein Token vorliegt. Bei invisible nicht: dort gibt
     * es nichts zu loesen, und ein gesperrter Knopf waere ohne Erklaerung.
     */
    public function gatesSubmit(): bool
    {
        return $this->gate ?? $this->isVisible();
    }

    public function render(): View
    {
        return view('components.turnstile');
    }
}
