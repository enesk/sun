<?php

declare(strict_types=1);

namespace App\Ads\Enums;

/**
 * Lebenszyklus einer Belegung (ad_placements, docs/ad-slots.md, 3.2).
 *
 * draft -> active <-> paused; expired wird nicht gesetzt, sondern
 * errechnet: eine Belegung mit `ends_at` in der Vergangenheit gilt als
 * abgelaufen, egal was in der Spalte steht. Der Resolver liefert nur
 * active-Belegungen innerhalb ihres Zeitraums aus.
 */
enum AdPlacementStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Entwurf'),
            self::ACTIVE => __('Aktiv'),
            self::PAUSED => __('Pausiert'),
            self::EXPIRED => __('Abgelaufen'),
        };
    }

    /**
     * Filament-Farbe des Badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::EXPIRED => 'danger',
        };
    }

    /**
     * Nur dieser Status wird ueberhaupt ausgespielt — der Zeitraum wird
     * zusaetzlich geprueft (AdSlotResolver, SUN-AD-003).
     */
    public function isDeliverable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::ACTIVE],
            self::ACTIVE => [self::PAUSED, self::EXPIRED],
            self::PAUSED => [self::ACTIVE, self::EXPIRED],
            self::EXPIRED => [self::DRAFT],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
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
