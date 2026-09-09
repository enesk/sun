<?php

declare(strict_types=1);

namespace App\Content\Support;

use InvalidArgumentException;

/**
 * Kennung einer Karte im Pipeline-Board und im Redaktionskalender (#19).
 *
 * Board und Kalender zeigen Objekte aus 24 getrennten Tenant-Datenbanken
 * nebeneinander; eine blosse Zeilen-ID waere dort mehrdeutig. Die Karte
 * traegt deshalb Portal, Objektart und ID in einem Wert:
 *
 *   "7:draft:1042"  bzw.  "7:topic:88"
 */
final readonly class PipelineCardKey
{
    public const TYPE_DRAFT = 'draft';

    public const TYPE_TOPIC = 'topic';

    public function __construct(
        public int $tenantId,
        public string $type,
        public int $id,
    ) {
        if (! in_array($type, [self::TYPE_DRAFT, self::TYPE_TOPIC], true)) {
            throw new InvalidArgumentException("Unbekannte Kartenart '{$type}'.");
        }
    }

    public static function draft(int $tenantId, int $id): self
    {
        return new self($tenantId, self::TYPE_DRAFT, $id);
    }

    public static function topic(int $tenantId, int $id): self
    {
        return new self($tenantId, self::TYPE_TOPIC, $id);
    }

    public static function parse(string $value): self
    {
        $parts = explode(':', $value);

        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[2])) {
            throw new InvalidArgumentException("Ungueltige Kartenkennung '{$value}'.");
        }

        return new self((int) $parts[0], $parts[1], (int) $parts[2]);
    }

    public function isDraft(): bool
    {
        return $this->type === self::TYPE_DRAFT;
    }

    public function toString(): string
    {
        return "{$this->tenantId}:{$this->type}:{$this->id}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
