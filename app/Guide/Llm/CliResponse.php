<?php

declare(strict_types=1);

namespace App\Guide\Llm;

/**
 * Ergebnis eines Aufrufs der Claude-CLI (#42).
 *
 * searchResults sind die Treffer der tatsaechlich ausgefuehrten Websuchen
 * (url, title), gelesen aus dem stream-json-Verlauf. Sie ersetzen die
 * web_search_tool_result-Bloecke der Messages API, damit der Faktenabgleich
 * weiter pruefen kann, ob eine Quelle wirklich gefunden wurde.
 */
final class CliResponse
{
    /**
     * @param  array<string, mixed>|null  $output  structured_output
     * @param  array<string, mixed>  $usage
     * @param  array<int, array{url: string, title: string}>  $searchResults
     * @param  array<int, string>  $fetchedUrls
     */
    public function __construct(
        public readonly ?array $output,
        public readonly string $text,
        public readonly array $usage,
        public readonly float $costUsd,
        public readonly int $turns,
        public readonly int $searches = 0,
        public readonly array $searchResults = [],
        public readonly array $fetchedUrls = [],
    ) {}

    public function inputTokens(): int
    {
        return (int) ($this->usage['input_tokens'] ?? 0)
            + (int) ($this->usage['cache_read_input_tokens'] ?? 0)
            + (int) ($this->usage['cache_creation_input_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return (int) ($this->usage['output_tokens'] ?? 0);
    }
}
