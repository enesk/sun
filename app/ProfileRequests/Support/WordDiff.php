<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use Illuminate\Support\HtmlString;
use Jfcherng\Diff\SequenceMatcher;

/**
 * Wort-Diff fuer die Diff-Komponente des Panels (SUN-RQ, Ticket #11).
 *
 * Serverseitig, weil der Vergleich sonst zweimal existiert (einmal fuer die
 * Anzeige, einmal fuer das Protokoll) und weil ein Beschreibungstext mit
 * 1 500 Zeichen im Browser nichts zu suchen hat, was der Server in einem
 * Durchgang erledigt.
 *
 * Verglichen werden **Woerter**, nicht Zeichen: ein Tippfehler faerbt dann
 * das eine Wort und nicht die halbe Zeile. Die Trennung laeuft ueber
 * Leerraum, der Leerraum selbst bleibt als eigenes Token erhalten — nur so
 * steht im Ergebnis wieder der Originaltext und nicht eine normalisierte
 * Fassung davon.
 *
 * Die Rueckgabe ist HTML und wird mit {!! !!} ausgegeben. Jedes Token wird
 * dabei einzeln escaped; der einzige unescaped Anteil sind die von hier
 * erzeugten <ins>/<del>.
 */
final class WordDiff
{
    /**
     * Ab dieser Laenge lohnt der Wort-Diff. Darunter ist die Zeile so kurz,
     * dass alt und neu nebeneinander schneller zu lesen sind als eine
     * Einfaerbung (eine Telefonnummer wird nicht wortweise korrigiert).
     */
    public const MIN_LENGTH = 60;

    /**
     * Beide Seiten eingefaerbt: links nur <del>, rechts nur <ins>.
     *
     * @return array{old: HtmlString, new: HtmlString}
     */
    public static function render(?string $old, ?string $new): array
    {
        $oldTokens = self::tokenize((string) $old);
        $newTokens = self::tokenize((string) $new);

        $opcodes = (new SequenceMatcher($oldTokens, $newTokens))->getOpcodes();

        $left = '';
        $right = '';

        foreach ($opcodes as [$op, $i1, $i2, $j1, $j2]) {
            $removedTokens = array_slice($oldTokens, $i1, $i2 - $i1);
            $addedTokens = array_slice($newTokens, $j1, $j2 - $j1);

            $removed = self::escapeJoin($removedTokens);
            $added = self::escapeJoin($addedTokens);

            // OP_EQ traegt beide Seiten unveraendert; bei OP_REP ist eine
            // Seite Loeschung und die andere Einfuegung, deshalb kein match()
            // ueber die Ops, sondern die zwei Haelften getrennt behandelt.
            if ($op === SequenceMatcher::OP_EQ) {
                $left .= $removed;
                $right .= $added;

                continue;
            }

            // Reiner Leerraum wird nicht markiert — eine eingefaerbte Luecke
            // ist keine Information, nur Unruhe.
            $left .= self::mark($removed, self::hasWord($removedTokens), 'del');
            $right .= self::mark($added, self::hasWord($addedTokens), 'ins');
        }

        return [
            'old' => new HtmlString($left),
            'new' => new HtmlString($right),
        ];
    }

    /**
     * Lohnt der Wort-Diff fuer dieses Paar? Nur bei zwei vorhandenen Texten —
     * ein neu gesetztes oder geleertes Feld ist keine Korrektur, sondern ein
     * Austausch, und der liest sich als Ganzes besser.
     */
    public static function worthwhile(mixed $old, mixed $new): bool
    {
        if (! is_string($old) || ! is_string($new)) {
            return false;
        }

        if (trim($old) === '' || trim($new) === '') {
            return false;
        }

        return mb_strlen($old) >= self::MIN_LENGTH || mb_strlen($new) >= self::MIN_LENGTH;
    }

    /**
     * Text in Woerter und Leerraum zerlegen. Der Leerraum bleibt als Token
     * stehen, damit die Rueckgabe wieder der Eingabe entspricht.
     *
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [$text] : array_values($parts);
    }

    private static function mark(string $html, bool $hasWord, string $tag): string
    {
        if ($html === '' || ! $hasWord) {
            return $html;
        }

        return "<{$tag}>{$html}</{$tag}>";
    }

    /** @param  list<string>  $tokens */
    private static function hasWord(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (trim($token) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $tokens */
    private static function escapeJoin(array $tokens): string
    {
        $out = '';

        foreach ($tokens as $token) {
            // Zeilenumbrueche bleiben erhalten, die Anzeige setzt
            // whitespace-pre-line.
            $out .= e($token);
        }

        return $out;
    }
}
