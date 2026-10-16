<?php

declare(strict_types=1);

namespace App\Ads\Dto;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Support\AdSlotCatalog;

/**
 * Alles, was ein Provider zum Rendern braucht, ohne selbst zu fragen
 * (docs/ad-slots.md 5, Ticket #4).
 *
 * Gebaut wird der Kontext von der Komponente (#5) bzw. vom Resolver
 * ({@see \App\Ads\AdSlotResolver::context()}) — nie vom Provider. Damit haengt
 * kein Provider an tenant(), an der Einwilligung oder am Editor-Modus.
 *
 * Der Kontext ist **nicht** Teil des Cache-Schluessels, weil er nie gecacht
 * wird: `consentGranted` und `editorMode` wechseln je Anfrage.
 *
 * `consentGranted` ist seit #9 keine Abbruchbedingung eines Providers mehr:
 * das Gate liegt in der Komponente und in resources/js/ads.js, damit eine
 * Einwilligung im laufenden Besuch ohne Reload greift (docs/ad-slots.md 9).
 * Der Wert bleibt im Kontext, weil `ads:resolve --no-consent` damit zeigt,
 * was der Browser zurueckhalten wuerde.
 */
final readonly class AdRenderContext
{
    public function __construct(
        public string $slotKey,
        public ?AdPageType $pageType,
        public AdDevice $device,
        public int|string|null $tenantId,
        public ?string $adsensePublisherId,
        public bool $consentGranted = false,
        public bool $editorMode = false,
        public bool $lazy = true,
        public int $minHeightMobile = 0,
        public int $minHeightDesktop = 0,
    ) {}

    /**
     * Kontext aus dem Katalog aufbauen — Seitentyp und Mindesthoehen stehen
     * dort, sie werden nirgends von Hand uebergeben.
     */
    public static function forSlot(
        string $slotKey,
        AdDevice $device,
        int|string|null $tenantId,
        ?string $adsensePublisherId,
        bool $consentGranted = false,
        bool $editorMode = false,
        bool $lazy = true,
    ): self {
        $slot = AdSlotCatalog::find($slotKey) ?? [];

        return new self(
            slotKey: $slotKey,
            pageType: AdSlotCatalog::pageTypeOf($slotKey),
            device: $device,
            tenantId: $tenantId,
            adsensePublisherId: $adsensePublisherId,
            consentGranted: $consentGranted,
            editorMode: $editorMode,
            lazy: $lazy,
            minHeightMobile: (int) ($slot['min_height_mobile'] ?? 0),
            minHeightDesktop: (int) ($slot['min_height_desktop'] ?? 0),
        );
    }

    /** Mindesthoehe der Geraeteklasse, mit der gerade gerendert wird. */
    public function minHeight(): int
    {
        return $this->device === AdDevice::MOBILE
            ? $this->minHeightMobile
            : $this->minHeightDesktop;
    }

    public function withDevice(AdDevice $device): self
    {
        return new self(
            $this->slotKey,
            $this->pageType,
            $device,
            $this->tenantId,
            $this->adsensePublisherId,
            $this->consentGranted,
            $this->editorMode,
            $this->lazy,
            $this->minHeightMobile,
            $this->minHeightDesktop,
        );
    }
}
