<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\AntiSpam\Enums\CandidateKind;

/**
 * Ergebnis eines Laufs fuer ein Portal (#10).
 */
final class ScanResult
{
    /**
     * @param  list<ScanHit>  $hits
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantName,
        public readonly int $threshold,
        public readonly bool $applied,
        public readonly int $accountsScanned = 0,
        public readonly int $listingsScanned = 0,
        public readonly array $hits = [],
        /** Treffer, die wegen Zahlung, Verifizierung oder Adminrolle nicht markiert wurden. */
        public readonly int $protectedSkipped = 0,
        /** Portal nicht auswertbar (Datenbank nicht erreichbar, Spalten fehlen). */
        public readonly ?string $error = null,
    ) {}

    public static function failed(int $tenantId, string $tenantName, int $threshold, string $error): self
    {
        return new self(
            tenantId: $tenantId,
            tenantName: $tenantName,
            threshold: $threshold,
            applied: false,
            error: $error,
        );
    }

    /**
     * @return list<ScanHit>
     */
    public function hitsOf(CandidateKind $kind): array
    {
        return array_values(array_filter($this->hits, static fn (ScanHit $hit): bool => $hit->kind === $kind));
    }

    public function accountHits(): int
    {
        return count($this->hitsOf(CandidateKind::Account));
    }

    public function listingHits(): int
    {
        return count($this->hitsOf(CandidateKind::Listing));
    }

    /**
     * Berichtszeile ohne personenbezogene Daten — Zahlen, IDs und Regelcodes.
     *
     * @return array<string, mixed>
     */
    public function toReport(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'tenant' => $this->tenantName,
            'threshold' => $this->threshold,
            'applied' => $this->applied,
            'accounts_scanned' => $this->accountsScanned,
            'listings_scanned' => $this->listingsScanned,
            'account_hits' => $this->accountHits(),
            'listing_hits' => $this->listingHits(),
            'protected_skipped' => $this->protectedSkipped,
            'error' => $this->error,
            'hits' => array_map(static fn (ScanHit $hit): array => [
                'kind' => $hit->kind->value,
                'id' => $hit->id,
                'score' => $hit->score->score,
                'reasons' => $hit->score->codes(),
                'created_at' => $hit->createdAt?->toIso8601String(),
                'marked' => $hit->marked,
            ], $this->hits),
        ];
    }
}
