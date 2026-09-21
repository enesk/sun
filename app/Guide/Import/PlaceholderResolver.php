<?php

declare(strict_types=1);

namespace App\Guide\Import;

use App\Guide\Models\TenantGuideSetting;

/**
 * Platzhalter in Fragen, Kategorien und Ueberschriften der Themenlisten.
 *
 * Gespeichert wird immer die kanonische Form {{branch}}, {{branch_plural}},
 * {{year}}; beim Import werden die Kurzformen {branche}, {branchen} usw.
 * darauf umgeschrieben. {{branch}} und {{branch_plural}} ersetzt die
 * Zuweisung aus tenant_guide_settings, {{year}} bleibt stehen und wird erst
 * zur Renderzeit ersetzt (renderYear()).
 */
class PlaceholderResolver
{
    public const BRANCH = '{{branch}}';

    public const BRANCH_PLURAL = '{{branch_plural}}';

    public const YEAR = '{{year}}';

    /**
     * Schreibweisen aus Listen => kanonischer Platzhalter.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'branch' => self::BRANCH,
        'branche' => self::BRANCH,
        'branch_plural' => self::BRANCH_PLURAL,
        'branchen' => self::BRANCH_PLURAL,
        'branche_plural' => self::BRANCH_PLURAL,
        'year' => self::YEAR,
        'jahr' => self::YEAR,
    ];

    private const PATTERN = '/\{\{?\s*([a-zA-Z_äöüÄÖÜ]+)\s*\}?\}/u';

    /**
     * Schreibt bekannte Platzhalter in die kanonische Form um.
     */
    public function canonicalize(string $text): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => self::ALIASES[mb_strtolower($match[1])] ?? $match[0],
            $text,
        );
    }

    /**
     * Platzhalter, die weder bekannt noch aufloesbar sind.
     *
     * @return array<int, string>
     */
    public function unknown(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER);

        $unknown = [];

        foreach ($matches as $match) {
            if (! isset(self::ALIASES[mb_strtolower($match[1])])) {
                $unknown[] = $match[0];
            }
        }

        return array_values(array_unique($unknown));
    }

    /**
     * Ersetzt die Branchen-Platzhalter des Portals; {{year}} bleibt stehen.
     *
     * @throws MissingPlaceholderValue wenn das Portal keinen Wert fuer einen verwendeten Platzhalter hat
     */
    public function resolveForTenant(string $text, TenantGuideSetting $settings): string
    {
        $values = [
            self::BRANCH => trim((string) $settings->branch),
            self::BRANCH_PLURAL => trim((string) $settings->branch_plural),
        ];

        foreach ($values as $placeholder => $value) {
            if (! str_contains($text, $placeholder)) {
                continue;
            }

            if ($value === '') {
                $column = $placeholder === self::BRANCH ? 'branch' : 'branch_plural';

                throw new MissingPlaceholderValue("tenant_guide_settings.{$column} ist leer, {$placeholder} kann nicht ersetzt werden.");
            }

            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }

    /**
     * Ersetzt {{year}} zur Renderzeit (Titel, Ueberschriften).
     */
    public function renderYear(string $text, ?int $year = null): string
    {
        return str_replace(self::YEAR, (string) ($year ?? (int) now()->format('Y')), $text);
    }

    /**
     * Entfernt {{year}} fuer Slug und Duplikaterkennung.
     */
    public function withoutYear(string $text): string
    {
        return str_replace(self::YEAR, ' ', $text);
    }
}
