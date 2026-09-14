<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Die 16 Bundeslaender in der Schreibweise von cities.administrative_area_level_1 (#18).
 *
 * Die Spalte fuehrt den deutschen Klarnamen, wie ihn Google Places mit
 * languageCode 'de' liefert ("Baden-Württemberg", "Thüringen"). Der
 * StateCatalog der Content-Pipeline arbeitet mit ISO-Codes und ASCII-Namen
 * aus der Konfiguration und taugt deshalb nicht als Werteliste fuer die Spalte.
 */
final class GermanState
{
    public const NAMES = [
        'Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg',
        'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen',
        'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen',
    ];

    /**
     * Klarname des Bundeslands, wenn der Wert eines ist — auch in abweichender
     * Schreibweise ("Baden-Wuerttemberg", "sachsen anhalt"). Regionen wie
     * "Allgäu" oder "North Carolina" ergeben null.
     */
    public static function canonical(?string $value): ?string
    {
        $needle = self::normalize((string) $value);

        if ($needle === '') {
            return null;
        }

        foreach (self::NAMES as $name) {
            if (self::normalize($name) === $needle) {
                return $name;
            }
        }

        return null;
    }

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::NAMES, true);
    }

    private static function normalize(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return trim((string) preg_replace('/[^a-z]+/', ' ', $value));
    }
}
