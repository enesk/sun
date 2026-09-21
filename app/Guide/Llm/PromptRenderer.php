<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Models\Central\PromptTemplate;

/**
 * Baut aus einem Prompt-Template die Nachrichtenteile fuer die Messages API (#5).
 *
 * Die {{var}}-Syntax bleibt beim bewaehrten Renderer der Content-Pipeline
 * (Umzug nach app/Guide in #19). Hier kommt nur die Aufteilung fuer das
 * Prompt-Caching dazu: System-Prompt und Branchen-Styleguide werden als
 * getrennte System-Bloecke mit cache_control gesendet. Beide sind je Template
 * bzw. je Branche identisch; alles Themenspezifische steht im User-Prompt.
 *
 * Der Styleguide kommt aus der Variablen 'styleguide'. Verwendet der
 * System-Prompt den Platzhalter {{styleguide}} selbst, bleibt es bei einem
 * Block.
 */
final class PromptRenderer
{
    public const STYLEGUIDE_VAR = 'styleguide';

    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $vars
     * @return array{system: array<int, array<string, mixed>>, user: string}
     */
    public function render(PromptTemplate $template, array $vars, bool $cache = true): array
    {
        $prompts = $this->renderer->renderTemplate($template, $vars);

        $texts = [];

        if ($prompts['system'] !== null) {
            $texts[] = $prompts['system'];
        }

        $styleguide = $vars[self::STYLEGUIDE_VAR] ?? null;

        if (is_string($styleguide) && trim($styleguide) !== '' && ! $this->systemUsesStyleguide($template)) {
            $texts[] = "Styleguide:\n\n".$styleguide;
        }

        return [
            'system' => $this->systemBlocks($texts, $cache),
            'user' => $prompts['user'],
        ];
    }

    /**
     * System-Bloecke ohne Template, etwa fuer guide:llm:ping.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<string, mixed>>
     */
    public function systemBlocks(array $texts, bool $cache = true): array
    {
        $blocks = [];

        foreach ($texts as $text) {
            if (trim($text) === '') {
                continue;
            }

            $block = ['type' => 'text', 'text' => $text];

            if ($cache) {
                $block['cache_control'] = ['type' => 'ephemeral'];
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    private function systemUsesStyleguide(PromptTemplate $template): bool
    {
        return in_array(
            self::STYLEGUIDE_VAR,
            $this->renderer->placeholders((string) $template->system_prompt),
            true,
        );
    }
}
