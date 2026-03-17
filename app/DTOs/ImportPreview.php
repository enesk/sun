<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ImportPreview
{
    public function __construct(
        public int $slotCount,
        public array $positions,
        public array $conflicts,
        public int $newCount,
        public int $conflictCount,
        public int $existingSlotCount = 0,
    ) {}
}
