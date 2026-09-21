<?php

declare(strict_types=1);

namespace App\Guide\Dto;

/**
 * Ergebnis der Freshness-Probe (#8). `unchanged` ist die Entscheidung:
 * keine Aenderung gemeldet und confidence >= guide.research.probe_confidence_min.
 */
final class ProbeResult
{
    /**
     * @param  array<string, mixed>  $data  Inhalt von guide_topic_runs.probe_json
     */
    public function __construct(
        public readonly bool $changed,
        public readonly float $confidence,
        public readonly bool $unchanged,
        public readonly array $data,
        public readonly float $costUsd,
    ) {}
}
