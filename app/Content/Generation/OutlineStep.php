<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;
use Illuminate\Support\Str;

/**
 * Gliederung und Arbeitstitel (#14).
 *
 * Die H2 folgen den Nebenfragen aus People-Also-Ask und dem Fragencluster des
 * Themas (#12); die Wettbewerber-H2 dienen der Einordnung, nicht als Vorlage.
 * Der erste Abschnitt beantwortet die Suchintention — sonst faellt der Artikel
 * im Kriterium 'suchintention' der Rubrik durch (#15).
 *
 * Die Stufe liefert eine flache Liste aus H2 und H3 in Lesereihenfolge. Der
 * Zuschnitt in Abschnitte macht `sections()`: jede H2 beginnt einen Abschnitt,
 * die folgenden H3 gehoeren dazu. Ein Abschnitt ist damit genau eine
 * Schreibaufgabe fuer SectionStep und bleibt unter ~2.000 Ausgabe-Tokens.
 */
class OutlineStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'outline';
    }

    /**
     * @return array{title: string, items: array<int, array{heading: string, level: int, key_points: array<int, string>}>, sections: array<int, array{heading: string, subheadings: array<int, string>, key_points: array<int, string>, target_words: int}>}
     */
    public function run(GenerationContext $context, ArticleDraft $draft): array
    {
        $result = $this->ask($context, $draft);

        $items = [];

        foreach ((array) ($result['outline'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $heading = trim((string) ($item['heading'] ?? ''));

            if ($heading === '') {
                continue;
            }

            $items[] = [
                'heading' => $heading,
                'level' => (int) ($item['level'] ?? 2) === 3 ? 3 : 2,
                'key_points' => array_values(array_filter(array_map(
                    static fn ($point): string => is_string($point) ? trim($point) : '',
                    (array) ($item['key_points'] ?? []),
                ))),
            ];
        }

        // Die erste Ueberschrift ist immer eine H2: eine H3 ohne uebergeordnete
        // H2 waere ein Gliederungsfehler und haette keinen Abschnitt.
        if ($items !== [] && $items[0]['level'] === 3) {
            $items[0]['level'] = 2;
        }

        $title = trim((string) ($result['title'] ?? ''));

        return [
            'title' => $title !== '' ? Str::limit($title, 160, '') : $context->topic->title,
            'items' => $items,
            'sections' => $this->sections($items, $context),
        ];
    }

    /**
     * Abschnitte im Zuschnitt einer Schreibaufgabe, mit Wortbudget.
     *
     * Das Budget verteilt die Ziellaenge gleichmaessig auf die H2, bleibt aber
     * im Korridor aus config('content.generation.section_words'). Ein Abschnitt
     * mit H3-Unterpunkten bekommt Zuschlag, weil er mehr Stoff traegt.
     *
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $items
     * @return array<int, array{heading: string, subheadings: array<int, string>, key_points: array<int, string>, target_words: int}>
     */
    private function sections(array $items, GenerationContext $context): array
    {
        $sections = [];

        foreach ($items as $item) {
            if ($item['level'] === 2) {
                $sections[] = [
                    'heading' => $item['heading'],
                    'subheadings' => [],
                    'key_points' => $item['key_points'],
                    'target_words' => 0,
                ];

                continue;
            }

            if ($sections === []) {
                continue;
            }

            $last = count($sections) - 1;
            $sections[$last]['subheadings'][] = $item['heading'];
            $sections[$last]['key_points'] = array_merge(
                $sections[$last]['key_points'],
                $item['key_points'],
            );
        }

        if ($sections === []) {
            return [];
        }

        $corridor = (array) config('content.generation.section_words', ['min' => 80, 'max' => 220]);
        $min = max(40, (int) ($corridor['min'] ?? 80));
        $max = max($min, (int) ($corridor['max'] ?? 220));

        // Der Regionalblock traegt einen Teil der Ziellaenge und wird deshalb
        // vom Budget der Abschnitte abgezogen.
        $reserved = $context->isRegional() ? 150 : 0;
        $target = max($min, (int) round((($context->targetWords['min'] + $context->targetWords['max']) / 2 - $reserved) / count($sections)));

        foreach ($sections as $index => $section) {
            $bonus = count($section['subheadings']) * 40;
            $sections[$index]['target_words'] = min($max, $target + $bonus);
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'outline'],
            'properties' => [
                // Der Artikeltitel traegt die H1. 70 Zeichen sind die
                // Toleranz; der Titel der Suchergebnisseite entsteht getrennt
                // im MetaStep und ist hart auf 60 Zeichen begrenzt.
                'title' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 70],
                'outline' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 12,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['heading', 'level', 'key_points'],
                        'properties' => [
                            'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                            'level' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 3],
                            'key_points' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 6,
                                'items' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 240],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        $regional = $context->isRegional()
            ? "- Der Regionalbezug auf {$context->regionName} steht in einem eigenen Abschnitt und "
                ."zusaetzlich im Fliesstext. Keine Ueberschrift, die nur den Ortsnamen anhaengt.\n"
            : "- Das Thema ist bundesweit. Nennen Sie keinen Ort in den Ueberschriften.\n";

        return "Regeln fuer die Ausgabe:\n"
            ."- Die erste H2 beantwortet die Suchintention '{$context->intent}' unmittelbar.\n"
            ."- Vier bis acht H2; H3 nur, wo ein Abschnitt wirklich zwei Teilfragen traegt.\n"
            .'- Jede H2 traegt eine Frage oder ein Anliegen aus der PAA-Liste oder dem Thema, '
            ."keine Allgemeinplaetze wie 'Fazit' oder 'Einleitung'.\n"
            .$regional
            ."- key_points sind Stichpunkte fuer die Schreibstufe, keine ausformulierten Saetze.\n"
            .'- title ist der Artikeltitel (H1), moeglichst unter 60 Zeichen und hoechstens 70. '
            .'Er enthaelt das Haupt-Keyword und ist kein Meta-Title.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Erstellen Sie Titel und Gliederung (H2/H3) fuer einen Ratgeberartikel der Branche
            {$context->branchLabel} auf {$context->tenantName}.

            Haupt-Keyword: {$context->primaryKeyword}
            Weitere Keywords: {$vars['secondary_keywords']}
            Suchintention: {$context->intent}
            Regionsbezug: {$context->regionScope} ({$context->regionName})
            Fragen von Nutzer:innen (People-Also-Ask): {$vars['serp_paa']}
            Ueberschriften der Wettbewerber (nur zur Einordnung): {$vars['competitor_h2s']}
            Belegte Fakten:
            {$vars['fact_snippets']}
            Interne Linkziele:
            {$vars['internal_link_targets']}
            Saisonanlass: {$vars['seasonal_hook']}
            Nachrichtenanlass: {$vars['news_hook']}
            TEXT;
    }
}
