<?php

declare(strict_types=1);

namespace App\Guide\Dto;

use App\Guide\Enums\RunMode;

/**
 * Ergebnis der Aenderungserkennung (#9, App\Guide\Research\ChangeDetector).
 *
 * `changedFacts` hat die Form von {{changed_facts}} (docs/guide-prompts.md §2)
 * plus `change` (added|changed|removed). `changedSectionIds` stehen in
 * Lesereihenfolge der Gliederung und landen in
 * guide_topic_runs.changed_section_ids_json.
 */
final class ChangeSet
{
    /**
     * @param  array<int, array<string, mixed>>  $changedFacts
     * @param  array<int, string>  $changedSectionIds
     * @param  array<string, string>  $assigned  fact_key => section_id, in diesem Lauf zugeordnet
     */
    public function __construct(
        public readonly RunMode $mode,
        public readonly array $changedFacts = [],
        public readonly array $changedSectionIds = [],
        public readonly bool $keyFactsAffected = false,
        public readonly array $assigned = [],
        public readonly bool $forced = false,
        public readonly ?string $assignmentError = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'changed_section_ids' => $this->changedSectionIds,
            'key_facts_affected' => $this->keyFactsAffected,
            'assigned' => $this->assigned,
            'forced' => $this->forced,
            'assignment_error' => $this->assignmentError,
        ];
    }
}
