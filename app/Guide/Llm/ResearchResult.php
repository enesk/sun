<?php

declare(strict_types=1);

namespace App\Guide\Llm;

/**
 * Ergebnis von LlmClient::research() (#5).
 *
 * `citations` sind die Treffer, die das Web-Search-Werkzeug geliefert hat,
 * je URL einmal: url, title, page_age (Angabe der Suchmaschine, etwa
 * "2 days ago" oder ein Datum; null, wenn unbekannt) und cited = das Modell
 * hat die Quelle im Text ausdruecklich zitiert.
 */
final class ResearchResult
{
    /**
     * @param  array<string, mixed>  $data  schemakonforme Nutzdaten aus 'emit'
     * @param  array<int, array{url: string, title: string, page_age: ?string, cited: bool}>  $citations
     * @param  array<int, string>  $searchErrors  error_code der fehlgeschlagenen Suchen
     */
    public function __construct(
        public readonly array $data,
        public readonly array $citations,
        public readonly int $searchCount,
        public readonly float $costUsd = 0.0,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $requests = 0,
        public readonly array $searchErrors = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function urls(): array
    {
        return array_column($this->citations, 'url');
    }
}
