<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Vertikale eines Tenants (#14): Zwischenebene fuer Portaltexte zwischen
 * lang/de/portal.php und den Tenant-Overrides.
 *
 * Gespeichert in der Stancl-data-Spalte unter `vertical`, gelesen ueber
 * `tenant('vertical')`. Die Texte je Vertikale liegen unter
 * lang/{locale}/verticals/{vertical}/{group}.php und enthalten nur die Keys,
 * die von der Basis abweichen. Die Vorgabe hat bewusst keine eigene Datei.
 */
final class TenantVertical
{
    public const ATTRIBUTE = 'vertical';

    public const HANDWERK = 'handwerk';

    public const GESUNDHEIT = 'gesundheit';

    public const DEFAULT = self::HANDWERK;

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::HANDWERK => 'Handwerk & Dienstleistung',
            self::GESUNDHEIT => 'Gesundheit (Arzt, Zahnarzt, Apotheke, Tierarzt)',
        ];
    }

    /**
     * Unbekannte oder leere Werte fallen auf die Vorgabe zurueck.
     */
    public static function resolve(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::options())
            ? $value
            : self::DEFAULT;
    }
}
