<?php

declare(strict_types=1);

namespace App\Ads\Enums;

/**
 * Geraeteklasse eines Ankers oder einer Belegung (docs/ad-slots.md, 3.2).
 *
 * Es gibt bewusst nur zwei echte Klassen plus ALL: die Grenze ist der
 * lg-Haltepunkt (1024 px) des Themes sun-v2, an dem die Seitenspalten
 * erscheinen. "tablet" aus dem alten System (ad_slots je Tenant-DB) faellt
 * weg — es hat nie eine eigene Anzeigengroesse ausgeloest, nur einen
 * dritten Sichtbarkeitsschalter.
 */
enum AdDevice: string
{
    case MOBILE = 'mobile';
    case DESKTOP = 'desktop';
    case ALL = 'all';

    public function label(): string
    {
        return match ($this) {
            self::MOBILE => __('Nur mobil'),
            self::DESKTOP => __('Nur Desktop'),
            self::ALL => __('Mobil und Desktop'),
        };
    }

    /**
     * Tailwind-Klassen, die den Container auf diese Geraeteklasse begrenzen.
     * Die Grenze ist lg (1024 px), wie die Seitenspalten im Theme sun-v2.
     */
    public function visibilityClass(): string
    {
        return match ($this) {
            self::MOBILE => 'lg:hidden',
            self::DESKTOP => 'hidden lg:block',
            self::ALL => '',
        };
    }

    /**
     * Darf eine Belegung mit dieser Geraetewahl in einem Anker laufen, der
     * nur $slot zulaesst? ALL im Anker erlaubt jede Wahl, ein auf eine
     * Geraeteklasse festgelegter Anker nur sich selbst oder ALL.
     */
    public function fitsInto(self $slot): bool
    {
        return $slot === self::ALL || $this === self::ALL || $this === $slot;
    }

    /**
     * Schnittmenge aus Anker-Erlaubnis und Wunsch der Belegung. Gibt die
     * Geraeteklasse zurueck, mit der tatsaechlich ausgespielt wird.
     */
    public function narrowTo(self $slot): self
    {
        if ($slot === self::ALL) {
            return $this;
        }

        return $slot;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
