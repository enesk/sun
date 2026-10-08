<?php

declare(strict_types=1);

namespace App\Turnstile\View\Components\Concerns;

use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Exceptions\TurnstileNotConfiguredException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Gemeinsame Auflösung für <x-turnstile /> und <x-turnstile-scripts /> (#53).
 *
 * Beide Komponenten entscheiden nichts selbst: Sitekey, Modus und ob die
 * Aktion überhaupt geschützt wird kommen aus dem TurnstileConfigResolver (#3),
 * geprüft wird ausschließlich in TurnstileRule (#4).
 */
trait ResolvesTurnstileConfig
{
    public TurnstileAction $action;

    /** Null = Modul/Portal/Aktion aus oder in Produktion nicht konfiguriert. */
    public ?ResolvedTurnstileConfig $config = null;

    protected function resolveTurnstileConfig(string|TurnstileAction $action): void
    {
        $this->action = $action instanceof TurnstileAction
            ? $action
            : (TurnstileAction::tryFromValue($action) ?? throw new InvalidArgumentException(
                "Unbekannte Turnstile-Aktion [{$action}]."
            ));

        try {
            $this->config = TurnstileConfigResolver::for($this->action);
        } catch (TurnstileNotConfiguredException $e) {
            // Fail-open wie in docs/turnstile.md §5: eine kaputte Konfiguration
            // darf kein Formular unbenutzbar machen. Lautstark wird es in der
            // Rule (#4) und im Alarm (#12), nicht auf der Seite des Besuchers.
            Log::warning('Turnstile: Widget nicht gerendert, Konfiguration unvollstaendig.', [
                'action' => $this->action->value,
                'fehler' => $e->getMessage(),
            ]);
        }
    }

    /** Bei deaktivierter Aktion steht am Formular nichts — auch kein leeres Feld. */
    public function shouldRender(): bool
    {
        return $this->config?->enabled === true;
    }

    /**
     * api.js mit explizitem Rendering. Der Callback-Name ist der, den
     * resources/js/turnstile.js auf window legt.
     */
    public function scriptUrl(): string
    {
        $base = (string) config('turnstile.script_url');

        return $base.(str_contains($base, '?') ? '&' : '?').'render=explicit&onload=onTurnstileLoad';
    }
}
