<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Art eines Laufs (docs/guide-system.md, Abschnitt 3).
 *
 * create    Thema ohne veroeffentlichte Fassung oder force_rewrite nach
 *           erkannter Aenderung: Tiefenrecherche + ganzer Artikel.
 * update    Thema mit veroeffentlichter Fassung: Probe, bei Aenderung
 *           Tiefenrecherche + betroffene Abschnitte.
 * unchanged Ergebnis einer Probe ohne relevante Aenderung; nur das
 *           Geprueft-Datum wird fortgeschrieben.
 */
enum RunMode: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case UNCHANGED = 'unchanged';

    public function label(): string
    {
        return match ($this) {
            self::CREATE => __('Neuanlage'),
            self::UPDATE => __('Aktualisierung'),
            self::UNCHANGED => __('Unverändert'),
        };
    }

    /**
     * Filament-Farbe; nur Statusrollen des Panels, "unchanged" nie grau
     * (design/guide-dashboard.md §2.2).
     */
    public function color(): string
    {
        return match ($this) {
            self::CREATE, self::UPDATE => 'status-published',
            self::UNCHANGED => 'status-unchanged',
        };
    }

    /**
     * Einziger erlaubter Moduswechsel waehrend eines Laufs: Die Probe stuft
     * eine Aktualisierung auf unchanged herab oder (force_rewrite) auf create hoch.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::UPDATE => [self::UNCHANGED, self::CREATE],
            self::CREATE, self::UNCHANGED => [],
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
