<?php

declare(strict_types=1);

namespace App\Ads\Dto;

use App\Ads\Enums\AdDevice;
use App\Ads\Models\AdUnit;

/**
 * Die aufgeloeste Belegung eines Ankers (Ticket #4) — genau das, was in den
 * Cache geht.
 *
 * Kein Eloquent-Model im Cache: ein serialisiertes Model zieht Verbindung,
 * Casts und Beziehungen mit und bricht, sobald sich eine Spalte aendert. Hier
 * stehen nur Skalare; das Werbemittel wird fuer den Provider aus den Werten
 * wieder zusammengesetzt ({@see unit()}), ohne Datenbankzugriff.
 */
final readonly class AdResolution
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public int $placementId,
        public int $adUnitId,
        public string $adUnitName,
        public string $provider,
        public array $config,
        public AdDevice $device,
        public int $priority,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'placement_id' => $this->placementId,
            'ad_unit_id' => $this->adUnitId,
            'ad_unit_name' => $this->adUnitName,
            'provider' => $this->provider,
            'config' => $this->config,
            'device' => $this->device->value,
            'priority' => $this->priority,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['placement_id'], $data['ad_unit_id'], $data['provider'])) {
            return null;
        }

        return new self(
            placementId: (int) $data['placement_id'],
            adUnitId: (int) $data['ad_unit_id'],
            adUnitName: (string) ($data['ad_unit_name'] ?? ''),
            provider: (string) $data['provider'],
            config: (array) ($data['config'] ?? []),
            device: AdDevice::tryFrom((string) ($data['device'] ?? '')) ?? AdDevice::ALL,
            priority: (int) ($data['priority'] ?? 0),
        );
    }

    /**
     * Das Werbemittel als Model-Instanz, ohne es neu zu laden. `exists` bleibt
     * true, damit Provider und Routen-Hilfen (adUnit->getKey()) funktionieren;
     * gespeichert wird so eine Instanz nie.
     */
    public function unit(): AdUnit
    {
        $unit = new AdUnit;
        $unit->setRawAttributes([
            'id' => $this->adUnitId,
            'name' => $this->adUnitName,
            'provider' => $this->provider,
            'config_json' => json_encode($this->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
        ], true);
        $unit->exists = true;

        return $unit;
    }
}
