<?php

declare(strict_types=1);

namespace App\Content\Sources\Support;

use App\Content\Support\TextFold;

/**
 * Prueft, ob ein freier Text Branchen-Keywords des Mandanten enthaelt (#11).
 *
 * Dieselbe Aufgabe wie im GoogleTrendsRssConnector (#8), aber als eigener
 * Baustein, weil sie jetzt vier Connectoren betrifft: Feeds, Foerderdatenbank,
 * News und Saisonkalender. Der Trends-Connector bleibt unangetastet, sein
 * Abgleich ist auf den ht:-Feed zugeschnitten.
 *
 * Deutsch flektiert und setzt zusammen, deshalb wird ueber Wortstaemme
 * verglichen: "Heizung" trifft "Heizungen", "Heizungsgesetz" und
 * "Heizungsfoerderung". Ein Suffix-Stemmer genuegt fuer eine
 * Ja/Nein-Entscheidung ueber eine Handvoll Keywords.
 */
final class KeywordMatcher
{
    /**
     * Laengste Suffixe zuerst, damit "Heizungen" ueber 'ungen' abgebaut wird.
     */
    private const SUFFIXES = [
        'ungen', 'innen', 'erin', 'heit', 'keit', 'isch', 'lich', 'ung',
        'end', 'ern', 'em', 'en', 'er', 'es', 'se', 'n', 's', 'e',
    ];

    /** @var array<string, array<int, string>> Keyword => Wortstaemme */
    private array $stems = [];

    private readonly int $minStemLength;

    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(array $keywords, ?int $minStemLength = null)
    {
        $this->minStemLength = max(1, $minStemLength ?? (int) config('content.sources.keyword_matcher.min_stem_length', 4));

        foreach ($keywords as $keyword) {
            $normalized = $this->normalize($keyword);

            if ($normalized === '') {
                continue;
            }

            $this->stems[$keyword] = array_values(array_unique(
                array_map(fn (string $word): string => $this->stem($word), explode(' ', $normalized))
            ));
        }
    }

    public function hasKeywords(): bool
    {
        return $this->stems !== [];
    }

    public function matchesAny(string $text): bool
    {
        return $this->matches($text) !== [];
    }

    /**
     * Welche Branchen-Keywords im Text stecken, in ihrer Originalschreibweise.
     *
     * @return array<int, string>
     */
    public function matches(string $text): array
    {
        $haystack = $this->normalize($text);

        if ($haystack === '' || $this->stems === []) {
            return [];
        }

        $wordStems = array_unique(array_map(fn (string $word): string => $this->stem($word), explode(' ', $haystack)));
        $matched = [];

        foreach ($this->stems as $keyword => $tokens) {
            if ($tokens === []) {
                continue;
            }

            foreach ($tokens as $token) {
                // Lange Staemme duerfen im Kompositum treffen ('heiz' in
                // 'heizungsgesetz'), kurze nur als ganzes Wort.
                $hit = mb_strlen($token) >= $this->minStemLength
                    ? str_contains($haystack, $token)
                    : in_array($token, $wordStems, true);

                if (! $hit) {
                    continue 2;
                }
            }

            $matched[] = (string) $keyword;
        }

        return $matched;
    }

    /**
     * Kleinschreibung, Umlaute aufgeloest, alles andere zu Leerzeichen.
     * Beide Seiten des Abgleichs laufen durch dieselbe Funktion.
     */
    private function normalize(string $text): string
    {
        // Umlaute werden ausgeschrieben, nicht auf den Grundvokal gekuerzt:
        // Behoerdentexte schreiben ohnehin haeufig 'Sanitaerraum' statt
        // 'Sanitärraum', und nur so treffen beide Schreibweisen aufeinander.
        // Die Faltung selbst steht seit #95 in TextFold.
        return TextFold::fold(strip_tags($text));
    }

    /**
     * Hoechstens ein Suffix wird entfernt, und nur, wenn mindestens vier
     * Zeichen stehen bleiben — sonst schrumpfen kurze Woerter zu Resten, die
     * ueberall treffen.
     */
    private function stem(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 4) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            }
        }

        return $word;
    }
}
