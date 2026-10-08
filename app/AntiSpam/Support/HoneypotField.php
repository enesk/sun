<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use Illuminate\Support\Str;

/**
 * Feldname des Honeypots (#8) — je Session zufaellig, damit ein Bot ihn nicht
 * hart verdrahten kann.
 *
 * Der Name muss zwischen Rendern und Abschicken stabil bleiben, deshalb liegt
 * er in der Session. Ohne Session (Konsole, API) gilt der erste Basisname ohne
 * Suffix: dann ist das Feld nicht zufaellig, aber es bricht nichts.
 *
 * Die Basisnamen in config('antispam.honeypot.field_names') sind absichtlich
 * plausibel — ein Feld namens `honeypot` fuellt kein Bot aus.
 */
final class HoneypotField
{
    public const SESSION_KEY = 'antispam.honeypot_field';

    /** Fallback, wenn die Konfiguration leer ist. */
    private const FALLBACK = 'website_url';

    public static function name(): string
    {
        if (! self::hasSession()) {
            return self::baseNames()[0] ?? self::FALLBACK;
        }

        $name = session(self::SESSION_KEY);

        if (is_string($name) && self::isWellFormed($name)) {
            return $name;
        }

        $name = self::generate();
        session()->put(self::SESSION_KEY, $name);

        return $name;
    }

    /** Wert des Honeypots aus den Formulardaten. */
    public static function valueIn(mixed $data): string
    {
        $value = is_array($data) ? ($data[self::name()] ?? null) : null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Fuellt? Dann hat ein Mensch das Feld nie gesehen. */
    public static function tripped(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }

    private static function generate(): string
    {
        $basis = self::baseNames();
        $basis = $basis === [] ? [self::FALLBACK] : $basis;

        return $basis[array_rand($basis)].'_'.Str::lower(Str::random(6));
    }

    /**
     * @return list<string>
     */
    private static function baseNames(): array
    {
        $names = AntiSpamConfig::list('honeypot.field_names');

        return array_values(array_filter($names, static fn (string $name): bool => self::isWellFormed($name)));
    }

    /** Nur Zeichen, die als HTML-Feldname und als Validierungsschluessel gehen. */
    private static function isWellFormed(string $name): bool
    {
        return $name !== '' && preg_match('/^[a-z][a-z0-9_]{2,39}$/', $name) === 1;
    }

    private static function hasSession(): bool
    {
        $request = request();

        return $request !== null && $request->hasSession();
    }
}
