<?php

declare(strict_types=1);

namespace App\Ads\Dto;

use App\Ads\Enums\AdDevice;

/**
 * Lade-Vorgabe eines Katalog-Ankers (SUN-LZ-002, docs/ad-slots.md 7.5).
 *
 * Katalogdaten, keine Belegungsdaten: entsteht aus config/ad-slots.php
 * ({@see \App\Ads\Support\AdSlotCatalog::definition()}) und landet nie im
 * Resolver-Cache.
 *
 * `lazy` ist bereits effektiv: Notschalter `lazy_load.enabled` und
 * `eager`-Flag des Ankers sind verrechnet.
 */
final readonly class AdSlotDefinition
{
    public function __construct(
        public string $key,
        public bool $lazy,
        public bool $eager,
        public int $rootMarginMobile,
        public int $rootMarginDesktop,
        public float $threshold,
    ) {}

    /**
     * Vorlauf in Pixel fuer den Container dieser Geraeteklasse. Ein
     * gemeinsamer Container (ALL) ist auf beiden Geraeten sichtbar und
     * bekommt den groesseren Vorlauf, damit er auch am Desktop rechtzeitig
     * angefordert wird.
     */
    public function rootMargin(AdDevice $device): int
    {
        return match ($device) {
            AdDevice::MOBILE => $this->rootMarginMobile,
            AdDevice::DESKTOP => $this->rootMarginDesktop,
            AdDevice::ALL => max($this->rootMarginMobile, $this->rootMarginDesktop),
        };
    }
}
