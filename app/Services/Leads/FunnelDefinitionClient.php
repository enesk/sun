<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Dto\Leads\FunnelDefinition;
use App\Support\TenantCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Laedt den veroeffentlichten Funnel eines Portals aus dem Leadsystem (#25).
 *
 * Zwei Cache-Eintraege je Portal und Token:
 *
 * - frisch: Ergebnis des letzten Abrufs (Snapshot 1 h, "nicht veroeffentlicht"
 *   5 min, Fehlschlag 1 min), damit nicht jeder Seitenaufruf die API fragt;
 * - letzter guter Stand: ohne Ablauf, gilt bei Timeout, Verbindungsfehler,
 *   5xx oder unlesbarer Antwort.
 *
 * 404 heisst "nicht (mehr) veroeffentlicht": kein Dialog, der letzte gute
 * Stand wird verworfen. Beide Schluessel tragen den Mandanten, sonst saehe ein
 * Portal das Formular eines anderen (siehe App\Support\TenantCache).
 */
class FunnelDefinitionClient
{
    private const STATE_OK = 'ok';

    private const STATE_MISSING = 'missing';

    private const STATE_FAILED = 'failed';

    /**
     * Funnel des Tokens fuer das laufende Portal; null = kein Dialog.
     */
    public function get(string $token, ?string $tenantKey = null): ?FunnelDefinition
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $tenantKey ??= TenantCache::tenantKey();
        $cached = Cache::get($this->freshKey($tenantKey, $token));

        if (is_array($cached)) {
            return $this->fromEntry($token, $cached);
        }

        return $this->load($token, $tenantKey);
    }

    /**
     * Verwirft beide Eintraege und laedt neu. Muss im Kontext des Portals
     * laufen, dessen Cache gemeint ist ($tenant->run()).
     */
    public function refresh(string $token, ?string $tenantKey = null): ?FunnelDefinition
    {
        $tenantKey ??= TenantCache::tenantKey();

        $this->forget($token, $tenantKey);

        return $this->get($token, $tenantKey);
    }

    public function forget(string $token, ?string $tenantKey = null): void
    {
        $tenantKey ??= TenantCache::tenantKey();
        $token = trim($token);

        Cache::forget($this->freshKey($tenantKey, $token));
        Cache::forget($this->lastGoodKey($tenantKey, $token));
    }

    private function load(string $token, string $tenantKey): ?FunnelDefinition
    {
        $config = (array) config('leads.funnel');
        $url = rtrim((string) config('leads.api_url'), '/').'/funnels/'.rawurlencode($token);

        try {
            $response = Http::acceptJson()
                ->timeout((int) ($config['timeout'] ?? 3))
                ->connectTimeout((int) ($config['timeout'] ?? 3))
                ->get($url);
        } catch (ConnectionException $exception) {
            return $this->fail($token, $tenantKey, "Leadsystem nicht erreichbar: {$exception->getMessage()}");
        }

        if ($response->status() === 404) {
            Cache::forget($this->lastGoodKey($tenantKey, $token));
            Cache::put(
                $this->freshKey($tenantKey, $token),
                ['state' => self::STATE_MISSING],
                (int) ($config['missing_ttl'] ?? 300),
            );

            return null;
        }

        if (! $response->successful()) {
            return $this->fail($token, $tenantKey, "Leadsystem antwortet mit HTTP {$response->status()}");
        }

        $payload = $this->payload($response);
        $fetchedAt = CarbonImmutable::now();

        try {
            $definition = FunnelDefinition::fromArray(
                $token,
                $payload,
                $fetchedAt,
                $this->systemFieldKeys(),
                static function (string $fieldKey, string $type) use ($tenantKey): void {
                    Log::warning('Funnel-Frage mit unbekanntem Typ uebersprungen', [
                        'tenant' => $tenantKey,
                        'field_key' => $fieldKey,
                        'type' => $type,
                    ]);
                },
            );
        } catch (InvalidArgumentException $exception) {
            return $this->fail($token, $tenantKey, "Funnel-Snapshot unlesbar: {$exception->getMessage()}");
        }

        $entry = [
            'state' => self::STATE_OK,
            'payload' => $payload,
            'fetched_at' => $fetchedAt->toIso8601String(),
        ];

        Cache::put($this->freshKey($tenantKey, $token), $entry, (int) ($config['fresh_ttl'] ?? 3600));
        Cache::forever($this->lastGoodKey($tenantKey, $token), $entry);

        return $definition;
    }

    /**
     * Fehlschlag: letzten guten Stand ausliefern und fuer kurze Zeit merken,
     * damit die folgenden Aufrufe nicht erneut auf den Timeout warten.
     */
    private function fail(string $token, string $tenantKey, string $reason): ?FunnelDefinition
    {
        $lastGood = Cache::get($this->lastGoodKey($tenantKey, $token));
        $fallback = is_array($lastGood) ? $lastGood : ['state' => self::STATE_FAILED];

        Log::warning('Funnel aus dem Leadsystem nicht geladen', [
            'tenant' => $tenantKey,
            'reason' => $reason,
            'fallback' => is_array($lastGood) ? ($lastGood['fetched_at'] ?? null) : null,
        ]);

        Cache::put(
            $this->freshKey($tenantKey, $token),
            $fallback,
            (int) config('leads.funnel.retry_after', 60),
        );

        return $this->fromEntry($token, $fallback);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function fromEntry(string $token, array $entry): ?FunnelDefinition
    {
        if (($entry['state'] ?? null) !== self::STATE_OK || ! is_array($entry['payload'] ?? null)) {
            return null;
        }

        try {
            return FunnelDefinition::fromArray(
                $token,
                $entry['payload'],
                CarbonImmutable::parse((string) ($entry['fetched_at'] ?? 'now')),
                $this->systemFieldKeys(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Die oeffentliche API liefert eine JsonResource mit 'data'-Huelle; ohne
     * Huelle wird die Antwort unveraendert genommen.
     *
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    /**
     * @return list<string>
     */
    private function systemFieldKeys(): array
    {
        return array_values(array_map('strval', (array) config('leads.funnel.system_field_keys', [])));
    }

    private function freshKey(string $tenantKey, string $token): string
    {
        return "leads.funnel.{$tenantKey}.".sha1($token);
    }

    private function lastGoodKey(string $tenantKey, string $token): string
    {
        return $this->freshKey($tenantKey, $token).'.last_good';
    }
}
