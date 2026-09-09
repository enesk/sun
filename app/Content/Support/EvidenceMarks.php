<?php

declare(strict_types=1);

namespace App\Content\Support;

/**
 * Belegmarken der Textgenerierung (#14).
 *
 * Die Faktenliste im Prompt ist nummeriert ("[F117] ..."), damit das Modell
 * belegen kann, welchen Faktenschnipsel ein Satz verwendet. Die Marke gehoert
 * in die Fakten-Zuordnung, nicht in den Lesetext: im Frontend ist sie Rauschen,
 * und der Faktencheck (#15) liest die Ziffern sonst als unbelegte Zahl im
 * Artikel — ein blockierender Fehlalarm.
 */
final class EvidenceMarks
{
    /** Eine Belegmarke, wie GenerationContext::factBlock() sie vergibt. */
    public const PATTERN = '/\[F\d+\]/u';

    /**
     * Entfernt alle Belegmarken und raeumt die Luecke auf, die sie
     * hinterlassen ("Betriebe [F117] [F118]." wird zu "Betriebe.").
     */
    public static function strip(string $text): string
    {
        $clean = preg_replace(self::PATTERN, '', $text, -1, $count);

        if ($clean === null || $count === 0) {
            return $text;
        }

        $clean = preg_replace('/[ \t]{2,}/u', ' ', $clean) ?? $clean;

        return preg_replace('/[ \t]+([.,;:!?)\]])/u', '$1', $clean) ?? $clean;
    }
}
