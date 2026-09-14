<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\Portal\Review;

/**
 * Keyword-Heuristik fuer Bewertungen (#13).
 *
 * Sortiert Verdachtsfaelle vor — typischer Fall ist ein Bewerbungsschreiben,
 * das jemand in das Bewertungsformular eines Betriebs kopiert hat. Die
 * Heuristik entscheidet nie selbst: ein Treffer fuehrt zu needs_review, nie
 * zu rejected. Muster und Grenzen stehen in config/moderation.php.
 */
final class ReviewSpamDetector
{
    private const EMAIL = '/[\pL\pN._%+\-]+@[\pL\pN.\-]+\.\pL{2,}/u';

    private const URL = '/(?:https?:\/\/|www\.)\S+|\b[\pL\pN\-]+\.(?:de|com|net|org|info|eu|at|ch|biz|io)\b(?:\/\S*)?/iu';

    // Beginnt mit + oder 0; Punkte zaehlen nicht als Trenner, sonst waere jedes Datum eine Nummer
    private const PHONE = '/(?:\+|\b0)\d[\d \/\-()]{5,}\d/u';

    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        private readonly array $keywords,
        private readonly bool $detectEmails = true,
        private readonly bool $detectUrls = true,
        private readonly bool $detectPhoneNumbers = true,
        private readonly int $phoneMinDigits = 7,
        private readonly int $maxLength = 1500,
    ) {}

    public static function fromConfig(): self
    {
        $config = (array) config('moderation.reviews', []);

        return new self(
            keywords: array_values(array_filter((array) ($config['keywords'] ?? []), 'is_string')),
            detectEmails: (bool) ($config['detect_emails'] ?? true),
            detectUrls: (bool) ($config['detect_urls'] ?? true),
            detectPhoneNumbers: (bool) ($config['detect_phone_numbers'] ?? true),
            phoneMinDigits: (int) ($config['phone_min_digits'] ?? 7),
            maxLength: (int) ($config['max_length'] ?? 1500),
        );
    }

    /**
     * Gruende als Klartext; leer = unauffaellig.
     *
     * @return list<string>
     */
    public function reasons(?string $title, ?string $body, ?string $authorName = null): array
    {
        $text = trim(implode("\n", array_filter([$title, $body], fn (?string $part): bool => (string) $part !== '')));
        $everything = trim("{$text}\n{$authorName}");

        if ($everything === '') {
            return [];
        }

        $reasons = [];
        $normalized = self::normalize($text);
        $hits = [];

        foreach ($this->keywords as $keyword) {
            $keyword = trim($keyword);
            $prefix = str_ends_with($keyword, '*');
            $words = preg_split('/\s+/u', self::normalize(rtrim($keyword, '*')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($words === []) {
                continue;
            }

            $pattern = '/(?<![\pL\pN])'
                .implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words))
                .($prefix ? '' : '(?![\pL\pN])')
                .'/u';

            if (preg_match($pattern, $normalized) === 1) {
                $hits[] = rtrim($keyword, '*');
            }
        }

        if ($hits !== []) {
            $reasons[] = 'Stichwort: '.implode(', ', array_unique($hits));
        }

        if ($this->detectEmails && preg_match(self::EMAIL, $everything) === 1) {
            $reasons[] = 'E-Mail-Adresse im Text';
        }

        if ($this->detectUrls && preg_match(self::URL, $everything) === 1) {
            $reasons[] = 'Link im Text';
        }

        if ($this->detectPhoneNumbers && $this->containsPhoneNumber($everything)) {
            $reasons[] = 'Telefonnummer im Text';
        }

        if ($this->maxLength > 0 && mb_strlen($text) > $this->maxLength) {
            $reasons[] = "Text länger als {$this->maxLength} Zeichen";
        }

        return $reasons;
    }

    public function reasonsFor(Review $review): array
    {
        return $this->reasons($review->title, $review->body, $review->author_name);
    }

    /**
     * Ein Grund als Zeichenkette fuer reviews.moderation_reason (max. 255).
     */
    public function reasonFor(Review $review): ?string
    {
        $reasons = $this->reasonsFor($review);

        return $reasons === [] ? null : mb_strimwidth(implode('; ', $reasons), 0, 255, '…');
    }

    private function containsPhoneNumber(string $text): bool
    {
        if (preg_match_all(self::PHONE, $text, $matches) === false) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            if (strlen((string) preg_replace('/\D/', '', $candidate)) >= $this->phoneMinDigits) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $text): string
    {
        return strtr(mb_strtolower($text), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    }
}
