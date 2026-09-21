<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmClient;

/**
 * FAQ-Block eines Ratgebers (#10, Stufe guide.faq). Klartext ohne HTML,
 * weil der Block als FAQPage-Schema ausgespielt wird; Form wie
 * guide_article_details.faq_json: [{question, answer}].
 */
class FaqWriter
{
    public const TEMPLATE = 'guide.faq';

    public function __construct(
        private readonly LlmClient $client,
    ) {}

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public function write(WritingContext $context): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE),
            $context->vars(),
            self::outputSchema(),
            $context->llmContext(),
        );

        $faq = [];

        foreach ((array) ($result['faq'] ?? []) as $item) {
            $question = $this->plain(is_array($item) ? ($item['question'] ?? '') : '');
            $answer = $this->plain(is_array($item) ? ($item['answer'] ?? '') : '');

            if ($question !== '' && $answer !== '') {
                $faq[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $faq;
    }

    /**
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['faq'],
            'properties' => [
                'faq' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => ['type' => 'string', 'maxLength' => 200],
                            'answer' => ['type' => 'string', 'maxLength' => 700],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function plain(mixed $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
