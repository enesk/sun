<?php

declare(strict_types=1);

namespace App\Guide\Dto;

/**
 * Ergebnis der Tiefenrecherche (#8). `currentFactCount` zaehlt alle aktuellen
 * Fakten des Themas nach dem Speichern, auch die nicht erneut bestaetigten.
 * `changes` ist das Ergebnis der Aenderungserkennung (#9).
 */
final class FactSet
{
    /**
     * @param  array<string, mixed>  $data  Inhalt von guide_topic_runs.research_json
     */
    public function __construct(
        public readonly array $data,
        public readonly int $currentFactCount,
        public readonly bool $factsChanged,
        public readonly string $factsHash,
        public readonly float $costUsd,
        public readonly ?ChangeSet $changes = null,
    ) {}
}
