<?php

declare(strict_types=1);

namespace App\AntiSpam\Rules;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\AntiSpamLogger;
use App\AntiSpam\Support\HoneypotField;
use App\AntiSpam\Support\SpamCode;
use App\Turnstile\Enums\TurnstileAction;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Das unsichtbare Feld muss leer bleiben (#8).
 *
 *     $honeypot = HoneypotField::name();
 *     $this->validate([$honeypot => [new HoneypotRule(TurnstileAction::Registration)]]);
 *
 * `$implicit = true` ist Pflicht: Laravel ruft eine Objekt-Rule bei leerem
 * Wert gar nicht auf (Validator::presentOrRuleIsImplicit) — und leer ist hier
 * der Normalfall. Ohne das Flag wuerde die Regel nur greifen, wenn sie es
 * nicht muss.
 *
 * ACHTUNG: wer einen Treffer STILL abweisen will (Erfolgsmeldung ohne
 * Datensatz, so verlangt es #8), nimmt nicht diese Rule, sondern
 * {@see \App\AntiSpam\SpamGuard} — eine Validierungsmeldung verraet dem Bot,
 * woran er gescheitert ist. Die Rule ist fuer Formulare, die den Treffer
 * sichtbar machen duerfen, und fuer die Vollstaendigkeit des Rule-Sets.
 *
 * Geloggt wird in beiden Faellen genau eine Zeile in turnstile_verifications
 * mit outcome=failed und error_codes ['honeypot'].
 */
class HoneypotRule implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly TurnstileAction $action = TurnstileAction::Registration,
        private readonly ?string $emailField = 'email',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! AntiSpamConfig::enabled() || ! AntiSpamConfig::bool('honeypot.enabled', true)) {
            return;
        }

        if (! HoneypotField::tripped($value)) {
            return;
        }

        app(AntiSpamLogger::class)->record(
            action: $this->action,
            codes: [SpamCode::HONEYPOT],
            email: $this->email(),
        );

        $fail(__('antispam.rejected'));
    }

    private function email(): ?string
    {
        if ($this->emailField === null) {
            return null;
        }

        $email = data_get($this->data, $this->emailField);

        return is_string($email) && $email !== '' ? $email : null;
    }
}
