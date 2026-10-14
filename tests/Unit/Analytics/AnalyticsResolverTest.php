<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Analytics\AnalyticsResolver;
use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use Tests\TestCase;

/**
 * Rangfolge der Messkennung: Tenant -> globale Einstellung -> aus (#14).
 *
 * Das Feld in /verwaltung wurde bis hierher geschrieben und nur im alten Theme
 * gelesen; sun-v2 lieferte stattdessen den globalen Wert aus. Diese Tests
 * halten die eine Aufloesung fest, die beide Themes jetzt benutzen.
 */
class AnalyticsResolverTest extends TestCase
{
    private AnalyticsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AnalyticsResolver;
    }

    public function test_kennung_des_portals_schlaegt_die_globale(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $aufgeloest = $this->resolver->resolve($this->tenant('G-PORTAL00'));

        $this->assertSame('G-PORTAL00', $aufgeloest->gaId);
    }

    public function test_ohne_portalkennung_gilt_die_globale(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $this->assertSame('G-GLOBAL00', $this->resolver->resolve($this->tenant(null))->gaId);
    }

    public function test_ohne_jede_kennung_wird_nicht_gemessen(): void
    {
        config(['app.google_tracking_id' => null]);

        $aufgeloest = $this->resolver->resolve($this->tenant(null));

        $this->assertNull($aufgeloest->gaId);
        $this->assertNull($aufgeloest->gtmId);
        $this->assertFalse($aufgeloest->measures(), 'Ohne Kennung darf kein Skript geladen werden.');
    }

    /**
     * Auf der Produktion stand bis heute GOOGLE_TRACKING_ID="G-XXXXX". Das ist
     * keine Kennung: ausgeliefert laedt sie 170 KB von Google und meldet ins
     * Nichts. Ein Platzhalter muss darum wie "nicht gesetzt" wirken.
     */
    public function test_platzhalter_gilt_als_nicht_gesetzt(): void
    {
        config(['app.google_tracking_id' => 'G-XXXXX']);

        $this->assertNull($this->resolver->resolve($this->tenant(null))->gaId);
        $this->assertSame('G-41J8DT8ZWN', $this->resolver->resolve($this->tenant('G-41J8DT8ZWN'))->gaId);
        $this->assertNull($this->resolver->resolve($this->tenant('GTM-XXXXXX', 'GTM-XXXXXX'))->gtmId);
    }

    public function test_leerzeichen_und_leerer_text_zaehlen_nicht(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $this->assertSame('G-GLOBAL00', $this->resolver->resolve($this->tenant('   '))->gaId);
        $this->assertSame('G-PORTAL00', $this->resolver->resolve($this->tenant('  G-PORTAL00 '))->gaId);
    }

    /**
     * Der Tag Manager hat keinen globalen Rueckfall: GOOGLE_TAG_MANAGER_ID
     * existiert nur je Portal. Ein Portal ohne Wert laedt keinen Container.
     */
    public function test_tag_manager_kommt_nur_vom_portal(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $this->assertNull($this->resolver->resolve($this->tenant(null))->gtmId);
        $this->assertSame('GTM-ABC123', $this->resolver->resolve($this->tenant(null, 'GTM-ABC123'))->gtmId);
    }

    public function test_ohne_tenant_gilt_die_globale_einstellung(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $this->assertSame('G-GLOBAL00', $this->resolver->resolve(null)->gaId);
    }

    private function tenant(?string $gaId, ?string $gtmId = null): Tenant
    {
        $tenant = new Tenant;
        $tenant->setAttribute(TenantConfigConstants::GOOGLE_ANALYTICS_ID, $gaId);
        $tenant->setAttribute(TenantConfigConstants::GOOGLE_TAG_MANAGER_ID, $gtmId);

        return $tenant;
    }
}
