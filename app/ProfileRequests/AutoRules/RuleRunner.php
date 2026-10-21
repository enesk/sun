<?php

declare(strict_types=1);

namespace App\ProfileRequests\AutoRules;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Das Regelwerk der Auto-Erledigung (SUN-RQ #9).
 *
 * Laeuft nach jeder erfolgreichen Verifikation
 * ({@see \App\ProfileRequests\Verification\RequestVerifier::markVerified()})
 * und nimmt die **erste** Regel aus `profile-requests.auto.rules`, deren
 * `appliesTo()` zutrifft. Greift keine, bleibt der Vorgang auf `verified`
 * und wartet im Panel (#7) — das ist der Normalfall und kein Fehler.
 *
 * Zwei Dinge sind hier bewusst hart:
 *
 *  * **Notschalter.** Ist `profile-requests.auto_disabled` gesetzt, laeuft
 *    keine Regel. Der Vorgang bekommt eine Protokollzeile, damit spaeter
 *    nachvollziehbar ist, warum er liegengeblieben ist.
 *  * **Jede Ausnahme bleibt hier.** Eine gescheiterte Regel darf die
 *    Verifikation nicht mitreissen — der Betrieb hat bestaetigt, und das
 *    ist festzuhalten, auch wenn die Automatik danach stolpert. Der Fehler
 *    steht im Log und in `request_events`.
 */
class RuleRunner
{
    /** Name der Regel, die gegriffen hat, oder null. */
    public function run(ProfileRequest $request): ?string
    {
        if ($this->isDisabled()) {
            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Auto-Regeln sind abgeschaltet — Vorgang bleibt zur Freigabe',
                'config' => 'profile-requests.auto_disabled',
            ]);

            return null;
        }

        foreach ($this->rules() as $rule) {
            try {
                if (! $rule->appliesTo($request)) {
                    continue;
                }

                $gelaufen = $rule->run($request);
            } catch (Throwable $exception) {
                Log::warning('profile-requests: Auto-Regel fehlgeschlagen', [
                    'request_id' => $request->getKey(),
                    'rule' => $rule->key(),
                    'exception' => $exception->getMessage(),
                ]);

                $request->recordEvent(RequestEventType::NOTE, [
                    'note' => 'Auto-Regel fehlgeschlagen — Vorgang bleibt zur Freigabe',
                    'rule' => $rule->key(),
                    'fehler' => Str::limit($exception->getMessage(), 300),
                ]);

                return null;
            }

            // Die zustaendige Regel hat entschieden — auch wenn sie den
            // Vorgang zur Freigabe liegengelassen hat. Eine zweite Regel
            // ueber denselben Vorgang laufen zu lassen hiesse, dieselbe
            // Entscheidung zweimal zu treffen.
            return $gelaufen ? $rule->key() : null;
        }

        return null;
    }

    public function isDisabled(): bool
    {
        return (bool) config('profile-requests.auto_disabled', false);
    }

    /**
     * Die konfigurierten Regeln in Pruefreihenfolge. Ein Eintrag, der keine
     * {@see AutoRule} ist, wird uebersprungen und geloggt — eine kaputte
     * Konfiguration soll die Inbox nicht anhalten.
     *
     * @return list<AutoRule>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ((array) config('profile-requests.auto.rules', []) as $class) {
            if (! is_string($class) || ! is_a($class, AutoRule::class, true)) {
                Log::warning('profile-requests: ungültige Auto-Regel in der Konfiguration', [
                    'eintrag' => is_string($class) ? $class : gettype($class),
                ]);

                continue;
            }

            $rules[] = app($class);
        }

        return $rules;
    }
}
