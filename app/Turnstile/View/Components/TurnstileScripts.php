<?php

declare(strict_types=1);

namespace App\Turnstile\View\Components;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\View\Components\Concerns\ResolvesTurnstileConfig;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-turnstile-scripts action="company_listing" /> — nur die beiden Skripte (#53).
 *
 * Warum getrennt von <x-turnstile />: das Widget legt seine Skripte über den
 * Stack 'scripts' ab, und der wird nur beim Rendern des Layouts
 * geleert. Erscheint das Widget erst nach einem Livewire-Umlauf — Schritt 2 der
 * Firmeneintragung, ein Dialog, ein aufgeklapptes Formular — dann steht beim
 * ersten Laden der Seite kein @stack mehr bereit: das Markup kommt an,
 * api.js und resources/js/turnstile.js fehlen. Im Browser bleibt der Kasten
 * leer, der Hidden-Input leer, und Cloudflare meldet beim Abschicken
 * `missing-input-response` ohne Siteverify-Aufruf.
 *
 * Darum gehört sie an jeder Stelle, die ihr Widget verzögert einblendet,
 * unbedingt ins Formular — außerhalb der Bedingung, direkt daneben:
 * `<x-turnstile-scripts action="company_listing" />` steht vor der Bedingung,
 * `<x-turnstile ... />` bleibt darin. Vorbild:
 * resources/views/themes/sun-v2/views/livewire/portal/company-signup.blade.php
 *
 * Mehrfach auf einer Seite ist unschädlich: das once in der Ansicht liefert
 * die Skripte genau einmal je Anfrage. Bei ausgeschalteter Aktion rendert die
 * Komponente nichts — Cloudflare wird dann auf der Seite nicht geladen.
 */
class TurnstileScripts extends Component
{
    use ResolvesTurnstileConfig;

    public function __construct(string|TurnstileAction $action)
    {
        $this->resolveTurnstileConfig($action);
    }

    public function render(): View
    {
        return view('components.turnstile-scripts');
    }
}
