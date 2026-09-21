<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmClient;

/**
 * Changelog-Eintrag "Was ist neu?" einer Aktualisierung (#10, Stufe
 * guide.change_summary).
 *
 * Ein Satz je Aenderung; der Eintrag hat die Form
 * {date, summary, changed_section_ids, source_urls} plus `source`
 * (Herausgeber und URL der ersten Quelle) fuer die Quellenangabe im
 * Frontend (GuidePageData::changelog()). Datum, Abschnitte und Quellen
 * setzt der Code aus Lauf und Fakten-Set, nicht das Modell.
 */
class ChangelogWriter
{
    public const TEMPLATE = 'guide.change_summary';

    public function __construct(
        private readonly LlmClient $client,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $changedFacts  Form von {{changed_facts}}
     * @param  array<int, string>  $changedSectionIds
     * @param  array<string, string>  $sectionBodies  id => HTML der geaenderten Abschnitte nach der Aenderung
     * @return array{date: string, summary: string, changed_section_ids: array<int, string>, source_urls: array<int, string>, source: array{label: string, url: string}|null}
     */
    public function write(WritingContext $context, array $changedFacts, array $changedSectionIds, array $sectionBodies): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE),
            $context->vars([
                'changed_facts' => WritingContext::json($changedFacts),
                'previous_facts' => WritingContext::json(array_map(fn (array $fact): array => [
                    'key' => $fact['key'] ?? null,
                    'label' => $fact['label'] ?? null,
                    'value' => $fact['old_value'] ?? null,
                ], $changedFacts)),
                'existing_section_html' => $sectionBodies === [] ? 'keine' : implode("\n\n", $sectionBodies),
            ]),
            self::outputSchema(),
            $context->llmContext(),
        );

        $sentences = [];

        foreach ((array) ($result['sentences'] ?? []) as $item) {
            $sentence = SectionWriter::firstSentence((string) (is_array($item) ? ($item['sentence'] ?? '') : ''));

            if ($sentence !== '' && ! in_array($sentence, $sentences, true)) {
                $sentences[] = $sentence;
            }
        }

        $sourceUrls = array_values(array_unique(array_filter(
            array_map(fn (array $fact): string => trim((string) ($fact['source_url'] ?? '')), $changedFacts),
            fn (string $url): bool => preg_match('#^https?://#i', $url) === 1,
        )));

        $source = null;

        if ($sourceUrls !== []) {
            $publisher = $context->source($sourceUrls[0])?->publisher;
            $source = ['label' => trim((string) $publisher) !== '' ? (string) $publisher : (string) parse_url($sourceUrls[0], PHP_URL_HOST), 'url' => $sourceUrls[0]];
        }

        return [
            'date' => $context->today,
            'summary' => implode(' ', array_slice($sentences, 0, max(1, count($changedFacts)))),
            'changed_section_ids' => array_values($changedSectionIds),
            'source_urls' => $sourceUrls,
            'source' => $source,
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
            'required' => ['sentences'],
            'properties' => [
                'sentences' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['fact_key', 'sentence'],
                        'properties' => [
                            'fact_key' => ['type' => 'string', 'maxLength' => 128],
                            'sentence' => ['type' => 'string', 'minLength' => 15, 'maxLength' => 300],
                        ],
                    ],
                ],
            ],
        ];
    }
}
