<?php

declare(strict_types=1);

namespace App\AntiSpam\Rules;

use App\AntiSpam\DisposableDomainList;
use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\AntiSpamLogger;
use App\AntiSpam\Support\SpamCode;
use App\Turnstile\Enums\TurnstileAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keine Wegwerf-E-Mail-Adresse (#8).
 *
 *     'email' => ['required', 'email', new NotDisposableEmailRule(TurnstileAction::Registration)]
 *
 * Diese Regel weist SICHTBAR ab — anders als Honeypot und Timing. Sie kann
 * einen echten Menschen treffen, der aus Gewohnheit eine Wegwerfadresse
 * nimmt, und der soll erfahren, warum sein Konto nicht entsteht.
 *
 * NICHT implizit: bei leerer Adresse hat `required` schon gemeldet, und eine
 * zweite Meldung am selben Feld hilft niemandem.
 *
 * Der MX-Check (`antispam.disposable.mx_check`) ist standardmaessig aus: er
 * geht ueber DNS, kostet Zeit im Request und schlaegt bei einem langsamen
 * Resolver falsch an. Ist er an, laeuft er erst NACH der Sperrliste — der
 * billige Vergleich zuerst.
 */
class NotDisposableEmailRule implements ValidationRule
{
    public function __construct(
        private readonly TurnstileAction $action = TurnstileAction::Registration,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! AntiSpamConfig::enabled() || ! AntiSpamConfig::bool('disposable.enabled', true)) {
            return;
        }

        $email = is_scalar($value) ? trim((string) $value) : '';

        if ($email === '') {
            return;
        }

        $list = app(DisposableDomainList::class);

        if ($list->blocks($email)) {
            $this->reject($email, SpamCode::DISPOSABLE_EMAIL, $fail);

            return;
        }

        if (AntiSpamConfig::bool('disposable.mx_check', false) && ! $this->hasMxRecord($email)) {
            $this->reject($email, SpamCode::NO_MX_RECORD, $fail);
        }
    }

    private function reject(string $email, string $code, Closure $fail): void
    {
        app(AntiSpamLogger::class)->record(
            action: $this->action,
            codes: [$code],
            email: $email,
        );

        $fail(__('antispam.disposable_email'));
    }

    /**
     * Ein MX-Eintrag genuegt; fehlt er, gilt ein A-Eintrag als Zustellweg
     * (RFC 5321 §5.1). Faellt die Abfrage aus, besteht die Pruefung — ein
     * DNS-Problem auf unserer Seite darf keine Registrierung verhindern.
     */
    private function hasMxRecord(string $email): bool
    {
        $domain = DisposableDomainList::domainOf($email);

        if ($domain === null) {
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
