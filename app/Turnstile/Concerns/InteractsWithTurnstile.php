<?php

declare(strict_types=1);

namespace App\Turnstile\Concerns;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
use Illuminate\Validation\ValidationException;

/**
 * Turnstile in einer Livewire-Komponente (#7, #52).
 *
 * In Livewire gibt es kein Request-Feld, aus dem die Rule das Token lesen
 * koennte — es muss in einer Property stehen, und `validate()` loest den
 * Feldnamen gegen die Properties auf. Fehlt sie, bricht Livewire mit
 * "No property found for validation" ab, bevor die Rule ueberhaupt laeuft
 * (so war CompanySignup auf allen Portalen ein 500). Der Trait haelt die
 * Property, damit Ansicht und Komponente nicht auseinanderlaufen koennen:
 *
 *     use InteractsWithTurnstile;
 *     ...
 *     <x-turnstile action="company_listing" wire="turnstileToken" field="turnstileToken" />
 *
 * Beim Absenden als LETZTE Pruefung, erst wenn alle anderen Felder stimmen —
 * Cloudflare nimmt jedes Token nur einmal an:
 *
 *     $this->validateTurnstile(TurnstileAction::CompanyListing);
 */
trait InteractsWithTurnstile
{
    /** Turnstile-Token, gesetzt von <x-turnstile wire="turnstileToken" field="turnstileToken" />. */
    public string $turnstileToken = '';

    /**
     * Token pruefen (Durchsetzung bleibt in {@see TurnstileRule}). Nach einem
     * Fehlschlag wird es weggeraeumt, damit der naechste Versuch nicht noch
     * einmal mit dem schon eingeloesten Token laeuft — das Widget stellt sich
     * im Browser passend dazu neu (resources/js/turnstile.js).
     *
     * @param  string|null  $emailField  Property mit der E-Mail-Adresse fuers Log, null wenn keine im Formular steht.
     *
     * @throws ValidationException
     */
    protected function validateTurnstile(TurnstileAction $action, ?string $emailField = 'email'): void
    {
        try {
            $this->validate([
                'turnstileToken' => [new TurnstileRule($action, $emailField)],
            ]);
        } catch (ValidationException $e) {
            $this->turnstileToken = '';

            throw $e;
        }
    }
}
