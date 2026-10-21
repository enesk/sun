<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ai;

use App\ProfileRequests\Enums\RequestType;

/**
 * Ergebnis eines Klassifikationslaufs (SUN-RQ #6).
 *
 * Bewusst ein eigener Typ und kein Array: der Aufrufer (ClassifyRequest)
 * schreibt daraus Spalten und Diff-Zeilen, und ein Tippfehler in einem
 * Array-Schluessel waere dort eine stille Fehlzuordnung.
 *
 * `$changes` ist der **Vorschlag** fuer `request_changes` — Feld, alter und
 * neuer Wert, nichts davon ist uebernommen. `$replyDraft` liegt nur im Panel.
 * Der KI-Schritt schreibt nie ins Profil und versendet nie eine Mail.
 */
final readonly class ClassificationResult
{
    /**
     * @param  array<int, array{field: string, old: mixed, new: mixed}>  $changes
     * @param  array<int, array<string, mixed>>  $candidates  Liste, aus der gewaehlt wurde
     */
    public function __construct(
        public RequestType $type,
        public ?int $tenantId,
        public ?int $companyId,
        public float $confidence,
        public ?string $summary,
        public ?string $replyDraft,
        public ?string $deleteReason,
        public array $changes,
        public array $candidates,
        public bool $fallback,
        public ?string $fallbackReason = null,
    ) {}

    /**
     * Antwort war unbrauchbar: Typ `other`, keine Zuordnung, nichts
     * vorgeschlagen. Die Anfrage wird trotzdem eingeordnet und landet in der
     * Inbox — eine Mail, die die KI nicht versteht, ist Handarbeit, kein
     * Fehlerfall.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public static function fallback(string $reason, array $candidates = [], ?int $tenantId = null): self
    {
        return new self(
            type: RequestType::OTHER,
            tenantId: $tenantId,
            companyId: null,
            confidence: 0.0,
            summary: null,
            replyDraft: null,
            deleteReason: null,
            changes: [],
            candidates: $candidates,
            fallback: true,
            fallbackReason: $reason,
        );
    }

    public function isConfident(): bool
    {
        return $this->confidence >= (float) config('profile-requests.classification.min_confidence', 0.7);
    }
}
