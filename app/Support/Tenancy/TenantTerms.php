<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Grammatische Branchenbegriffe eines Tenants (#2).
 *
 * Gespeichert in der Stancl-data-Spalte unter `terms`, gelesen ueber
 * `tenant('terms')`. Diese Klasse ist die einzige Stelle, die Keys, Labels,
 * Beispielwerte, Defaults und Validierung festlegt — Translator, Filament und
 * Command lesen von hier.
 */
final class TenantTerms
{
    public const ATTRIBUTE = 'terms';

    public const MAX_LENGTH = 60;

    /**
     * Key => [Label, Beispiel, Default]. Die Reihenfolge ist die Formularreihenfolge.
     *
     * @var array<string, array{label: string, example: string, default: string}>
     */
    private const DEFINITIONS = [
        'branche' => [
            'label' => 'Branche (Singular)',
            'example' => 'Elektriker',
            'default' => 'Fachbetrieb',
        ],
        'branche_plural' => [
            'label' => 'Branche (Plural)',
            'example' => 'Elektriker',
            'default' => 'Fachbetriebe',
        ],
        'branche_akk' => [
            'label' => 'Branche (Akkusativ mit Artikel)',
            'example' => 'einen Elektriker',
            'default' => 'einen Fachbetrieb',
        ],
        'betrieb' => [
            'label' => 'Betrieb (Singular)',
            'example' => 'Elektrobetrieb',
            'default' => 'Betrieb',
        ],
        'betrieb_plural' => [
            'label' => 'Betrieb (Plural)',
            'example' => 'Elektrobetriebe',
            'default' => 'Betriebe',
        ],
        'portal' => [
            'label' => 'Portalname',
            'example' => 'Elektrikerportal',
            'default' => 'Portal',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(fn (array $definition) => $definition['label'], self::DEFINITIONS);
    }

    public static function label(string $key): string
    {
        return self::DEFINITIONS[$key]['label'] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    public static function examples(): array
    {
        return array_map(fn (array $definition) => $definition['example'], self::DEFINITIONS);
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $definition) => $definition['default'], self::DEFINITIONS);
    }

    /**
     * Legt gespeicherte Werte ueber die Defaults. Unbekannte Keys fallen weg,
     * leere oder nicht-skalare Werte greifen auf den Default zurueck — so
     * erscheint nie ein roher Platzhalter wie `:branche` im Frontend.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, string>  $fallbacks  abweichende Defaults, z. B. `portal` => Tenant-Name
     * @return array<string, string>
     */
    public static function resolve(?array $stored, array $fallbacks = []): array
    {
        $terms = [];

        foreach (self::defaults() as $key => $default) {
            $value = $stored[$key] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : '';

            if ($value === '') {
                $value = trim((string) ($fallbacks[$key] ?? '')) ?: $default;
            }

            $terms[$key] = $value;
        }

        return $terms;
    }

    /**
     * Regeln fuer ein Array unter `$attribute` (Vorgabe `terms`), z. B. fuer
     * Validator::make($data, TenantTerms::rules()).
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $attribute = self::ATTRIBUTE): array
    {
        $rules = [$attribute => ['required', 'array:'.implode(',', self::keys())]];

        foreach (self::keys() as $key) {
            $rules["{$attribute}.{$key}"] = self::rulesFor();
        }

        return $rules;
    }

    /**
     * Regeln fuer einen einzelnen Begriff: Pflicht, max. 60 Zeichen, kein HTML.
     * Spitze Klammern sind in einem Branchenbegriff nie sinnvoll und werden
     * deshalb pauschal abgelehnt.
     *
     * @return list<string>
     */
    public static function rulesFor(): array
    {
        return ['required', 'string', 'max:'.self::MAX_LENGTH, 'not_regex:/[<>]/'];
    }
}
