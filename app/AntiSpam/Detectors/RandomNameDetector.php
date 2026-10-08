<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\Support\BotCandidate;
use Illuminate\Support\Str;

/**
 * R7 und R8: Der Name ist kein Name (#10).
 *
 * Vier Muster, eines genuegt:
 *
 *   1. Ziffern im Namen ("Max123", "user4711")
 *   2. ein Wort ab 12 Zeichen ohne Vokal (Tastaturrauschen)
 *   3. der Name ist die E-Mail-Adresse
 *   4. der Name enthaelt eine URL (`http`, `www.`) — das Muster der
 *      Link-Spam-Eintraege
 *
 * Die Schwelle "ab 12 Zeichen ohne Vokal" ist absichtlich hoch: kurze Woerter
 * ohne Vokal gibt es in echten Namen ("Krbl"), lange nicht mehr. Ziffern sind
 * im Bestand bei 142 Namen kein einziges Mal vorgekommen (§1.2) — die Regel
 * ist Vorsorge und soll deshalb eng greifen.
 */
final class RandomNameDetector implements Detector
{
    private const MIN_VOWEL_FREE_LENGTH = 12;

    public function code(): string
    {
        return 'random_name';
    }

    public function label(): string
    {
        return __('Name ist ein Zufallsstring oder eine Adresse');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return filled($candidate->name);
    }

    public function matches(BotCandidate $candidate): bool
    {
        $name = trim((string) $candidate->name);

        if (preg_match('/\d/', $name) === 1) {
            return true;
        }

        $lower = Str::lower($name);

        if (str_contains($lower, 'http') || str_contains($lower, 'www.')) {
            return true;
        }

        if (filled($candidate->email) && $lower === Str::lower(trim((string) $candidate->email))) {
            return true;
        }

        return $this->hasVowelFreeWord($lower);
    }

    private function hasVowelFreeWord(string $lower): bool
    {
        $words = preg_split('/[^\p{L}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            if (mb_strlen($word) < self::MIN_VOWEL_FREE_LENGTH) {
                continue;
            }

            if (preg_match('/[aeiouyäöü]/u', $word) !== 1) {
                return true;
            }
        }

        return false;
    }
}
