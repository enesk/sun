<?php

declare(strict_types=1);

namespace App\Guide\Import;

/**
 * Ergebnis eines Imports (TopicListImporter) oder einer Zuweisung an ein
 * Portal (TopicListAssigner).
 *
 * `line` in errors ist beim Import die Zeile der Quelle (1-basiert, Kopfzeile
 * mitgezaehlt), bei der Zuweisung die Position des Listen-Items.
 */
class ImportReport
{
    public int $imported = 0;

    /** Bereits vorhandene Eintraege, deren notes/priority usw. uebernommen wurden. */
    public int $updated = 0;

    public int $skippedDuplicates = 0;

    /**
     * @var array<int, array{line: int, reason: string}>
     */
    public array $errors = [];

    public function addError(int $line, string $reason): void
    {
        $this->errors[] = ['line' => $line, 'reason' => $reason];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{imported: int, updated: int, skipped_duplicates: int, errors: array<int, array{line: int, reason: string}>}
     */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped_duplicates' => $this->skippedDuplicates,
            'errors' => $this->errors,
        ];
    }
}
