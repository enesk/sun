<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Titel und Meta-Description (#10, Stufe guide.meta).
 *
 * Titel <= guide.writing.title_max (60) Zeichen, Meta-Description <=
 * guide.writing.meta_description_max (155). Der Titel ist zugleich
 * posts.title und posts.meta_title. Das laufende Jahr steht im gespeicherten
 * Titel als {{year}} und wird zur Renderzeit ersetzt
 * (GuidePageData::replaceYear()); gemessen wird die gerenderte Laenge.
 */
class MetaWriter
{
    public const TEMPLATE = 'guide.meta';

    public const YEAR_PLACEHOLDER = '{{year}}';

    public function __construct(
        private readonly LlmClient $client,
    ) {}

    /**
     * @return array{title: string, meta_description: string, primary_keyword: string}
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
            'title' => $this->title((string) ($result['meta_title'] ?? ''), (string) $context->topic->question),
            'meta_description' => $this->limit((string) ($result['meta_description'] ?? ''), self::descriptionMax()),
            'primary_keyword' => trim((string) ($result['primary_keyword'] ?? '')),
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
            'required' => ['meta_title', 'meta_description', 'primary_keyword'],
            'properties' => [
                'meta_title' => ['type' => 'string', 'minLength' => 10, 'maxLength' => self::titleMax()],
                'meta_description' => [
                    'type' => 'string',
                    'minLength' => (int) config('guide.writing.meta_description_min', 100),
                    'maxLength' => self::descriptionMax(),
                ],
                'primary_keyword' => ['type' => 'string', 'maxLength' => 80],
            ],
        ];
    }

    public static function titleMax(): int
    {
        return max(20, (int) config('guide.writing.title_max', 60));
    }

    public static function descriptionMax(): int
    {
        return max(50, (int) config('guide.writing.meta_description_max', 155));
    }

    /**
     * Laufendes Jahr als Platzhalter; "03/2026" oder "2026-01-01" bleiben.
     */
    private function title(string $title, string $fallback): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', strip_tags($title)));
        $title = $title !== '' ? $title : rtrim($fallback, '?');
        $year = (string) Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->year;

        $rendered = $this->limit($title, self::titleMax());

        return (string) preg_replace('#(?<![\d/.\-])'.$year.'(?![\d/.\-])#', self::YEAR_PLACEHOLDER, $rendered);
    }

    private function limit(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(Str::limit($text, $max, '', preserveWords: true), ' ,;:-–');
    }
}
