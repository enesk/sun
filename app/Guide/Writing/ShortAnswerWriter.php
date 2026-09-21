<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmClient;

/**
 * Kurzantwort unter der Artikelueberschrift (#10, Stufe guide.short_answer):
 * zwei bis drei Saetze Klartext, Zahlen nur aus dem Fakten-Set.
 */
class ShortAnswerWriter
{
    public const TEMPLATE = 'guide.short_answer';

    public function __construct(
        private readonly LlmClient $client,
    ) {}

    /**
     * @return array{short_answer: string, used_fact_keys: array<int, string>}
     */
    public function write(WritingContext $context): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE),
            $context->vars(),
            self::outputSchema(),
            $context->llmContext(),
        );

        return [
            'short_answer' => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($result['short_answer'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            'used_fact_keys' => $context->knownFactKeys((array) ($result['used_fact_keys'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['short_answer', 'used_fact_keys'],
            'properties' => [
                'short_answer' => ['type' => 'string', 'minLength' => 120, 'maxLength' => 480],
                'used_fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }
}
