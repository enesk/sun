<?php

declare(strict_types=1);

namespace App\Guide\Research;

/**
 * Vergleichsform eines Faktwerts (#9): Zahlenformat, Einheiten, Spannen und
 * Whitespace werden vereinheitlicht, damit reine Schreibvarianten keine
 * Aenderung ausloesen ('1.500 €' == '1500 Euro', '1.500–3.000 €/m²' ==
 * '1500 bis 3000 Euro pro qm').
 *
 * Das Ergebnis ist nur ein Vergleichsschluessel, kein Anzeigewert.
 *
 * Zahlen werden deutsch gelesen: Punkt als Tausender-, Komma als
 * Dezimaltrenner ('1,5' = 1.5). Englische Formen gelten nur, wo sie eindeutig
 * sind ('1,500,000', '1,500.50', '19.99'). Datumsangaben 'TT.MM.JJJJ' werden
 * zu 'JJJJ-MM-TT'.
 */
final class FactNormalizer
{
    /**
     * Schreibvarianten je Einheit; Reihenfolge egal, laengere gewinnen.
     */
    private const UNITS = [
        '€' => ['€', 'eur', 'euro', 'euros'],
        'ct' => ['ct', 'cent'],
        '%' => ['%', 'prozent'],
        'm²' => ['m²', 'm2', 'qm', 'quadratmeter'],
        'm³' => ['m³', 'm3', 'kubikmeter'],
        'kwh' => ['kwh', 'kilowattstunde', 'kilowattstunden'],
        'h' => ['h', 'std', 'std.', 'stunde', 'stunden'],
        'tag' => ['tag', 'tage', 'tagen'],
        'woche' => ['woche', 'wochen'],
        'monat' => ['monat', 'monate', 'monaten'],
        'jahr' => ['jahr', 'jahre', 'jahren'],
        '/' => ['pro', 'je'],
    ];

    private ?string $unitPattern = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $unitMap = null;

    public function normalize(?string $value, ?string $unit = null): string
    {
        $text = trim(((string) $value).' '.((string) $unit));

        // Geschuetzte und schmale Leerzeichen als Tausendertrenner: '1 500'.
        $text = (string) preg_replace('/(?<=\d)[\x{00A0}\x{202F}\x{2009}](?=\d{3}(?!\d))/u', '', $text);
        $text = mb_strtolower((string) preg_replace('/[\s\x{00A0}\x{202F}\x{2009}]+/u', ' ', $text));

        // Spannen: '1.500 bis 3.000', '1.500 – 3.000'.
        $text = (string) preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', '-', $text);
        $text = (string) preg_replace('/(?<=\d)\s*(?:€|eur|euro)?\s+bis\s+(?=\d)/u', '-', $text);

        // ISO-Datum bleibt, wie es ist; alle anderen Zahlen werden vereinheitlicht.
        $text = (string) preg_replace_callback(
            '/\d{4}-\d{2}-\d{2}|\d[\d.,]*/u',
            fn (array $match): string => str_contains($match[0], '-') ? $match[0] : $this->number($match[0]),
            $text,
        );
        $text = (string) preg_replace_callback($this->unitPattern(), fn (array $match): string => $this->unitMap()[$match[0]] ?? $match[0], $text);

        return rtrim((string) preg_replace('/\s+/u', '', $text), '.');
    }

    /**
     * Gleicher Wert samt Einheit und Gueltigkeitsdatum.
     *
     * @param  array{value?: ?string, unit?: ?string, valid_from?: ?string}  $a
     * @param  array{value?: ?string, unit?: ?string, valid_from?: ?string}  $b
     */
    public function same(array $a, array $b): bool
    {
        return $this->normalize($a['value'] ?? null, $a['unit'] ?? null) === $this->normalize($b['value'] ?? null, $b['unit'] ?? null)
            && (string) ($a['valid_from'] ?? '') === (string) ($b['valid_from'] ?? '');
    }

    private function number(string $token): string
    {
        $trailing = '';

        if (preg_match('/[.,]+$/', $token, $match) === 1) {
            $trailing = $match[0];
            $token = substr($token, 0, -strlen($trailing));
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $token, $date) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $date[3], (int) $date[2], (int) $date[1]).$trailing;
        }

        $number = match (true) {
            preg_match('/^\d+$/', $token) === 1 => $token,
            // deutsch: 1.500 | 1.500,50 | 1500,5
            preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $token) === 1,
            preg_match('/^\d+,\d+$/', $token) === 1 => str_replace(',', '.', str_replace('.', '', $token)),
            // englisch, nur eindeutige Formen: 1,500,000 | 1,500.50 | 19.99
            preg_match('/^\d{1,3}(,\d{3}){2,}$/', $token) === 1,
            preg_match('/^\d{1,3}(,\d{3})+\.\d+$/', $token) === 1 => str_replace(',', '', $token),
            preg_match('/^\d+\.\d{1,2}$/', $token) === 1 => $token,
            default => null,
        };

        if ($number === null) {
            return $token.$trailing;
        }

        [$integer, $decimals] = array_pad(explode('.', $number, 2), 2, '');
        $integer = ltrim($integer, '0');
        $decimals = rtrim($decimals, '0');

        return ($integer === '' ? '0' : $integer).($decimals !== '' ? ".{$decimals}" : '').$trailing;
    }

    private function unitPattern(): string
    {
        if ($this->unitPattern !== null) {
            return $this->unitPattern;
        }

        $variants = array_keys($this->unitMap());
        usort($variants, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternatives = implode('|', array_map(fn (string $variant): string => preg_quote($variant, '/'), $variants));

        return $this->unitPattern = "/(?<!\\p{L})(?:{$alternatives})(?!\\p{L})/u";
    }

    /**
     * @return array<string, string>
     */
    private function unitMap(): array
    {
        if ($this->unitMap !== null) {
            return $this->unitMap;
        }

        $map = [];

        foreach (self::UNITS as $canonical => $variants) {
            foreach ($variants as $variant) {
                $map[$variant] = $canonical;
            }
        }

        return $this->unitMap = $map;
    }
}
