<?php

declare(strict_types=1);

namespace App\Content\Support;

/**
 * Einheitliche Faltung fuer jeden Textabgleich der Pipeline (#95).
 *
 * Deutsche Keywords kommen in zwei Schreibweisen vor: die Themenkandidaten
 * fuehren sie in ASCII-Umschrift ('heizungstausch foerderung bayern'), der
 * Artikel schreibt sie mit Umlaut ('Heizungstausch-Förderung'). Ohne Faltung
 * findet ein einfacher Vergleich das eine im anderen nicht.
 *
 * Zwei Varianten, weil beide Schreibweisen in freien Texten vorkommen:
 * 'ö' => 'oe' trifft die Umschrift, 'ö' => 'o' die verkuerzte Schreibweise
 * ohne Punkte. Wer sicher treffen will, prueft gegen beide.
 */
final class TextFold
{
    /** Umschrift: 'ö' wird ausgeschrieben. */
    private const UMLAUTS_EXPANDED = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
    ];

    /** Verkuerzte Schreibweise: 'ö' verliert nur die Punkte. */
    private const UMLAUTS_PLAIN = [
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
    ];

    /** Uebrige Akzente haben nur eine sinnvolle Auflösung. */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e',
        'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o',
        'ò' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
        'ñ' => 'n',
    ];

    /**
     * Kleinschreibung, Umlaute aufgeloest, alles ausser Buchstaben und
     * Ziffern auf ein Leerzeichen. Beide Seiten eines Abgleichs muessen durch
     * dieselbe Variante laufen.
     */
    public static function fold(string $text, bool $expandUmlauts = true): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, $expandUmlauts ? self::UMLAUTS_EXPANDED : self::UMLAUTS_PLAIN);
        $text = strtr($text, self::ACCENTS);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '') ?? '');
    }

    /**
     * Beide Faltungen desselben Textes, Umschrift zuerst.
     *
     * @return array<int, string>
     */
    public static function variants(string $text): array
    {
        $expanded = self::fold($text);
        $plain = self::fold($text, expandUmlauts: false);

        return $expanded === $plain ? [$expanded] : [$expanded, $plain];
    }
}
