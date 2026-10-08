<?php

declare(strict_types=1);

namespace App\AntiSpam;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\AntiSpamLogger;
use App\AntiSpam\Support\HoneypotField;
use App\AntiSpam\Support\SpamCode;
use App\AntiSpam\Support\TimingToken;
use App\Turnstile\Enums\TurnstileAction;

/**
 * Die stillen Pruefungen an einer Stelle (#8).
 *
 * Honeypot und Mindest-Ausfuellzeit werden laut Ticket STILL abgewiesen: der
 * Besucher sieht dieselbe Erfolgsmeldung wie bei einem echten Absenden, es
 * entsteht aber kein Datensatz. Ein Bot soll nicht lernen, woran er
 * gescheitert ist — und genau das wuerde eine Validierungsmeldung verraten.
 *
 * Deshalb laufen diese beiden Pruefungen NICHT ueber den Validator, sondern
 * hier, VOR der Validierung:
 *
 *     if (app(SpamGuard::class)->rejectsSilently(TurnstileAction::Registration, $request->all())) {
 *         return $this->fakeSuccess();   // kein User, kein Eintrag
 *     }
 *
 * Die Rules in App\AntiSpam\Rules bleiben trotzdem bestehen und pruefen
 * dasselbe — fuer Formulare, die den Treffer sichtbar machen duerfen, und als
 * Netz, falls ein Formular den Guard vergisst.
 *
 * Jeder Treffer schreibt genau eine Zeile in turnstile_verifications
 * (outcome=failed, error_codes ['honeypot'] bzw. ['too_fast']).
 */
class SpamGuard
{
    public function __construct(
        private readonly AntiSpamLogger $logger,
    ) {}

    /**
     * Formulardaten pruefen. Gibt true zurueck, wenn der Aufrufer Erfolg
     * vorspielen und nichts anlegen soll.
     *
     * @param  array<string, mixed>  $data
     */
    public function rejectsSilently(TurnstileAction $action, array $data, ?string $email = null): bool
    {
        return $this->rejects(
            action: $action,
            honeypot: HoneypotField::valueIn($data),
            timingToken: $data[TimingToken::FIELD] ?? null,
            email: $email ?? (is_string($data['email'] ?? null) ? $data['email'] : null),
        );
    }

    /**
     * Dasselbe mit einzeln uebergebenen Werten — der Weg fuer Livewire, wo die
     * Werte in Properties stehen und nicht im Request.
     */
    public function rejects(
        TurnstileAction $action,
        mixed $honeypot = null,
        mixed $timingToken = null,
        ?string $email = null,
    ): bool {
        if (! AntiSpamConfig::enabled()) {
            return false;
        }

        $codes = [];

        if (AntiSpamConfig::bool('honeypot.enabled', true) && HoneypotField::tripped($honeypot)) {
            $codes[] = SpamCode::HONEYPOT;
        }

        if (AntiSpamConfig::bool('timing.enabled', true) && TimingToken::tooFast($timingToken)) {
            $codes[] = TimingToken::code($timingToken);
        }

        if ($codes === []) {
            return false;
        }

        $this->logger->record(action: $action, codes: $codes, email: $email);

        return true;
    }
}
