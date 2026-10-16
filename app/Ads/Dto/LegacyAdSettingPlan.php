<?php

declare(strict_types=1);

namespace App\Ads\Dto;

/**
 * Die Werbe-Einstellungen eines Portals, wie sie die Uebernahme des
 * Altbestands setzen wuerde (Ticket #15).
 *
 * Drei Dinge wandern je Portal mit: die AdSense-Publisher-ID aus dem
 * Alt-Code, der Auto-Ads-Schalter (Alt-Position `auto_ads`, nur der Loader
 * ohne Anzeigenblock) und der Inhalt von `ad_settings.ads_txt_content` aus
 * der **Tenant**-DB, der in `ad_settings.ads_txt_extra` der **Central**-DB
 * gehoert (zwei Tabellen gleichen Namens auf verschiedenen Verbindungen).
 *
 * `enableAds` ist der Freigabeschalter: ohne ihn stuende nach dem Umstieg
 * netzweit Werbung aus, obwohl das Altsystem gerade ausliefert.
 */
final readonly class LegacyAdSettingPlan
{
    public function __construct(
        public int $tenantId,
        public string $tenantName,
        public ?string $publisherId,
        public bool $autoAds,
        public ?string $adsTxt,
        public bool $enableAds,
    ) {}
}
