<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;
use Illuminate\Support\Str;

/**
 * Kurzantwort (#14) — der Absatz oberhalb des Inhaltsverzeichnisses.
 *
 * Sie ist der Teil, den Suchmaschinen als Featured Snippet und KI-Systeme als
 * Antwort uebernehmen, und zugleich der Anrisstext des Artikels
 * (ArticleMapper). Sie entsteht als eigener Aufruf und nicht aus dem ersten
 * Abschnitt: eine Kurzantwort ist eine Zusammenfassung des ganzen Artikels,
 * kein Anfang.
 */
class ShortAnswerStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'short_answer';
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     */
    public function run(GenerationContext $context, ArticleDraft $draft, array $outline): string
    {
        $result = $this->ask($context, $draft, [
            'outline' => collect($outline)->map(
                static fn (array $item): string => 'H'.$item['level'].': '.$item['heading']
            )->implode("\n"),
        ]);

        return Str::limit(trim((string) ($result['short_answer'] ?? '')), 500, '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['short_answer'],
            'properties' => [
                'short_answer' => ['type' => 'string', 'minLength' => 80, 'maxLength' => 500],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        return "Regeln fuer die Ausgabe:\n"
            ."- Zwei bis drei Saetze, ohne HTML.\n"
            ."- Der erste Satz beantwortet die Frage direkt, ohne Vorlauf.\n"
            .'- Keine Zahl, die nicht in der Faktenliste steht.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Formulieren Sie eine Kurzantwort auf die Suchintention {$context->intent} zum
            Haupt-Keyword {$context->primaryKeyword}.

            Gliederung:
            {$vars['outline']}

            Belegte Fakten:
            {$vars['fact_snippets']}
            TEXT;
    }
}
