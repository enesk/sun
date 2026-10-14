<?php

namespace App\Analytics;

/**
 * Die aufgeloesten Messkennungen eines Requests (#14).
 *
 * Beide Werte sind entweder eine geprueft aussehende Kennung oder null —
 * "null" heisst: dieses Portal messt nicht, es wird auch kein Skript geladen.
 */
readonly class AnalyticsSettings
{
    public function __construct(
        public ?string $gaId = null,
        public ?string $gtmId = null,
    ) {}

    public function measures(): bool
    {
        return $this->gaId !== null || $this->gtmId !== null;
    }
}
