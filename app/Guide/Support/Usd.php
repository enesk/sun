<?php

declare(strict_types=1);

namespace App\Guide\Support;

/**
 * Geldbetraege im Ratgeber-Dashboard: USD mit deutschem Zahlenformat,
 * "18,40 USD"; Betraege ueber 0 nie als "0,00 USD" (design/guide-dashboard.md §1.4).
 */
final class Usd
{
    public static function format(float $amount): string
    {
        if ($amount > 0 && $amount < 0.01) {
            return '< 0,01 USD';
        }

        return number_format($amount, 2, ',', '.').' USD';
    }

    /**
     * Schaetzung aus config('guide.estimates'), z. B. estimate('create', 3).
     */
    public static function estimate(string $kind, int $count = 1): float
    {
        return round((float) config("guide.estimates.{$kind}_usd", 0) * max(0, $count), 2);
    }
}
