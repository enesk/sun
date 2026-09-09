<?php

declare(strict_types=1);

namespace App\Content\Quality;

use App\Content\Generation\GenerationContext;
use App\Content\Generation\GenerationStep;
use App\Content\Models\ArticleDraft;

/**
 * Stufe 3 des Qualitaetsgates: die redaktionelle Rubrik (#15).
 *
 * Bewertet wird mit demselben Modell, das den Artikel geschrieben hat
 * (claude-sonnet-5), aber gegen die acht gewichteten Kriterien des Templates
 * `quality_rubric` (#13, docs/content-prompts.md §6). Der redaktionelle Teil
 * des Prompts ist damit im Content-Panel pflegbar; das Ausgabeschema gehoert
 * wie bei allen Stufen dem Code.
 *
 * `fix_instructions` sind Objekte aus Abschnittskennung und Anweisung, keine
 * blossen Saetze: ohne die Kennung wuesste der Fix-Durchlauf nicht, welchen
 * Abschnitt er ueberarbeiten soll, und muesste den ganzen Artikel neu
 * schreiben. Das gespeicherte Template `quality_rubric` fuehrt seit Version 2
 * dasselbe Schema (#66), weil der PromptTemplateSeeder self::outputSchema()
 * referenziert.
 */
class RubricEvaluator extends GenerationStep
{
    public function templateKey(): string
    {
        return 'quality_rubric';
    }

    /**
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     * @return array{score: float, per_criterion: array<int, array<string, mixed>>, blocking_issues: array<int, string>, fix_instructions: array<int, array{section_id: string, instruction: string}>}
     */
    public function evaluate(GenerationContext $context, ArticleDraft $draft, array $sections): array
    {
        $result = $this->ask($context, $draft, [
            'section' => $this->articleText($draft, $sections),
        ]);

        return [
            'score' => (float) ($result['score'] ?? 0),
            'per_criterion' => $this->criteria($result),
            'blocking_issues' => array_values(array_filter(array_map(
                static fn ($issue): string => is_string($issue) ? trim($issue) : '',
                (array) ($result['blocking_issues'] ?? []),
            ))),
            'fix_instructions' => $this->fixInstructions($result, $sections),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return self::outputSchema();
    }

    /**
     * Das wirksame Ausgabeschema der Rubrik. Oeffentlich und statisch, damit
     * der PromptTemplateSeeder es referenzieren kann, statt es zu kopieren —
     * so koennen der im Prompt-Editor angezeigte und der tatsaechliche
     * Vertrag nicht auseinanderlaufen (#66).
     *
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['score', 'per_criterion', 'blocking_issues', 'fix_instructions'],
            'properties' => [
                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                'per_criterion' => [
                    'type' => 'array',
                    'minItems' => 8,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['criterion', 'weight', 'score', 'comment'],
                        'properties' => [
                            'criterion' => [
                                'type' => 'string',
                                'enum' => [
                                    'einzigartigkeit', 'faktenbelege', 'suchintention', 'struktur',
                                    'lesbarkeit', 'interne_links', 'regionalitaet', 'ymyl_sicherheit',
                                ],
                            ],
                            'weight' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'comment' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 400],
                        ],
                    ],
                ],
                'blocking_issues' => ['type' => 'array', 'maxItems' => 12, 'items' => ['type' => 'string', 'maxLength' => 400]],
                'fix_instructions' => [
                    'type' => 'array',
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['section_id', 'instruction'],
                        'properties' => [
                            'section_id' => ['type' => 'string', 'maxLength' => 8],
                            'instruction' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 400],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        return "Regeln fuer die Ausgabe:\n"
            ."- score ist die gewichtete Summe der acht Kriterien, 0 bis 100.\n"
            .'- Jeder Abschnitt des Artikels traegt eine Kennung in eckigen Klammern ([s1], [s2], ...). '
            ."Jede Anweisung in fix_instructions nennt in section_id genau die Kennung des Abschnitts, der zu aendern ist.\n"
            .'- Eine Anweisung beschreibt eine konkrete Aenderung an diesem Abschnitt, keine allgemeine '
            ."Kritik und keine Anweisung zum ganzen Artikel.\n"
            .'- blocking_issues enthaelt nur, was eine Veroeffentlichung verhindert.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Bewerten Sie den folgenden Artikelentwurf (Branche {$context->branchLabel}, Haupt-Keyword
            {$context->primaryKeyword}, Regionsbezug {$context->regionScope} {$context->regionName})
            anhand der acht gewichteten Kriterien: Einzigartigkeit (15), Faktenbelege (20),
            Suchintention (15), Struktur (10), Lesbarkeit (10), Interne Links (10),
            Regionalitaet (10), YMYL-Sicherheit (10).

            Styleguide der Branche (Verbotsliste und Disclaimer-Pflicht):
            {$context->styleguide}

            Belegte Fakten:
            {$vars['fact_snippets']}

            Vorgesehene interne Linkziele:
            {$vars['internal_link_targets']}

            Zu bewertender Artikeltext:
            {$vars['section']}

            Melden Sie in blocking_issues jeden dieser Faelle, sofern zutreffend: unbelegte Zahl,
            Widerspruch zu einem Faktenschnipsel, fehlender YMYL-Disclaimer, Superlativ- oder
            Werbesprache, Doorway-Muster (Region nur im Titel, nicht im Inhalt).
            TEXT;
    }

    /**
     * Der Artikel, wie das Modell ihn sieht: mit Abschnittskennungen, damit
     * es seine Korrekturhinweise verorten kann.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     */
    private function articleText(ArticleDraft $draft, array $sections): string
    {
        $lines = [
            'Titel (H1): '.(string) $draft->title,
            'Meta-Title: '.(string) $draft->meta_title,
            'Meta-Description: '.(string) $draft->meta_description,
            'Kurzantwort: '.(string) $draft->short_answer,
            '',
        ];

        foreach ($sections as $section) {
            $lines[] = "[{$section['id']}] H2: ".($section['heading'] !== '' ? $section['heading'] : '(ohne Ueberschrift)');

            if ($section['summary'] !== '') {
                $lines[] = 'Fazit-Satz: '.$section['summary'];
            }

            $lines[] = $section['body'];
            $lines[] = '';
        }

        foreach ((array) ($draft->faq_json ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $lines[] = 'FAQ: '.(string) ($entry['question'] ?? '').' — '.(string) ($entry['answer'] ?? '');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function criteria(array $result): array
    {
        $criteria = [];

        foreach ((array) ($result['per_criterion'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $criteria[] = [
                'criterion' => (string) ($entry['criterion'] ?? ''),
                'label' => __(ucfirst(str_replace('_', ' ', (string) ($entry['criterion'] ?? '')))),
                'weight' => (float) ($entry['weight'] ?? 0),
                'score' => (float) ($entry['score'] ?? 0),
                'comment' => (string) ($entry['comment'] ?? ''),
                'reason' => (string) ($entry['comment'] ?? ''),
            ];
        }

        return $criteria;
    }

    /**
     * Nur Hinweise auf Abschnitte, die es wirklich gibt. Eine erfundene
     * Kennung wuerde sonst im Fix-Durchlauf ins Leere greifen.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     * @return array<int, array{section_id: string, instruction: string}>
     */
    private function fixInstructions(array $result, array $sections): array
    {
        $ids = array_column($sections, 'id');
        $instructions = [];

        foreach ((array) ($result['fix_instructions'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = trim((string) ($entry['section_id'] ?? ''));
            $text = trim((string) ($entry['instruction'] ?? ''));

            if ($text === '') {
                continue;
            }

            $instructions[] = [
                'section_id' => in_array($id, $ids, true) ? $id : '',
                'instruction' => $text,
            ];
        }

        return $instructions;
    }
}
