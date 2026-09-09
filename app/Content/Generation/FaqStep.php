<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;

/**
 * FAQ-Eintraege (#14).
 *
 * Grundlage sind die People-Also-Ask-Fragen aus der SERP-Analyse (#10) — also
 * Fragen, die Ratsuchende tatsaechlich stellen, nicht ausgedachte. Die
 * Antworten duerfen keine Zahl einfuehren, die im Artikel nicht belegt ist:
 * das FAQ-Markup landet als schema.org/FAQPage im Quelltext und wird von
 * Suchmaschinen und KI-Systemen direkt zitiert.
 */
class FaqStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'faq';
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     * @return array<int, array{question: string, answer: string}>
     */
    public function run(GenerationContext $context, ArticleDraft $draft, array $outline): array
    {
        $result = $this->ask($context, $draft, [
            'outline' => collect($outline)->map(
                static fn (array $item): string => 'H'.$item['level'].': '.$item['heading']
            )->implode("\n"),
        ]);

        $entries = [];

        foreach ((array) ($result['faq'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = trim((string) ($entry['question'] ?? ''));
            $answer = trim((string) ($entry['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $entries[] = ['question' => $question, 'answer' => $answer];
        }

        return array_slice($entries, 0, 6);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['faq'],
            'properties' => [
                'faq' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 200],
                            'answer' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 600],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        // Der Hinweis auf die Listenform steht hier, weil das Modell 'faq'
        // gelegentlich als JSON-Zeichenkette statt als Liste liefert. Der
        // Schema-Retry faengt das ab, kostet aber einen zweiten Aufruf.
        return "Regeln fuer die Ausgabe:\n"
            .'- faq ist eine Liste von Objekten mit den Feldern question und answer, '
            ."keine Zeichenkette und kein verschachteltes JSON.\n"
            ."- Bevorzugen Sie woertliche Fragen aus der PAA-Liste.\n"
            ."- Keine Frage wiederholt eine H2 der Gliederung.\n"
            ."- Antworten in zwei bis vier Saetzen, ohne HTML und ohne Aufzaehlung.\n"
            .'- Keine Zahl, die nicht in der Faktenliste steht.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Leiten Sie 3 bis 6 FAQ-Eintraege fuer einen Ratgeberartikel der Branche
            {$context->branchLabel} zum Haupt-Keyword {$context->primaryKeyword} ab.

            Nutzerfragen aus der Suche: {$vars['serp_paa']}

            Gliederung des Artikels:
            {$vars['outline']}

            Belegte Fakten:
            {$vars['fact_snippets']}
            TEXT;
    }
}
