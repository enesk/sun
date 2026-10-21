<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

/**
 * Ergebnis eines Pipeline-Laufs (#8) — je Schritt eine Zeile, dazu die
 * Zusammenfassung fuer Aufrufer (Panel, Auto-Regel, Artisan) und
 * Bestaetigungsmail.
 */
final class DeletionResult
{
    /**
     * @param  array<string, StepResult>  $steps  Schluessel => Ergebnis, in Laufreihenfolge
     */
    public function __construct(
        public readonly string $runId,
        public readonly int $companyId,
        public readonly string $companyName,
        public readonly array $steps = [],
        public readonly bool $dryRun = false,
        public readonly int $durationMs = 0,
    ) {}

    public function ok(): bool
    {
        return $this->failedSteps() === [];
    }

    /**
     * @return array<string, StepResult>
     */
    public function failedSteps(): array
    {
        return array_filter($this->steps, fn (StepResult $result): bool => $result->isFailed());
    }

    /** Schritte, die etwas getan haben — die Liste fuer die Bestaetigungsmail. */
    public function completedKeys(): array
    {
        return array_keys(array_filter(
            $this->steps,
            fn (StepResult $result): bool => $result->status === StepStatus::OK,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $counts = ['ok' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($this->steps as $result) {
            $counts[$result->status->value]++;
        }

        return [
            'run_id' => $this->runId,
            'company_id' => $this->companyId,
            'steps' => count($this->steps),
            'ok' => $counts['ok'],
            'skipped' => $counts['skipped'],
            'failed' => $counts['failed'],
            'duration_ms' => $this->durationMs,
            'dry_run' => $this->dryRun,
        ];
    }
}
