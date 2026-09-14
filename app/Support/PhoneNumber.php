<?php

namespace App\Support;

/**
 * Rufnummern fuer tel:-Links und JSON-LD in E.164 (+4930123456).
 *
 * Bewusst schlanke Regex-Normalisierung ohne libphonenumber: das Paket liegt
 * nur als transitive Abhaengigkeit (-lite) im vendor und ist nicht direkt
 * verlangt. Nationale Nummern werden nur fuer die Laender in DIAL_CODES
 * aufgeloest; alles andere liefert null, und die Nummer erscheint als Text.
 */
final class PhoneNumber
{
    /** @var array<string, string> */
    private const DIAL_CODES = [
        'DE' => '49',
        'AT' => '43',
        'CH' => '41',
    ];

    /** Platzhalter aus Importen, die keine Nummer sind. */
    private const PLACEHOLDERS = ['none', 'null', 'nan', 'n/a', 'na', 'k.a.', 'ka', '-', '--', 'keine', 'keine angabe', 'unbekannt'];

    /** @var array<string, string|null> Laufzeit-Cache je Request (Profil + Karten + JSON-LD rufen dieselbe Nummer mehrfach) */
    private static array $cache = [];

    public static function toE164(?string $value, string $countryCode = 'DE'): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = "{$countryCode}|{$value}";

        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::normalize($value, strtoupper($countryCode));
        }

        return self::$cache[$key];
    }

    /**
     * Sichtbare Fassung: Originalschreibweise mit bereinigten Leerzeichen,
     * Platzhalter wie 'None' oder 'k.A.' ergeben null.
     */
    public static function display(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        if ($value === '' || in_array(mb_strtolower($value), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $value;
    }

    private static function normalize(string $value, string $countryCode): ?string
    {
        $value = self::display($value);
        $dialCode = self::DIAL_CODES[$countryCode] ?? null;

        if ($value === null || $dialCode === null) {
            return null;
        }

        // Nur Ziffern, Plus und uebliche Trennzeichen; Buchstaben (Durchwahl-Texte, zwei Nummern mit "oder") sind nicht eindeutig
        if (! preg_match('#^\+?[0-9\s/().\-]+$#', $value)) {
            return null;
        }

        // "+49 (0) 30 ..." bzw. "0049 (0)30 ..." – die geklammerte Null entfaellt international
        $value = preg_replace('/^(\+|00)(\d{1,3})\s*\(0\)/', '$1$2', $value);
        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($value, '+') || str_starts_with($digits, '00')) {
            $international = ltrim(str_starts_with($value, '+') ? $digits : substr($digits, 2), '0');

            return self::valid($international) ? "+{$international}" : null;
        }

        // Nationale Nummer braucht die Verkehrsausscheidungsziffer 0, ohne Vorwahl ist sie nicht waehlbar
        if (! preg_match('/^0[1-9]\d{5,12}$/', $digits)) {
            return null;
        }

        $international = $dialCode.substr($digits, 1);

        return self::valid($international) ? "+{$international}" : null;
    }

    /** E.164: Laendercode ohne fuehrende Null, hoechstens 15 Ziffern, darunter ist keine Anschlussnummer denkbar. */
    private static function valid(string $digits): bool
    {
        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1;
    }
}
