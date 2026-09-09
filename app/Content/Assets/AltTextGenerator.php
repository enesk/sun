<?php

declare(strict_types=1);

namespace App\Content\Assets;

use App\Content\Llm\LlmClient;
use App\Content\Llm\LlmContext;
use App\Content\Llm\PromptRenderer;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\PromptTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alt-Text des Titelbilds (#16).
 *
 * Derselbe Weg wie in den Generierungsstufen (#14): der redaktionelle Teil des
 * Prompts kommt aus `prompt_templates` (Schluessel `image_alt`) und ist im
 * Content-Panel editierbar, das Ausgabeschema gehoert dem Code. Ist das
 * Template nicht vorhanden oder nicht renderbar, traegt ein eingebauter
 * Prompt den Aufruf.
 *
 * Scheitert auch der Modellaufruf, greift ein deterministischer Ersatztext aus
 * Titel und Region. Ein Bild ohne alt-Attribut waere ein Barrierefreiheits-
 * und SEO-Mangel — das darf kein Providerausfall verursachen.
 */
class AltTextGenerator
{
    public const TEMPLATE_KEY = 'image_alt';

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptRenderer $renderer,
    ) {}

    /**
     * @param  array{source: string, prompt: ?string, description: ?string}  $image
     */
    public function generate(ArticleDraft $draft, string $branchLabel, array $image, ?LlmContext $context = null): string
    {
        $context ??= LlmContext::forDraft($draft, self::TEMPLATE_KEY);
        $max = max(60, (int) config('content.assets.alt_text.max_chars', 125));
        $vars = $this->vars($draft, $branchLabel, $image, $max);

        try {
            [$system, $user] = $this->prompt($context->tenantId, $vars);

            $result = $this->llm->emit(
                $system,
                $user,
                $this->schema($max),
                $context,
                self::TEMPLATE_KEY,
            );

            $alt = trim((string) ($result['alt_text'] ?? ''));
        } catch (Throwable $exception) {
            Log::warning('Alt-Text nicht erzeugbar, Ersatztext verwendet.', [
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $alt = '';
        }

        return Str::limit($alt !== '' ? $alt : $this->fallback($draft, $branchLabel), $max, '');
    }

    /**
     * System- und Nutzerprompt aus dem Template, sonst der eingebaute Text.
     *
     * @param  array<string, string>  $vars
     * @return array{0: ?string, 1: string}
     */
    private function prompt(?int $tenantId, array $vars): array
    {
        $template = PromptTemplate::query()->resolve(self::TEMPLATE_KEY, $tenantId)->first();

        if ($template !== null) {
            try {
                $rendered = $this->renderer->renderTemplate($template, $vars);

                return [$rendered['system'] ?? null, $rendered['user']];
            } catch (Throwable $exception) {
                Log::warning('Prompt-Template fuer den Alt-Text nicht renderbar, Ersatztext verwendet.', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return [null, <<<TEXT
            Formulieren Sie den Alternativtext (alt-Attribut) fuer das Titelbild eines
            deutschsprachigen Ratgeberartikels.

            Artikel: {$vars['title']}
            Branche: {$vars['branch']}
            Region: {$vars['region_name']}
            Thema in Stichworten: {$vars['primary_keyword']}
            Bildbeschreibung der Quelle: {$vars['image_description']}

            Regeln:
            - Hoechstens {$vars['max_chars']} Zeichen, ein Satz, ohne Punkt am Ende.
            - Beschreiben Sie, was zu sehen ist, nicht was der Artikel behauptet.
            - Beginnen Sie nicht mit "Bild von", "Foto von" oder "Darstellung von".
            - Keine Keyword-Haeufung, keine Werbesprache, keine Marken- oder Personennamen.
            TEXT];
    }

    /**
     * @param  array{source: string, prompt: ?string, description: ?string}  $image
     * @return array<string, string>
     */
    private function vars(ArticleDraft $draft, string $branchLabel, array $image, int $max): array
    {
        return [
            'title' => (string) $draft->title,
            'branch' => $branchLabel,
            'region_name' => $this->regionName($draft),
            'primary_keyword' => (string) ($draft->topicCandidate?->primary_keyword ?? $draft->title),
            'image_description' => $image['description']
                ?? $image['prompt']
                ?? 'Keine Beschreibung vorhanden; leiten Sie das Motiv aus Thema und Branche ab.',
            'image_source' => $image['source'],
            'max_chars' => (string) $max,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(int $max): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['alt_text'],
            'properties' => [
                'alt_text' => ['type' => 'string', 'minLength' => 15, 'maxLength' => $max],
            ],
        ];
    }

    /**
     * Ersatztext ohne Modell. Er beschreibt das Motiv so allgemein, wie es
     * ohne Bildkenntnis vertretbar ist, und bleibt damit ehrlich.
     */
    private function fallback(ArticleDraft $draft, string $branchLabel): string
    {
        $region = $this->regionName($draft);

        return "Symbolbild zum Thema {$draft->title} aus dem Bereich {$branchLabel}"
            .($region !== 'bundesweit' ? " in {$region}" : '');
    }

    private function regionName(ArticleDraft $draft): string
    {
        if ((string) $draft->region_scope === 'national' || $draft->region_code === null) {
            return 'bundesweit';
        }

        return (string) (config("content.regions.states.{$draft->region_code}.name") ?? $draft->region_code);
    }
}
