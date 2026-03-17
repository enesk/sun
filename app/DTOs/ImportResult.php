<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ImportResult
{
    public function __construct(
        public int $imported = 0,
        public int $skipped = 0,
        public int $updated = 0,
        public array $errors = [],
    ) {}
}
