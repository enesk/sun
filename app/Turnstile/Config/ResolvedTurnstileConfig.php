<?php

declare(strict_types=1);

namespace App\Turnstile\Config;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;

/**
 * Die fertig aufgeloeste Konfiguration fuer genau eine Aktion und genau einen
 * Portalkontext (#3). Unveraenderlich: alle Rangfolgen sind bereits entschieden,
 * niemand muss mehr nach config() oder in die Tenant-Zeile greifen.
 *
 * Erzeugt ausschliesslich von TurnstileConfigResolver.
 */
final class ResolvedTurnstileConfig
{
    public const FAIL_MODE_OPEN = 'open';

    public const FAIL_MODE_CLOSED = 'closed';

    public function __construct(
        public readonly TurnstileAction $action,
        public readonly ?int $tenantId,
        public readonly bool $enabled,
        public readonly TurnstileMode $mode,
        public readonly string $siteKey,
        public readonly string $secretKey,
        public readonly string $failMode,
        public readonly bool $usesTestKeys,
        /** Widget-Gruppe nach docs/turnstile.md Abschnitt 8 (#14). */
        public readonly string $widgetGroup = 'A',
    ) {}

    /** Darf die Anfrage bei einem Siteverify-Fehler weiterlaufen? */
    public function failsOpen(): bool
    {
        return $this->failMode !== self::FAIL_MODE_CLOSED;
    }

    /** Braucht die Seite Platz im Layout (Blade-Komponente, #5)? */
    public function rendersVisibleWidget(): bool
    {
        return $this->enabled && $this->mode->rendersVisibleWidget();
    }

    /**
     * Fuer das Log und die Fehlersuche — ohne Secret, bewusst nur der Zustand.
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'action' => $this->action->value,
            'tenant_id' => $this->tenantId,
            'enabled' => $this->enabled,
            'mode' => $this->mode->value,
            'fail_mode' => $this->failMode,
            'secret' => $this->secretKey !== '' ? 'gesetzt' : 'nicht gesetzt',
            'test_keys' => $this->usesTestKeys,
            'widget_group' => $this->widgetGroup,
        ];
    }
}
