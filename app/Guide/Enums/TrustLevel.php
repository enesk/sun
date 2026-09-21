<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Vertrauensstufe einer Quelle (guide_sources.trust_level).
 *
 * official Behoerden, Gesetzestexte, Kammern, Foerdergeber
 * trade    Fachverbaende, Innungen, Fachpresse der Branche
 * press    allgemeine Presse
 * other    alles uebrige
 */
enum TrustLevel: string
{
    case OFFICIAL = 'official';
    case TRADE = 'trade';
    case PRESS = 'press';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OFFICIAL => __('Amtlich'),
            self::TRADE => __('Fachquelle'),
            self::PRESS => __('Presse'),
            self::OTHER => __('Sonstige'),
        };
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
