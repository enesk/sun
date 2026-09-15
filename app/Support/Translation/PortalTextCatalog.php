<?php

declare(strict_types=1);

namespace App\Support\Translation;

use App\Support\Tenancy\TenantTerms;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Katalog der ueberschreibbaren Portaltexte fuer die Pflege im Admin-Panel (#13).
 *
 * Quelle ist allein lang/de/portal.php; Keys stehen ohne Gruppenpraefix, genau
 * wie in tenant_texts.key. Die Klasse liefert Standardtext, Platzhalter,
 * Warnungen und die Vorschau — Texte laufen dabei bewusst NICHT durch __(),
 * sonst setzt der TenantTranslator die Branchenbegriffe schon in die Anzeige ein.
 */
final class PortalTextCatalog
{
    public const LOCALE = 'de';

    public const GROUP = 'portal';

    /**
     * Rechtlich relevante Einwilligungstexte: Overrides nur durch die Admin-Rolle.
     * request.contact.more_businesses ist in portal.php als Einwilligung markiert.
     */
    public const PROTECTED_PREFIXES = [
        'request.optin',
        'request.contact.more_businesses',
    ];

    public const ADMIN_ROLE = 'admin';

    /**
     * Beispielwerte fuer die Platzhalter, die Views selbst uebergeben
     * (siehe Kopf von lang/de/portal.php).
     */
    private const CONTEXT_EXAMPLES = [
        'firma' => 'Muster GmbH',
        'stadt' => 'Rastatt',
        'anzahl' => '1.234',
        'begriff' => 'Notdienst',
        'ort' => '76437 Rastatt',
        'km' => '25',
        'wertung' => '4,7',
        'zeit' => '17:00',
        'tag' => 'Mo',
        'schritt' => '2',
        'minuten' => '5',
        'jahr' => '2026',
        'name' => 'Alex',
        'telefon' => '07222 123456',
        'sortierung' => 'Beste Bewertung',
        'ueberschrift' => 'Überschrift der Seite',
        'agb' => 'AGB',
        'datenschutz' => 'Datenschutz',
    ];

    /** @var array<string, string>|null */
    private static ?array $defaults = null;

    /**
     * @return array<string, string> Key => Standardtext
     */
    public static function defaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        // Nicht lang_path(): solange resources/lang existiert, zeigt es dorthin.
        // lang/ bindet der AppServiceProvider als zusaetzlichen Pfad ein.
        $lines = require base_path('lang/'.self::LOCALE.'/'.self::GROUP.'.php');

        return self::$defaults = array_filter(Arr::dot($lines), 'is_string');
    }

    public static function default(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        return self::defaults()[$key] ?? null;
    }

    public static function exists(?string $key): bool
    {
        return self::default($key) !== null;
    }

    /**
     * Select-Optionen gruppiert nach Seitentyp (erstes Key-Segment).
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $options = [];

        foreach (self::defaults() as $key => $text) {
            $options[Str::before($key, '.')][$key] = $key.' – '.Str::limit($text, 60);
        }

        return $options;
    }

    public static function isProtected(?string $key): bool
    {
        return $key !== null && Str::startsWith($key, self::PROTECTED_PREFIXES);
    }

    /**
     * Platzhalter eines Textes, kleingeschrieben (:Branche und :BRANCHE zaehlen als :branche).
     *
     * @return list<string>
     */
    public static function placeholders(?string $text): array
    {
        preg_match_all('/:([A-Za-z][A-Za-z_]*)/', (string) $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Branchenbegriffe sind ueberall verfuegbar, Kontextplatzhalter nur dort,
     * wo die View sie uebergibt — also wenn der Standardtext sie enthaelt.
     *
     * @return list<string>
     */
    public static function availablePlaceholders(?string $key): array
    {
        return array_values(array_unique([
            ...TenantTerms::keys(),
            ...self::placeholders(self::default($key)),
        ]));
    }

    public static function containsHtml(?string $value): bool
    {
        return preg_match('/[<>]/', (string) $value) === 1;
    }

    /**
     * @return list<string>
     */
    public static function warnings(?string $key, ?string $value): array
    {
        if (! self::exists($key) || blank($value)) {
            return [];
        }

        $used = self::placeholders($value);
        $warnings = [];

        $removed = array_diff(self::placeholders(self::default($key)), $used);
        $unknown = array_diff($used, self::availablePlaceholders($key));

        if ($removed !== []) {
            $warnings[] = 'Der Override entfernt Platzhalter aus dem Standardtext: '.self::format($removed);
        }

        if ($unknown !== []) {
            $warnings[] = 'Der Override enthält unbekannte Platzhalter: '.self::format($unknown);
        }

        return $warnings;
    }

    /**
     * Setzt Begriffe und Beispielwerte nach den Regeln des Translators ein
     * (:wert, :Wert, :WERT; laengster Treffer zuerst).
     *
     * @param  array<string, string>  $terms
     */
    public static function preview(?string $value, array $terms): string
    {
        $replacements = [];

        foreach ($terms + self::CONTEXT_EXAMPLES as $name => $replacement) {
            $replacements[':'.$name] = $replacement;
            $replacements[':'.Str::ucfirst($name)] = Str::ucfirst($replacement);
            $replacements[':'.Str::upper($name)] = Str::upper($replacement);
        }

        return strtr((string) $value, $replacements);
    }

    /**
     * @param  array<int, string>  $placeholders
     */
    public static function format(array $placeholders): string
    {
        return implode(', ', array_map(fn (string $name) => ':'.$name, $placeholders));
    }
}
