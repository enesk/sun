<?php

declare(strict_types=1);

namespace App\Turnstile\Enums;

/**
 * Darstellung des Widgets (#2). Entspricht dem Appearance-/Mode-Begriff von
 * Cloudflare: der Modus wird im Cloudflare-Dashboard je Widget eingestellt,
 * hier steuert er nur, was die Blade-Komponente rendert — sichtbarer Kasten
 * (managed, non_interactive) oder unsichtbar ohne Platzbedarf (invisible).
 *
 * Werte stehen in der Tenant-Konfiguration — nie umbenennen, nur ergaenzen.
 */
enum TurnstileMode: string
{
    /** Sichtbarer Kasten, bei Verdacht mit Interaktion. Vorgabe fuer Konten. */
    case Managed = 'managed';

    /** Sichtbarer Kasten, nie mit Interaktion. */
    case NonInteractive = 'non_interactive';

    /** Kein sichtbares Element; der Hinweistext am Formular bleibt Pflicht (#11). */
    case Invisible = 'invisible';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Fehlende oder unbekannte Werte ergeben Managed — die strengste Variante,
     * damit ein Tippfehler in der Konfiguration niemanden ungeschuetzt laesst.
     */
    public static function resolve(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Managed;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Managed => __('Sichtbar, bei Verdacht mit Aufgabe'),
            self::NonInteractive => __('Sichtbar, ohne Aufgabe'),
            self::Invisible => __('Unsichtbar'),
        };
    }

    /** Braucht die Seite Platz im Layout fuer den Kasten? */
    public function rendersVisibleWidget(): bool
    {
        return $this !== self::Invisible;
    }
}
