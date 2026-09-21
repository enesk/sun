<?php

declare(strict_types=1);

namespace App\Guide\Quality;

use App\Guide\Llm\LlmClient;
use App\Guide\Models\ArticleVersion;
use App\Guide\Writing\HtmlAssembler;
use App\Guide\Writing\WritingContext;

/**
 * Stufe 4 des Qualitaetsgates (#11): redaktionelle Rubrik aus SUN-RG-006
 * (docs/guide-prompts.md §6, Template guide.quality_rubric).
 *
 * Neuanlage: bewertet wird der ganze Artikel (Titel, Kurzantwort, body_html,
 * FAQ). Update: nur der geaenderte Teil — die neu geschriebenen Abschnitte
 * und, falls neu, Kurzantwort, FAQ und Meta. Der Rest ist unveraendert und
 * wurde bei seiner Veroeffentlichung bereits bewertet; Lint und
 * Faktenabgleich laufen trotzdem ueber den ganzen Artikel.
 *
 * Das Modell schreibt nicht um. blocking_issues verhindern die Auto-Freigabe
 * unabhaengig vom Score; fix_instructions adressieren eine Abschnitts-id (oder
 * short_answer, faq, meta oder "artikel") und steuern den einen
 * Fix-Durchlauf. Anweisungen an Abschnitte,
 * die es nicht gibt, werden auf "artikel" gesetzt.
 *
 * Das Ausgabeschema gehoert dem Code (self::outputSchema()); der
 * GuidePromptTemplateSeeder referenziert es.
 */
class RubricEvaluator
{
    public const TEMPLATE = 'guide.quality_rubric';

    public const SCOPE_FULL = 'full';

    public const SCOPE_CHANGED = 'changed';

    public const CODES = ['unbelegte_zahl', 'faktenwiderspruch', 'veraltete_angabe', 'ymyl_disclaimer_fehlt', 'werbesprache', 'sonstiges'];

    public const CRITERIA = ['belege', 'faktentreue', 'aktualitaet', 'suchintention', 'sprache', 'neutralitaet', 'ymyl', 'struktur'];

    public function __construct(
        private readonly LlmClient $client,
        private readonly HtmlAssembler $html,
    ) {}

    /**
     * @param  array<int, string>|null  $sectionIds  null = ganzer Artikel, sonst nur diese Abschnitte (Update)
     * @param  array<int, string>  $extraParts  im Update zusaetzlich neu: short_answer, faq, meta
     * @return array{scope: string, section_ids: array<int, string>, score: float, per_criterion: list<array<string, mixed>>, blocking_issues: list<array{code: string, section_id: ?string, quote: string, explanation: string}>, fix_instructions: list<array{section_id: string, instruction: string}>, template: array{key: string, version: int}}
     */
    public function evaluate(WritingContext $context, ArticleVersion $version, ?array $sectionIds = null, array $extraParts = []): array
    {
        $template = WritingContext::template(self::TEMPLATE);
        $scope = $sectionIds === null ? self::SCOPE_FULL : self::SCOPE_CHANGED;

        $result = $this->client->structured(
            $template,
            $context->vars(['existing_section_html' => $this->articleHtml($version, $sectionIds, $extraParts)]),
            self::outputSchema(),
            $context->llmContext(),
        );

        $sections = array_values(array_filter(array_map('strval', array_keys($this->html->split((string) $version->body_html)))));
        $known = [...$sections, 'short_answer', 'faq', 'meta'];

        return [
            'scope' => $scope,
            'section_ids' => $sectionIds ?? $sections,
            'score' => (float) max(0, min(100, (int) ($result['score'] ?? 0))),
            'per_criterion' => $this->criteria((array) ($result['per_criterion'] ?? [])),
            'blocking_issues' => $this->blockingIssues((array) ($result['blocking_issues'] ?? []), $known),
            'fix_instructions' => $this->fixInstructions((array) ($result['fix_instructions'] ?? []), $known),
            'template' => ['key' => (string) $template->key, 'version' => (int) $template->version],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        $object = fn (array $properties): array => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];

        return $object([
            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'per_criterion' => [
                'type' => 'array',
                'minItems' => 8,
                'maxItems' => 8,
                'items' => $object([
                    'key' => ['type' => 'string', 'enum' => self::CRITERIA],
                    'points' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 25],
                    'max_points' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                    'comment' => ['type' => 'string', 'maxLength' => 400],
                ]),
            ],
            'blocking_issues' => [
                'type' => 'array',
                'items' => $object([
                    'code' => ['type' => 'string', 'enum' => self::CODES],
                    'section_id' => ['type' => ['string', 'null']],
                    'quote' => ['type' => 'string', 'maxLength' => 400],
                    'explanation' => ['type' => 'string', 'maxLength' => 400],
                ]),
            ],
            'fix_instructions' => [
                'type' => 'array',
                'items' => $object([
                    'section_id' => ['type' => 'string'],
                    'instruction' => ['type' => 'string', 'maxLength' => 500],
                ]),
            ],
        ]);
    }

    /**
     * Artikel, wie das Modell ihn sieht. Im Update steht vorn der Hinweis,
     * dass nur die geaenderten Teile zu bewerten sind (Template Version 2).
     *
     * @param  array<int, string>|null  $sectionIds
     * @param  array<int, string>  $extraParts
     */
    private function articleHtml(ArticleVersion $version, ?array $sectionIds, array $extraParts): string
    {
        $full = $sectionIds === null;
        $parts = [];

        if ($full) {
            $parts[] = '<h1>'.e((string) $version->title).'</h1>';
        } else {
            $parts[] = '<p>[Nur geänderte Teile: '.implode(', ', [...$sectionIds, ...$extraParts]).'. Der übrige Artikel ist unverändert und bereits geprüft.]</p>';
        }

        if ($full || in_array('meta', $extraParts, true)) {
            $parts[] = '<p>[Meta-Title] '.e((string) $version->meta_title).'</p>';
            $parts[] = '<p>[Meta-Description] '.e((string) $version->meta_description).'</p>';
        }

        if ($full || in_array('short_answer', $extraParts, true)) {
            $parts[] = '<p>[Kurzantwort] '.e((string) $version->short_answer).'</p>';
        }

        $segments = $this->html->split((string) $version->body_html);
        $parts[] = $full
            ? (string) $version->body_html
            : implode('', array_intersect_key($segments, array_flip($sectionIds)));

        if ($full || in_array('faq', $extraParts, true)) {
            foreach ((array) ($version->faq_json ?? []) as $item) {
                if (is_array($item)) {
                    $parts[] = '<p>[FAQ] <strong>'.e((string) ($item['question'] ?? '')).'</strong> '.e((string) ($item['answer'] ?? '')).'</p>';
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<int, mixed>  $criteria
     * @return list<array{key: string, points: int, max_points: int, comment: string}>
     */
    private function criteria(array $criteria): array
    {
        $rows = [];

        foreach ($criteria as $entry) {
            if (! is_array($entry) || ! in_array($entry['key'] ?? null, self::CRITERIA, true)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $entry['key'],
                'points' => (int) ($entry['points'] ?? 0),
                'max_points' => (int) ($entry['max_points'] ?? 0),
                'comment' => trim((string) ($entry['comment'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $issues
     * @param  array<int, int|string>  $known
     * @return list<array{code: string, section_id: ?string, quote: string, explanation: string}>
     */
    private function blockingIssues(array $issues, array $known): array
    {
        $rows = [];

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $sectionId = trim((string) ($issue['section_id'] ?? ''));

            $rows[] = [
                'code' => in_array($issue['code'] ?? null, self::CODES, true) ? (string) $issue['code'] : 'sonstiges',
                'section_id' => in_array($sectionId, array_map('strval', $known), true) ? $sectionId : null,
                'quote' => trim((string) ($issue['quote'] ?? '')),
                'explanation' => trim((string) ($issue['explanation'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $instructions
     * @param  array<int, int|string>  $known
     * @return list<array{section_id: string, instruction: string}>
     */
    private function fixInstructions(array $instructions, array $known): array
    {
        $rows = [];

        foreach ($instructions as $entry) {
            $text = is_array($entry) ? trim((string) ($entry['instruction'] ?? '')) : '';

            if ($text === '') {
                continue;
            }

            $sectionId = trim((string) ($entry['section_id'] ?? ''));

            $rows[] = [
                'section_id' => in_array($sectionId, array_map('strval', $known), true) && $sectionId !== '' ? $sectionId : 'artikel',
                'instruction' => $text,
            ];
        }

        return $rows;
    }
}
