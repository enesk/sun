<?php

declare(strict_types=1);

namespace App\AntiSpam\Rules;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\AntiSpamLogger;
use App\AntiSpam\Support\TimingToken;
use App\Turnstile\Enums\TurnstileAction;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mindest-Ausfuellzeit (#8): wer in unter drei Sekunden abschickt, hat das
 * Formular nicht gelesen.
 *
 *     $this->validate([TimingToken::FIELD => [new MinimumFillTimeRule(TurnstileAction::Registration)]]);
 *
 * Geprueft wird der verschluesselte Zeitstempel aus <x-antispam-fields />.
 * `$implicit = true`, weil ein entferntes Feld der interessante Fall ist.
 *
 * Ein abgelaufener Zeitstempel (Tab stand eine Nacht offen) ist KEIN Treffer —
 * siehe TimingToken::tooFast(). Fuer die stille Abweisung gilt dasselbe wie
 * bei {@see HoneypotRule}: dafuer ist {@see \App\AntiSpam\SpamGuard} da.
 */
class MinimumFillTimeRule implements DataAwareRule, ValidationRule
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
        if (! AntiSpamConfig::enabled() || ! AntiSpamConfig::bool('timing.enabled', true)) {
            return;
        }

        if (! TimingToken::tooFast($value)) {
            return;
        }

        app(AntiSpamLogger::class)->record(
            action: $this->action,
            codes: [TimingToken::code($value)],
            email: $this->email(),
        );

        $fail(__('antispam.too_fast'));
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
