<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use App\Services\Seo\AiFootprintDetector;

/**
 * Prueft einen erzeugten Text gegen die Regeln des Schreib-Prompts.
 *
 * check() liefert den normalisierten Text oder wirft mit dem Grund, der in
 * profile_description_rewrites.last_error landet. Zahlen und Jahre gelten nur
 * als erlaubt, wenn sie in den Eingabedaten des Profils stehen.
 */
final class DescriptionValidator
{
    private readonly AiFootprintDetector $footprints;

    public function __construct()
    {
        $this->footprints = AiFootprintDetector::fromConfig();
    }

    /**
     * @param  array<string, mixed>  $facts
     *
     * @throws InvalidDescription
     */
    public function check(string $text, array $facts): string
    {
        $text = $this->normalize($text);

        if ($text === '') {
            throw new InvalidDescription('leerer Text');
        }

        $length = (array) config('profile_descriptions.length');
        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if ($words < (int) $length['min_words'] || $words > (int) $length['max_words']) {
            throw new InvalidDescription("Laenge {$words} Woerter, erlaubt {$length['min_words']}-{$length['max_words']}");
        }

        $paragraphs = count(explode("\n\n", $text));

        if ($paragraphs > (int) $length['max_paragraphs']) {
            throw new InvalidDescription("{$paragraphs} Absaetze, erlaubt {$length['max_paragraphs']}");
        }

        foreach ((array) config('profile_descriptions.forbidden_phrases', []) as $phrase) {
            if (preg_match('/(?<![\p{L}])'.preg_quote((string) $phrase, '/').'(?![\p{L}])/iu', $text) === 1) {
                throw new InvalidDescription("verbotene Floskel \"{$phrase}\"");
            }
        }

        if (preg_match('/[*#`<>{}]|\[[^\]]*\]|^\s*(?:[-•–]|\d+[.)])\s/mu', $text) === 1) {
            throw new InvalidDescription('Markdown oder HTML im Text');
        }

        if (preg_match('/\b(?:wir|unser\w*)\b/iu', $text) === 1) {
            throw new InvalidDescription('Wir-Form statt dritter Person');
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $text) === 1) {
            throw new InvalidDescription('E-Mail-Adresse im Text');
        }

        if (preg_match('#https?://|\bwww\.|\b[a-z0-9-]+\.(?:de|com|net|org|io|info|eu|at|ch)\b#iu', $text) === 1) {
            throw new InvalidDescription('Webadresse im Text');
        }

        if (preg_match('/(?:\+\d{2}|\b0)\d[\d \/-]{5,}\d/u', $text) === 1) {
            throw new InvalidDescription('Telefonnummer im Text');
        }

        $source = json_encode($facts, JSON_UNESCAPED_UNICODE) ?: '';

        if (preg_match_all('/(?<!\d)(?:19|20)\d{2}(?!\d)/u', $text, $years) > 0) {
            foreach (array_unique($years[0]) as $year) {
                if (! str_contains($source, $year)) {
                    throw new InvalidDescription("Jahreszahl {$year} nicht in den Eingabedaten");
                }
            }
        }

        $footprint = $this->footprints->clean($text);

        if ($footprint->changed()) {
            throw new InvalidDescription('KI-Footprint: '.implode(', ', $footprint->matchedPatterns));
        }

        return $text;
    }

    /**
     * Zeilenenden vereinheitlichen, Absaetze auf eine Leerzeile, Zeilen im
     * Absatz zu einer Zeile.
     */
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [];

        $paragraphs = array_map(
            static fn (string $paragraph): string => trim((string) preg_replace('/\s+/u', ' ', $paragraph)),
            $paragraphs,
        );

        return implode("\n\n", array_filter($paragraphs, static fn (string $paragraph): bool => $paragraph !== ''));
    }
}
