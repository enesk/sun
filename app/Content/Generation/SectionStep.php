<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;

/**
 * Ein Abschnitt als Fliesstext (#14).
 *
 * Jeder Abschnitt entsteht in einem eigenen Aufruf. Das kostet mehr Requests,
 * haelt aber jede Ausgabe unter ~2.000 Tokens: ein Schema-Retry wiederholt
 * dann einen Abschnitt und nicht den ganzen Artikel.
 *
 * `summary_sentence` ist Pflicht und steht im Schema, nicht im Template. Er
 * wird als erster Absatz der H2 gerendert und ist damit die Antwort, die
 * Leser:innen und Suchmaschinen ohne Weiterlesen bekommen. Ein Abschnitt ohne
 * eigenstaendigen Fazit-Satz waere ein Verstoss gegen das Abnahmekriterium —
 * deshalb kann keine Panel-Aenderung ihn abschalten.
 */
class SectionStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'section_write';
    }

    /**
     * @param  array{heading: string, subheadings: array<int, string>, key_points: array<int, string>, target_words: int}  $section
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     * @return array{heading: string, summary_sentence: string, html: string, word_count: int, used_facts: array<int, int>, used_links: array<int, string>}
     */
    public function run(
        GenerationContext $context,
        ArticleDraft $draft,
        array $section,
        array $outline,
        int $position,
        int $total,
    ): array {
        $result = $this->ask($context, $draft, [
            'outline' => $this->outlineText($outline),
            'section' => $this->sectionText($section, $position, $total),
        ]);

        return [
            'heading' => trim((string) ($result['heading'] ?? '')) ?: $section['heading'],
            'summary_sentence' => trim((string) ($result['summary_sentence'] ?? '')),
            'html' => trim((string) ($result['html'] ?? '')),
            'word_count' => (int) ($result['word_count'] ?? 0),
            'used_facts' => array_values(array_unique(array_map(
                'intval',
                (array) ($result['used_fact_ids'] ?? []),
            ))),
            'used_links' => array_values(array_unique(array_filter(array_map(
                static fn ($url): string => is_string($url) ? trim($url) : '',
                (array) ($result['used_link_urls'] ?? []),
            )))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['heading', 'summary_sentence', 'html', 'word_count', 'used_fact_ids', 'used_link_urls'],
            'properties' => [
                'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],

                // Der Fazit-Satz des Abschnitts. Eigenstaendig lesbar, ohne
                // Rueckbezug auf die Ueberschrift.
                'summary_sentence' => ['type' => 'string', 'minLength' => 30, 'maxLength' => 300],

                'html' => ['type' => 'string', 'minLength' => 120],
                'word_count' => ['type' => 'integer', 'minimum' => 20, 'maximum' => 600],

                // Belegkette: welche Faktenschnipsel und Linkziele der
                // Abschnitt verwendet. Grundlage der draft_sources und der
                // Pruefung, ob die Pflichtlinks gesetzt sind.
                'used_fact_ids' => ['type' => 'array', 'maxItems' => 12, 'items' => ['type' => 'integer']],
                'used_link_urls' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string', 'maxLength' => 300]],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        $corridor = (array) config('content.generation.section_words', []);
        $min = (int) ($corridor['min'] ?? 80);

        return "Regeln fuer die Ausgabe:\n"
            .'- summary_sentence ist ein eigenstaendiger Fazit-Satz zu diesem Abschnitt. Er '
            .'beantwortet die Ueberschrift, ohne sie zu wiederholen, und steht spaeter als '
            ."erster Absatz. Keine Einleitungsfloskel.\n"
            .'- html enthaelt ausschliesslich <p>, <ul>, <ol>, <li>, <strong>, <em>, <h3> und <a>. '
            ."Keine <h2>: die Ueberschrift wird getrennt gesetzt. Der Fazit-Satz steht NICHT in html.\n"
            ."- Mindestens {$min} Woerter, und nicht mehr als das genannte Wortbudget.\n"
            .'- Jede Zahl, jeder Preis und jede Frist stammt aus der Faktenliste. Nennen Sie die '
            ."verwendeten Faktennummern in used_fact_ids (ohne das Praefix F).\n"
            .'- Interne Links setzen Sie als <a href="..."> mit genau der vorgegebenen URL und '
            ."einem beschreibenden Ankertext. Kein 'hier', kein 'mehr erfahren'. Die gesetzten "
            ."URLs nennen Sie in used_link_urls.\n"
            .'- word_count ist Ihre eigene Zaehlung der Woerter in html.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Formulieren Sie den folgenden Abschnitt der Gliederung als Fliesstext-HTML aus.

            Gesamte Gliederung (Kontext):
            {$vars['outline']}

            Auszuformulierender Abschnitt:
            {$vars['section']}

            Branche: {$context->branchLabel}, Portal: {$context->tenantName}
            Haupt-Keyword: {$context->primaryKeyword}
            Weitere Keywords: {$vars['secondary_keywords']}
            Regionsbezug: {$context->regionScope} ({$context->regionName})
            Belegte Fakten, die Sie zitieren duerfen:
            {$vars['fact_snippets']}
            Interne Linkziele:
            {$vars['internal_link_targets']}
            TEXT;
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     */
    private function outlineText(array $outline): string
    {
        return collect($outline)->map(
            static fn (array $item): string => str_repeat('  ', $item['level'] - 2)
                .'H'.$item['level'].': '.$item['heading']
        )->implode("\n");
    }

    /**
     * @param  array{heading: string, subheadings: array<int, string>, key_points: array<int, string>, target_words: int}  $section
     */
    private function sectionText(array $section, int $position, int $total): string
    {
        $lines = [
            "H2: {$section['heading']}",
            "Position: Abschnitt {$position} von {$total}",
            "Wortbudget: {$section['target_words']} Woerter",
        ];

        if ($section['subheadings'] !== []) {
            $lines[] = 'H3-Unterpunkte (als <h3> im HTML setzen): '.implode(' | ', $section['subheadings']);
        }

        if ($section['key_points'] !== []) {
            $lines[] = "Stichpunkte:\n- ".implode("\n- ", $section['key_points']);
        }

        return implode("\n", $lines);
    }
}
