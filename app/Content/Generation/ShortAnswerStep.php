<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;
use Illuminate\Support\Facades\Log;
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
    private const MAX_ATTEMPTS = 3;

    public function templateKey(): string
    {
        return 'short_answer';
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     */
    public function run(GenerationContext $context, ArticleDraft $draft, array $outline): string
    {
        $vars = [
            'outline' => collect($outline)->map(
                static fn (array $item): string => 'H'.$item['level'].': '.$item['heading']
            )->implode("\n"),
        ];

        $answer = '';

        // Bis zu drei Anlaeufe: eine Beschreibung des Vorgehens statt einer
        // Antwort (#42) ist kein Schemaverstoss, der SchemaValidator sieht sie
        // also nicht. Bleibt es dabei, blockiert der SEO-Lint den Entwurf.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $result = $this->ask($context, $draft, $vars);
            $answer = self::clean((string) ($result['short_answer'] ?? ''));

            if (! self::isMetaCommentary($answer)) {
                break;
            }

            Log::warning('Kurzantwort beschreibt das Vorgehen statt zu antworten, neuer Versuch.', [
                'draft_id' => $draft->getKey(),
                'attempt' => $attempt,
                'answer' => Str::limit($answer, 160),
            ]);
        }

        return Str::limit($answer, 500, '');
    }

    /**
     * Entfernt Vorsaetze wie 'Kurzantwort: "…"' samt umschliessender
     * Anfuehrungszeichen.
     */
    public static function clean(string $answer): string
    {
        $answer = trim($answer);
        $answer = (string) preg_replace('/^(kurzantwort|antwort|short answer)\s*:\s*/iu', '', $answer);

        if (preg_match('/^["„“»](.*)["“”«]$/su', $answer, $match) === 1) {
            $answer = $match[1];
        }

        return trim($answer);
    }

    /**
     * Beschreibt der Text sich selbst statt die Frage zu beantworten?
     */
    public static function isMetaCommentary(string $answer): bool
    {
        return preg_match(
            '/^(kurzantwort|die kurzantwort|hier ist|ich habe|diese antwort|der text)\b|\b(kurzantwort erstellt|beantwortet die suchintention|verweist mangels|im ersten satz)\b/iu',
            $answer,
        ) === 1;
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
