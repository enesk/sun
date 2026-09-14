<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * Ergebnis von AiFootprintDetector::clean().
 */
final readonly class AiFootprintResult
{
    /**
     * @param  list<string>  $matchedPatterns
     */
    public function __construct(
        public string $original,
        public string $cleaned,
        public array $matchedPatterns,
    ) {}

    public function changed(): bool
    {
        return $this->matchedPatterns !== [];
    }

    public function removedCharacters(): int
    {
        return mb_strlen($this->original) - mb_strlen($this->cleaned);
    }

    public function cleanedLength(): int
    {
        return mb_strlen($this->cleaned);
    }
}
