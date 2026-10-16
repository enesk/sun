<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\Dto\AdRenderContext;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use App\Ads\Providers\AdSenseProvider;
use Tests\TestCase;

/**
 * Die Hoehenzusage der Banner-Anker (#32).
 *
 * Grund des Tests: mit `data-ad-format="auto"` bestimmt AdSense die Hoehe
 * selbst — gemessen 250 px auf einem Anker, der 90 px reserviert. Der Sprung
 * riss die CLS-Zusage des Moduls (gemessen 0,136 auf `home_after_hero`,
 * docs/messungen/ads-einwilligung-2026-10-16.md 4). Behoben wurde das, indem
 * `responsive` **keine** Format-Angabe mehr bekommt und seine Hoehe aus der
 * Reservierung des Ankers nimmt.
 *
 * Der Fix steckt ausschliesslich in zwei privaten Methoden des Providers und
 * war bis hierher von keinem Test gedeckt: ein `data-ad-format => 'auto'`
 * zurueck in `formatAttributes()` ist eine Zeile, faellt im Code-Review nicht
 * auf und macht sich erst in einer Feldmessung bemerkbar. Deshalb pruefen
 * diese Tests das gerenderte Fragment, nicht die Methoden.
 */
class AdSenseFormatClsTest extends TestCase
{
    private const PUBLISHER = 'ca-pub-0000000000000000';

    /**
     * Banner-Anker, 90 px mobil und 100 px am Desktop: `responsive` darf
     * weder ein Format noch die Volle-Breite-Option tragen, und die Hoehe
     * muss aus der Reservierung kommen — die kleinere der beiden, sonst
     * springt das Layout auf dem schmaleren Geraet.
     */
    public function test_responsive_gets_no_auto_format_and_takes_the_reserved_height(): void
    {
        $html = $this->render('responsive', minHeightMobile: 90, minHeightDesktop: 100);

        $this->assertStringNotContainsString('data-ad-format', $html);
        $this->assertStringNotContainsString('data-full-width-responsive', $html);
        $this->assertStringContainsString('height:90px', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    /** Eine feste Groesse aus dem Katalog gewinnt vor der Reservierung. */
    public function test_fixed_size_uses_the_catalogue_dimensions(): void
    {
        $html = $this->render('leaderboard_728x90', minHeightMobile: 90, minHeightDesktop: 100);

        $this->assertStringNotContainsString('data-ad-format', $html);
        $this->assertStringContainsString('width:728px', $html);
        $this->assertStringContainsString('height:90px', $html);
    }

    /**
     * Fluide Formate behalten ihre freie Hoehe: sie stehen im Textfluss bzw.
     * in Listen und nur auf Ankern mit 250/280 px Reservierung.
     */
    public function test_fluid_formats_keep_their_format_and_free_height(): void
    {
        $textanzeige = $this->render('in_article', minHeightMobile: 250, minHeightDesktop: 280);

        $this->assertStringContainsString('data-ad-format="fluid"', $textanzeige);
        $this->assertStringContainsString('data-ad-layout="in-article"', $textanzeige);
        $this->assertStringNotContainsString('height:', $textanzeige);

        $listenanzeige = $this->render('in_feed', minHeightMobile: 250, minHeightDesktop: 280);

        $this->assertStringContainsString('data-ad-format="fluid"', $listenanzeige);
        $this->assertStringNotContainsString('height:', $listenanzeige);
    }

    /**
     * Ohne Reservierung am Anker — moeglich, solange eine Position keine
     * Mindesthoehe fuehrt — darf `responsive` keine erfundene Hoehe bekommen.
     * Dann ist freie Hoehe richtig, nur eben ohne `auto`.
     */
    public function test_responsive_without_reservation_stays_free(): void
    {
        $html = $this->render('responsive', minHeightMobile: 0, minHeightDesktop: 0);

        $this->assertStringNotContainsString('data-ad-format', $html);
        $this->assertStringNotContainsString('height:', $html);
    }

    private function render(string $format, int $minHeightMobile, int $minHeightDesktop): string
    {
        $unit = new AdUnit([
            'provider' => AdProviderType::ADSENSE->value,
            'config_json' => ['format' => $format, 'slot_id' => '1234567890', 'layout_key' => '-abc+de+fg-hi'],
        ]);

        $context = new AdRenderContext(
            slotKey: 'home_after_hero',
            pageType: null,
            device: AdDevice::MOBILE,
            tenantId: 1,
            adsensePublisherId: self::PUBLISHER,
            consentGranted: true,
            minHeightMobile: $minHeightMobile,
            minHeightDesktop: $minHeightDesktop,
        );

        $gerendert = app(AdSenseProvider::class)->render($unit, $context);

        $this->assertNotNull($gerendert, "AdSense hat fuer Format [{$format}] nichts gerendert.");

        return $gerendert->html;
    }
}
