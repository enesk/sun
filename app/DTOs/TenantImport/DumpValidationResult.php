<?php

declare(strict_types=1);

namespace App\DTOs\TenantImport;

readonly class DumpValidationResult
{
    public function __construct(
        public bool $valid,
        public array $foundTables = [],
        public array $missingTables = [],
        public array $warnings = [],
        public array $errors = [],
    ) {}

    public static function ok(array $foundTables, array $missingTables = [], array $warnings = []): self
    {
        return new self(
            valid: true,
            foundTables: $foundTables,
            missingTables: $missingTables,
            warnings: $warnings,
        );
    }

    public static function failed(array $errors): self
    {
        return new self(valid: false, errors: $errors);
    }
}
