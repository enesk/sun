<?php

declare(strict_types=1);

namespace App\Services\Leads;

/**
 * Prueft die Signatur eingehender Webhooks des Leadsystems (#31).
 *
 * Gegenstueck zu App\Services\WebhookSigner im Leadsystem: HMAC-SHA256 ueber
 * "<timestamp>.<rumpf>", Header X-Funnel-Signature / X-Funnel-Timestamp /
 * X-Funnel-Event. Der Zeitstempel steckt im Hash, deshalb schuetzt das
 * Zeitfenster vor wiedereingespielten Zustellungen.
 */
final class LeadWebhookSignature
{
    public const SIGNATURE_HEADER = 'X-Funnel-Signature';

    public const TIMESTAMP_HEADER = 'X-Funnel-Timestamp';

    public const EVENT_HEADER = 'X-Funnel-Event';

    /** Erlaubte Abweichung des Zeitstempels in Sekunden. */
    public const TOLERANCE = 300;

    public function sign(string $secret, string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public function isFresh(int $timestamp, ?int $now = null): bool
    {
        return abs(($now ?? time()) - $timestamp) <= self::TOLERANCE;
    }

    public function verify(string $secret, string $payload, int $timestamp, string $signature): bool
    {
        return hash_equals($this->sign($secret, $payload, $timestamp), $signature);
    }
}
