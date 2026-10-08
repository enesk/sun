<?php

namespace App\Validator;

use App\AntiSpam\Rules\NotDisposableEmailRule;
use App\Turnstile\Enums\TurnstileAction;
use Illuminate\Support\Facades\Validator;

class RegisterValidator
{
    public function validate(array $fields, bool $passwordConfirmed = true)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            // NotDisposableEmailRule (#8) haengt hier und nicht im Controller:
            // jede Kontoanlage laeuft durch diesen Validator — /register, die
            // OTP-Registrierung und die Checkout-Formulare. So ist auch ein
            // neuer Registrierungsweg von selbst geschuetzt. Die Regel weist
            // SICHTBAR ab (anders als Honeypot und Timing): sie kann einen
            // echten Menschen treffen, und der soll den Grund erfahren.
            'email' => ['required', 'string', 'email', 'max:255', 'unique:central.users', new NotDisposableEmailRule(TurnstileAction::Registration)],
        ];

        if (! config('app.otp_login_enabled', false)) {
            $rules['password'] = ['required', 'string', 'min:8'];

            if ($passwordConfirmed) {
                $rules['password'][] = 'confirmed';
            }
        }

        if (config('app.recaptcha_enabled')) {
            $rules[recaptchaFieldName()] = recaptchaRuleName();
        }

        return Validator::make($fields, $rules);
    }
}
