<?php

declare(strict_types=1);

namespace App\Guide\Quality;

/**
 * Flesch-Reading-Ease in der deutschen Fassung nach Amstad (#11, uebernommen
 * aus dem alten Content-ReadabilityScorer, entfernt mit #35). Ziel guide_lint.readability,
 * nie blockierend.
 *
 *   FRE = 180 - ASL - (58,5 x ASW)
 *
 * ASL ist die mittlere Satzlaenge in Woertern, ASW die mittlere Silbenzahl je
 * Wort. Die englische Originalformel (206,835 - 1,015 x ASL - 84,6 x ASW)
 * bestraft deutsche Komposita zu hart und liefert fuer sachliche Ratgebertexte
 * regelmaessig negative Werte — deshalb Amstad.
 *
 * Die Silbenzaehlung ist eine Naeherung ueber Vokalgruppen: Diphthonge und
 * Umlaute zaehlen als eine Silbe, ein stummes Schluss-e wird abgezogen. Fuer
 * einen Teilscore reicht das; eine echte Silbentrennung waere ein eigenes
 * Woerterbuch.
 */
final class ReadabilityScorer
{
    /**
     * Vokalgruppen, die genau eine Silbe bilden.
     *
     * @var array<int, string>
     */
    private const DIPHTHONGS = ['au', 'ei', 'eu', 'ie', 'äu', 'ai', 'oi', 'ui'];

    /**
     * @return array{score: float, target: int, passed: bool, words: int, sentences: int, words_per_sentence: float, syllables_per_word: float, level: string}
     */
    public function score(string $html): array
    {
        $text = $this->plainText($html);
        $words = $this->words($text);
        $sentences = max(1, $this->sentences($text));
        $wordCount = count($words);

        if ($wordCount === 0) {
            return [
                'score' => 0.0,
                'target' => $this->target(),
                'passed' => false,
                'words' => 0,
                'sentences' => 0,
                'words_per_sentence' => 0.0,
                'syllables_per_word' => 0.0,
                'level' => 'sehr schwer',
            ];
        }

        $syllables = 0;

        foreach ($words as $word) {
            $syllables += $this->syllables($word);
        }

        $wordsPerSentence = $wordCount / $sentences;
        $syllablesPerWord = $syllables / $wordCount;
        $score = round(180 - $wordsPerSentence - (58.5 * $syllablesPerWord), 1);

        return [
            'score' => $score,
            'target' => $this->target(),
            'passed' => $score >= $this->target(),
            'words' => $wordCount,
            'sentences' => $sentences,
            'words_per_sentence' => round($wordsPerSentence, 1),
            'syllables_per_word' => round($syllablesPerWord, 2),
            'level' => $this->level($score),
        ];
    }

    public function target(): int
    {
        return (int) config('guide_lint.readability.min_flesch', 50);
    }

    /**
     * Beschriftung der Stufe, wie sie im Qualitaetsbericht steht.
     */
    private function level(float $score): string
    {
        return match (true) {
            $score >= 80 => 'sehr leicht',
            $score >= 60 => 'leicht',
            $score >= 50 => 'mittelschwer',
            $score >= 30 => 'schwer',
            default => 'sehr schwer',
        };
    }

    private function plainText(string $html): string
    {
        // Blockenden ohne Satzzeichen wuerden sonst zwei Saetze zu einem
        // verketten und die Satzlaenge verfaelschen.
        $html = preg_replace('/<\/(p|li|h2|h3|td|th|blockquote)>/i', '. ', $html) ?? $html;

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))) ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-]*/u', $text, $matches);

        return $matches[0] ?? [];
    }

    private function sentences(string $text): int
    {
        $count = preg_match_all('/[.!?:]+(?=\s|$)/u', $text);

        return $count === false ? 0 : $count;
    }

    private function syllables(string $word): int
    {
        $word = mb_strtolower($word);

        // Zahlen sprechen sich mehrsilbig; ohne diese Annahme wuerde "2026"
        // als einsilbig gezaehlt.
        if (preg_match('/^\d+$/', $word) === 1) {
            return max(1, (int) ceil(mb_strlen($word) / 2));
        }

        $word = str_replace(self::DIPHTHONGS, 'a', $word);
        $count = preg_match_all('/[aeiouäöüy]+/u', $word);
        $count = $count === false ? 0 : $count;

        // Stummes Schluss-e ("Frage" hat zwei Silben, nicht drei).
        if ($count > 1 && str_ends_with($word, 'e')) {
            $count--;
        }

        return max(1, $count);
    }
}
