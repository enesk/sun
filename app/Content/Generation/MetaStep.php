<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;
use Illuminate\Support\Str;

/**
 * Meta-Title und Meta-Description (#14).
 *
 * Die Grenzen 60 und 155 Zeichen stehen im Schema, damit das Modell sie
 * einhalten muss statt sie zu ueberschreiten und gekuerzt zu werden. Sie
 * werden trotzdem nachtraeglich hart beschnitten: ein gekuerzter Titel ist
 * besser als ein Entwurf, der an einer Zeichengrenze scheitert.
 *
 * Der Artikeltitel selbst kommt aus der Gliederung — dort steht der
 * inhaltliche Zuschnitt. Der Slug entsteht daraus im SlugService.
 */
class MetaStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'meta';
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     * @return array{meta_title: string, meta_description: string}
     */
    public function run(GenerationContext $context, ArticleDraft $draft, string $title, array $outline): array
    {
        $result = $this->ask($context, $draft, [
            'outline' => collect($outline)->map(
                static fn (array $item): string => 'H'.$item['level'].': '.$item['heading']
            )->implode("\n"),
        ]);

        $titleMax = max(20, (int) config('content.generation.title_max_chars', 60));
        $descriptionMax = max(80, (int) config('content.generation.meta_description_max_chars', 155));

        $metaTitle = trim((string) ($result['meta_title'] ?? '')) ?: $title;
        $metaDescription = trim((string) ($result['meta_description'] ?? ''));

        return [
            'meta_title' => Str::limit($metaTitle, $titleMax, ''),
            'meta_description' => Str::limit($metaDescription, $descriptionMax, ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        $titleMax = max(20, (int) config('content.generation.title_max_chars', 60));
        $descriptionMax = max(80, (int) config('content.generation.meta_description_max_chars', 155));

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['meta_title', 'meta_description'],
            'properties' => [
                'meta_title' => ['type' => 'string', 'minLength' => 10, 'maxLength' => $titleMax],
                'meta_description' => ['type' => 'string', 'minLength' => 40, 'maxLength' => $descriptionMax],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        $titleMax = (int) config('content.generation.title_max_chars', 60);
        $descriptionMax = (int) config('content.generation.meta_description_max_chars', 155);

        return "Regeln fuer die Ausgabe:\n"
            ."- meta_title hoechstens {$titleMax} Zeichen, meta_description hoechstens {$descriptionMax} Zeichen.\n"
            ."- Das Haupt-Keyword '{$context->primaryKeyword}' steht im meta_title.\n"
            .($context->isRegional()
                ? "- Die Region {$context->regionName} wird genau einmal genannt, nicht in Titel und Description doppelt.\n"
                : "- Kein Ortsname, das Thema ist bundesweit.\n")
            .'- Keine Anfuehrungszeichen, keine Superlative, kein Clickbait, keine Jahreszahl ohne Beleg.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Erzeugen Sie Meta-Title und Meta-Description fuer einen Ratgeberartikel der Branche
            {$context->branchLabel} auf {$context->tenantName}.

            Haupt-Keyword: {$context->primaryKeyword}
            Weitere Keywords: {$vars['secondary_keywords']}
            Regionsbezug: {$context->regionName}

            Gliederung:
            {$vars['outline']}
            TEXT;
    }
}
