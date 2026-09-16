<?php

declare(strict_types=1);

namespace App\Dto\Leads;

/**
 * Antwortoption einer Funnel-Frage (#25). value ist der Schluessel, der an das
 * Leadsystem zurueckgeht, label der angezeigte Text.
 */
final readonly class FunnelOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $position,
        public ?string $imagePath,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: (string) ($data['value'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            position: (int) ($data['position'] ?? 0),
            imagePath: isset($data['image_path']) ? (string) $data['image_path'] : null,
        );
    }
}
