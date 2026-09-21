<?php

declare(strict_types=1);

namespace App\Guide\Import;

/**
 * Uebersetzt die Spalte "headings" einer Themenliste in outline_json.
 *
 *   "Kosten | > Material | > Arbeitszeit | Foerderung"
 *
 * `|` trennt die Eintraege, jeder Eintrag ist eine H2; ein fuehrendes `>`
 * macht ihn zur H3 unter der vorherigen H2. Ergebnis in der Form von
 * guide_topics.outline_json:
 * [{id: 's1', level: 2, heading: 'Kosten', children: [{id: 's1-1', level: 3, heading: 'Material'}, …]}, …]
 */
class HeadingsParser
{
    public const MAX_HEADING_LENGTH = 200;

    /**
     * @return array<int, array{id: string, level: int, heading: string, children: array<int, array{id: string, level: int, heading: string}>}>
     *
     * @throws InvalidTopicRow bei einer H3 ohne vorherige H2 oder einer zu langen Ueberschrift
     */
    public function parse(string $headings): array
    {
        /** @var array<int, array{id: string, level: int, heading: string, children: array<int, array{id: string, level: int, heading: string}>}> $outline */
        $outline = [];

        foreach (explode('|', $headings) as $segment) {
            $segment = trim($segment);
            $isSubheading = str_starts_with($segment, '>');
            $heading = trim(ltrim($segment, '>'));

            if ($heading === '') {
                continue;
            }

            if (mb_strlen($heading) > self::MAX_HEADING_LENGTH) {
                throw new InvalidTopicRow(sprintf('Überschrift länger als %d Zeichen: "%s…"', self::MAX_HEADING_LENGTH, mb_substr($heading, 0, 40)));
            }

            if (! $isSubheading) {
                $outline[] = [
                    'id' => 's'.(count($outline) + 1),
                    'level' => 2,
                    'heading' => $heading,
                    'children' => [],
                ];

                continue;
            }

            $parent = array_key_last($outline);

            if ($parent === null) {
                throw new InvalidTopicRow("Unterüberschrift \"{$heading}\" steht vor der ersten Überschrift.");
            }

            $outline[$parent]['children'][] = [
                'id' => $outline[$parent]['id'].'-'.(count($outline[$parent]['children']) + 1),
                'level' => 3,
                'heading' => $heading,
            ];
        }

        return $outline;
    }
}
