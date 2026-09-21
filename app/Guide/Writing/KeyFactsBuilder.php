<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Models\Fact;
use App\Guide\Research\SectionFactMap;

/**
 * Key-Facts-Tabelle eines Ratgebers (#10). Entsteht ohne Modellaufruf
 * ausschliesslich aus guide_facts: Eine Tabelle ist die Stelle, an der eine
 * erfundene Zahl am glaubwuerdigsten aussaehe.
 *
 * Zeile: {key, label, value, unit, valid_from, source: {publisher, url}} —
 * die Form, die App\Guide\Services\GuidePageData::keyFacts() liest.
 * Reihenfolge: im Text verwendete Fakten mit Zahl, dann uebrige Fakten mit
 * Zahl, dann der Rest; hoechstens guide.writing.key_facts_rows Zeilen.
 */
class KeyFactsBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(WritingContext $context, SectionFactMap $map): array
    {
        $used = array_flip(array_merge([], ...array_values($map->sections)));

        $ranked = $context->facts
            ->sortBy(fn (Fact $fact): array => [
                isset($used[$fact->key]) && $this->hasNumber($fact) ? 0 : ($this->hasNumber($fact) ? 1 : 2),
                $fact->key,
            ])
            ->take($this->limit());

        return $ranked->map(fn (Fact $fact): array => $this->row($fact))->values()->all();
    }

    /**
     * Update-Modus: Nur Zeilen geaenderter Fakten werden ersetzt (oder
     * entfallen, wenn der Fakt nicht mehr aktuell ist); geaenderte Fakten,
     * die die Aenderungserkennung der Tabelle zugeschlagen hat
     * (SectionFactMap::keyFacts), kommen dazu. Ist nichts davon betroffen,
     * bleibt die Tabelle unveraendert.
     *
     * @param  array<int, array<string, mixed>>  $previous
     * @param  array<int, string>  $changedKeys
     * @return array{affected: bool, rows: array<int, array<string, mixed>>}
     */
    public function refresh(array $previous, WritingContext $context, array $changedKeys, SectionFactMap $map): array
    {
        $existingKeys = array_values(array_filter(array_map(fn (mixed $row): string => is_array($row) ? (string) ($row['key'] ?? '') : '', $previous)));
        $additional = array_values(array_diff(array_intersect($changedKeys, $map->keyFacts), $existingKeys));
        $replaced = array_intersect($changedKeys, $existingKeys);

        if ($previous === [] && $context->facts->isNotEmpty()) {
            return ['affected' => true, 'rows' => $this->build($context, $map)];
        }

        if ($replaced === [] && $additional === []) {
            return ['affected' => false, 'rows' => $previous];
        }

        $rows = [];

        foreach ($previous as $row) {
            $key = is_array($row) ? (string) ($row['key'] ?? '') : '';

            if (! in_array($key, $changedKeys, true)) {
                $rows[] = $row;

                continue;
            }

            $fact = $context->fact($key);

            if ($fact !== null) {
                $rows[] = $this->row($fact);
            }
        }

        foreach ($additional as $key) {
            $fact = $context->fact($key);

            if ($fact !== null && count($rows) < $this->limit()) {
                $rows[] = $this->row($fact);
            }
        }

        return ['affected' => true, 'rows' => $rows];
    }

    /**
     * @param  array<int, mixed>|null  $rows
     * @return array<int, string>
     */
    public function keys(?array $rows): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $row): string => is_array($row) ? trim((string) ($row['key'] ?? '')) : '',
            $rows ?? [],
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Fact $fact): array
    {
        return [
            'key' => (string) $fact->key,
            'label' => (string) $fact->label,
            'value' => (string) $fact->value,
            'unit' => $fact->unit,
            'valid_from' => $fact->valid_from?->toDateString(),
            'source' => [
                'publisher' => $fact->source?->publisher,
                'url' => $fact->source?->url,
            ],
        ];
    }

    private function hasNumber(Fact $fact): bool
    {
        return preg_match('/\d/', (string) $fact->value) === 1;
    }

    private function limit(): int
    {
        return max(1, (int) config('guide.writing.key_facts_rows', 8));
    }
}
