<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Models\ArticleDetail;

/**
 * Welche Fakt-Schluessel in welchem Abschnitt der gesperrten Gliederung
 * stehen (#9). Gespeichert in guide_article_details.section_fact_map_json:
 *
 *   {
 *     "sections":  {"s1": ["foerdersatz", ...], "s2-1": [...]},
 *     "key_facts": ["foerdersatz", ...],
 *     "assigned":  {"antragsfrist": {"section_id": "s3", "run_id": 12, "at": "2026-09-21"}}
 *   }
 *
 * sections  schreibt der Writer (#10) je geschriebenem Abschnitt aus
 *           used_fact_keys; ein neu geschriebener Abschnitt ersetzt seine Liste.
 * key_facts Schluessel der Key-Facts-Tabelle.
 * assigned  Zuordnung geaenderter Fakten, die in keinem Abschnitt vorkamen
 *           (ChangeDetector, per structured()-Aufruf). Sie zaehlen beim
 *           naechsten Lauf wie eine Verwendung im Abschnitt.
 *
 * Unveraenderlich; jede with*-Methode liefert eine neue Instanz.
 */
final class SectionFactMap
{
    /**
     * @param  array<string, array<int, string>>  $sections
     * @param  array<int, string>  $keyFacts
     * @param  array<string, array{section_id: string, run_id?: int|null, at?: string|null}>  $assigned
     */
    public function __construct(
        public readonly array $sections = [],
        public readonly array $keyFacts = [],
        public readonly array $assigned = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $sections = [];

        foreach ((array) ($data['sections'] ?? []) as $sectionId => $keys) {
            $sections[(string) $sectionId] = self::keys((array) $keys);
        }

        $assigned = [];

        foreach ((array) ($data['assigned'] ?? []) as $key => $entry) {
            if (is_array($entry) && trim((string) ($entry['section_id'] ?? '')) !== '') {
                $assigned[(string) $key] = ['section_id' => (string) $entry['section_id']] + $entry;
            }
        }

        return new self($sections, self::keys((array) ($data['key_facts'] ?? [])), $assigned);
    }

    public static function forDetail(?ArticleDetail $detail): self
    {
        return self::fromArray($detail?->section_fact_map_json);
    }

    /**
     * Abschnitt neu geschrieben: seine Schluessel ersetzen die bisherigen.
     *
     * @param  array<int, string>  $factKeys  used_fact_keys aus guide.write_section
     */
    public function withSection(string $sectionId, array $factKeys): self
    {
        return new self([...$this->sections, $sectionId => self::keys($factKeys)], $this->keyFacts, $this->assigned);
    }

    /**
     * @param  array<int, string>  $factKeys
     */
    public function withKeyFacts(array $factKeys): self
    {
        return new self($this->sections, self::keys($factKeys), $this->assigned);
    }

    public function withAssignment(string $factKey, string $sectionId, ?int $runId = null, ?string $at = null): self
    {
        return new self(
            $this->sections,
            self::keys([...$this->keyFacts, $factKey]),
            [...$this->assigned, $factKey => ['section_id' => $sectionId, 'run_id' => $runId, 'at' => $at]],
        );
    }

    /**
     * Abschnitte, in denen der Fakt steht oder denen er zugeordnet wurde.
     *
     * @return array<int, string>
     */
    public function sectionsFor(string $factKey): array
    {
        $ids = [];

        foreach ($this->sections as $sectionId => $keys) {
            if (in_array($factKey, $keys, true)) {
                $ids[] = $sectionId;
            }
        }

        if (isset($this->assigned[$factKey])) {
            $ids[] = $this->assigned[$factKey]['section_id'];
        }

        return array_values(array_unique($ids));
    }

    public function isMapped(string $factKey): bool
    {
        return $this->sectionsFor($factKey) !== [];
    }

    public function inKeyFacts(string $factKey): bool
    {
        return in_array($factKey, $this->keyFacts, true);
    }

    /**
     * @return array{sections: array<string, array<int, string>>, key_facts: array<int, string>, assigned: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'sections' => $this->sections,
            'key_facts' => $this->keyFacts,
            'assigned' => $this->assigned,
        ];
    }

    public function saveTo(ArticleDetail $detail): void
    {
        $detail->forceFill(['section_fact_map_json' => $this->toArray()])->save();
    }

    /**
     * @param  array<int|string, mixed>  $keys
     * @return array<int, string>
     */
    private static function keys(array $keys): array
    {
        $clean = array_map(fn (mixed $key): string => trim((string) $key), $keys);

        return array_values(array_unique(array_filter($clean, fn (string $key): bool => $key !== '')));
    }
}
