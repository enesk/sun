<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
use App\Turnstile\View\Components\Turnstile;
use App\Validator\RegisterValidator;
use Illuminate\Validation\Validator;

/**
 * Turnstile-Pflicht fuer jede Kontoanlage (#6, docs/turnstile.md Abschnitt 11).
 *
 * SaaSykit validiert jede Registrierung ueber App\Validator\RegisterValidator —
 * /register (RegisterController), die OTP-Registrierung und alle vier
 * Checkout-Formulare. Diese Unterklasse haengt dort die TurnstileRule an und
 * ist in App\Turnstile\TurnstileServiceProvider an den Container gebunden.
 * So bleibt der SaaSykit-Validator unberuehrt und ein neuer Registrierungsweg,
 * der denselben Validator benutzt, ist automatisch geschuetzt.
 *
 * Der Feldname ist immer der Hidden-Input von Cloudflare
 * (Turnstile::FIELD). Bei einem klassischen POST steckt er von selbst in
 * $fields; Livewire-Formulare schreiben ihre Property dort hinein (siehe
 * OneTimePasswordRegistration und CheckoutForm).
 *
 * Die Rule ist implizit: ein fehlendes Feld faellt durch, ein stillschweigend
 * weggelassenes Token oeffnet also keinen Weg.
 */
class TurnstileRegisterValidator extends RegisterValidator
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function validate(array $fields, bool $passwordConfirmed = true)
    {
        $validator = parent::validate($fields, $passwordConfirmed);

        if ($validator instanceof Validator) {
            $validator->addRules([
                Turnstile::FIELD => [new TurnstileRule(TurnstileAction::Registration)],
            ]);
        }

        return $validator;
    }
}
