<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public static function ok(): self
    {
        return new self(valid: true);
    }

    public static function failed(array $errors): self
    {
        return new self(valid: false, errors: $errors);
    }
}
