<?php

declare(strict_types=1);

namespace App\Content\Sources;

/**
 * Ergebnis eines Connector-Laufs fuer genau einen Mandanten (#7).
 */
final readonly class SourceRunResult
{
    private function __construct(
        public string $connectorKey,
        public int $tenantId,
        public int $fetched,
        public int $stored,
        public int $duplicates,
        public bool $skipped,
        public ?string $error,
    ) {}

    public static function success(string $connectorKey, int $tenantId, int $fetched, int $stored, int $duplicates): self
    {
        return new self($connectorKey, $tenantId, $fetched, $stored, $duplicates, false, null);
    }

    public static function skipped(string $connectorKey, int $tenantId, string $reason): self
    {
        return new self($connectorKey, $tenantId, 0, 0, 0, true, $reason);
    }

    public static function failure(string $connectorKey, int $tenantId, string $error): self
    {
        return new self($connectorKey, $tenantId, 0, 0, 0, false, $error);
    }

    public function failed(): bool
    {
        return ! $this->skipped && $this->error !== null;
    }

    public function summary(): string
    {
        if ($this->skipped) {
            return "uebersprungen ({$this->error})";
        }

        if ($this->failed()) {
            return "Fehler: {$this->error}";
        }

        return "{$this->fetched} Signale, {$this->stored} neu, {$this->duplicates} Duplikate";
    }
}
