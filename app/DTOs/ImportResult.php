<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ImportResult
{
    /**
     * @param  array<string, string>  $silentPositions  Positionen ohne Anker im Ziel-Theme: Schluessel => Beschriftung (Ticket #17)
     */
    public function __construct(
        public int $imported = 0,
        public int $skipped = 0,
        public int $updated = 0,
        public array $errors = [],
        public string $theme = '',
        public array $silentPositions = [],
    ) {}
}
