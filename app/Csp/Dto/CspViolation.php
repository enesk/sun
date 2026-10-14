<?php

declare(strict_types=1);

namespace App\Csp\Dto;

/**
 * Ein einzelner Verstoss aus einem CSP-Bericht (#51), schon normalisiert:
 * Schluessel beider Berichtsformate sind zusammengefuehrt, URLs sind ohne
 * Query und Fragment und auf Spaltenbreite gekuerzt.
 *
 * Gebaut wird das ausschliesslich von {@see \App\Csp\Support\CspReportPayload}.
 */
readonly class CspViolation
{
    public function __construct(
        public string $violatedDirective,
        public ?string $effectiveDirective,
        public string $blockedUri,
        public string $documentUri,
        public ?string $disposition,
        public ?string $sourceFile,
        public ?int $lineNumber,
        public ?int $columnNumber,
    ) {}
}
