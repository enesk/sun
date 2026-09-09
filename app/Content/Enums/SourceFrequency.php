<?php

declare(strict_types=1);

namespace App\Content\Enums;

/**
 * Abrufrhythmus, den ein SourceConnector selbst deklariert (#7).
 *
 * Der Scheduler kennt genau diese vier Faelle; ein neuer Connector waehlt
 * einen davon, der Runner muss dafuer nicht angefasst werden.
 */
enum SourceFrequency: string
{
    case HOURLY = 'hourly';

    case SIX_HOURLY = 'six_hourly';

    case DAILY = 'daily';

    case WEEKLY = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::HOURLY => __('stündlich'),
            self::SIX_HOURLY => __('alle 6 Stunden'),
            self::DAILY => __('täglich'),
            self::WEEKLY => __('wöchentlich'),
        };
    }

    /**
     * Reihenfolge von haeufig nach selten. Grundlage fuer die Regel aus
     * design/content-dashboard.md, §7a: im Panel ist nur gleich oder seltener
     * waehlbar als die Deklaration des Connectors, nie haeufiger.
     */
    public function rank(): int
    {
        return match ($this) {
            self::HOURLY => 0,
            self::SIX_HOURLY => 1,
            self::DAILY => 2,
            self::WEEKLY => 3,
        };
    }

    public function isAtLeastAsRareAs(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * Die Deklaration und alle selteneren Werte, aufsteigend — genau die
     * Auswahlliste des Formulars "Einstellen".
     *
     * @return list<self>
     */
    public static function fromDeclared(self $declared): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $case): bool => $case->isAtLeastAsRareAs($declared),
        ));
    }

    /**
     * Wie lange ein Lauf dieser Frequenz mindestens zurueckliegen muss, bevor
     * er erneut faellig ist. Puffer von 5 Minuten gegen Scheduler-Drift.
     */
    public function minimumIntervalMinutes(): int
    {
        return match ($this) {
            self::HOURLY => 55,
            self::SIX_HOURLY => 355,
            self::DAILY => 1435,
            self::WEEKLY => 10075,
        };
    }
}
