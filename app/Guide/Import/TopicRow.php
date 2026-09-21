<?php

declare(strict_types=1);

namespace App\Guide\Import;

/**
 * Eine gepruefte Zeile der Themenliste, Platzhalter in kanonischer Form.
 */
final readonly class TopicRow
{
    /**
     * @param  array<int, array<string, mixed>>|null  $outline  Form von guide_topics.outline_json
     */
    public function __construct(
        public int $line,
        public string $question,
        public string $normalizedQuestion,
        public ?string $categoryName,
        public ?array $outline,
        public ?string $notes,
        public int $priority,
        public ?int $refreshIntervalDays,
    ) {}
}
