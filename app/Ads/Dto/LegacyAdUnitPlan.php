<?php

declare(strict_types=1);

namespace App\Ads\Dto;

use App\Ads\Enums\AdDevice;
use App\Ads\Support\AdSlotCatalog;

/**
 * Ein Werbemittel samt Belegung, wie es die Uebernahme des Altbestands
 * anlegen wuerde (Ticket #15).
 *
 * Gebuendelt wird nach Anker, Geraeteklasse und AdSense-Anzeigenblock: der
 * Alt-Code steht in jedem Portal einzeln in `ad_slots.code`, ist aber je
 * Position und AdSense-Konto identisch. Aus 118 Zeilen werden so wenige
 * Werbemittel mit je einer Belegung, die ueber den Pivot auf ihre Portale
 * zeigt — statt 118 Einzelbelegungen.
 *
 * `tenantIds` ist der Portal-Scope dieser Belegung. Netzweit
 * (`applies_to_all_tenants`) wird sie nur, wenn der Block jedes Portal des
 * Netzes abdeckt; bei zwei AdSense-Konten ist das nicht der Fall.
 */
final readonly class LegacyAdUnitPlan
{
    /**
     * @param  list<int>  $tenantIds
     * @param  list<string>  $tenantNames
     */
    public function __construct(
        public string $legacyPosition,
        public string $slotKey,
        public AdDevice $device,
        public string $publisherId,
        public string $slotId,
        public ?string $layoutKey,
        public string $format,
        public array $tenantIds,
        public array $tenantNames,
    ) {}

    /**
     * Name des Werbemittels in der Verwaltung (#10). Enthaelt den
     * Anzeigenblock, weil zwei AdSense-Konten denselben Anker belegen und die
     * Werbemittel sonst nicht auseinanderzuhalten waeren.
     */
    public function unitName(): string
    {
        $label = (string) (AdSlotCatalog::find($this->slotKey)['label'] ?? $this->slotKey);

        return "Uebernahme · {$label} · {$this->publisherId} · {$this->slotId}";
    }

    /**
     * Konfiguration fuer `ad_units.config_json` (AdSense).
     *
     * Die Publisher-ID steht bewusst **nicht** drin: `data-ad-client` kommt
     * immer aus den Einstellungen des Portals (AdSenseProvider), nie aus dem
     * Werbemittel. Sie wandert deshalb nach `ad_settings`.
     *
     * @return array<string, mixed>
     */
    public function unitConfig(): array
    {
        $config = [
            'slot_id' => $this->slotId,
            'format' => $this->format,
        ];

        if ($this->layoutKey !== null) {
            $config['layout_key'] = $this->layoutKey;
        }

        return $config;
    }
}
