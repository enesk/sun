<?php

declare(strict_types=1);

namespace App\Guide\Support;

/**
 * Einzeltasten der Pruefung (design/guide-dashboard.md §8.2): J/K naechster/
 * vorheriger Eintrag, F freigeben, S zurueck zum Schreiben, V verwerfen,
 * ? Uebersicht. Nach WCAG 2.1.4 im Nutzermenue abschaltbar (#33).
 *
 * Die Wahl liegt je Browser in einem Cookie, nicht am Konto: sie betrifft
 * die Tastatur des Geraets (Screenreader, Spracheingabe), und es braucht
 * dafuer keine Spalte in users.
 */
final class ReviewShortcuts
{
    public const COOKIE = 'guide_single_keys';

    public const OFF = 'off';

    /**
     * Taste => [Beschriftung in der Uebersicht].
     *
     * @return array<string, string>
     */
    public static function keys(): array
    {
        return [
            'J' => __('Nächster Eintrag (überspringen)'),
            'K' => __('Vorheriger Eintrag'),
            'F' => __('Freigeben'),
            'S' => __('Zurück zum Schreiben …'),
            'V' => __('Verwerfen …'),
            '?' => __('Diese Übersicht'),
        ];
    }

    public static function enabled(): bool
    {
        return request()->cookie(self::COOKIE) !== self::OFF;
    }
}
