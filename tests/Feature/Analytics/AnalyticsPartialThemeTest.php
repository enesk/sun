<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Tests\Feature\FeatureTest;

/**
 * Das Partial im echten Theme-Pfad (#14).
 *
 * Die Aufloesung liegt in resources/views/components/analytics-meta.blade.php,
 * das Partial aber unter dem Theme-Pfad. Dieser Test haelt fest, dass der
 * ThemeViewFinder die Komponente ausserhalb des Themes noch findet — sonst
 * stuende auf jedem Portal ein "Unable to locate a class or view".
 */
class AnalyticsPartialThemeTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_sun_v2_liefert_die_kennung_des_portals_verzoegert_aus(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00', 'app.tracking_scripts' => null]);

        $this->tenant->setAttribute(TenantConfigConstants::GOOGLE_ANALYTICS_ID, 'G-PORTAL00');
        $this->tenant->setAttribute(TenantConfigConstants::GOOGLE_TAG_MANAGER_ID, 'GTM-ABC123');
        $this->tenant->save();

        $markup = $this->renderPartial('sun-v2');

        $this->assertStringContainsString('<meta name="ga-id" content="G-PORTAL00">', $markup);
        $this->assertStringContainsString('<meta name="gtm-id" content="GTM-ABC123">', $markup);
        $this->assertStringContainsString('<meta name="analytics-defer" content="1500">', $markup);
        $this->assertStringNotContainsString('G-GLOBAL00', $markup);
    }

    public function test_default_theme_liefert_dieselbe_kennung_ohne_verzoegerung(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00', 'app.tracking_scripts' => null]);

        $this->tenant->setAttribute(TenantConfigConstants::GOOGLE_ANALYTICS_ID, 'G-PORTAL00');
        $this->tenant->save();

        $markup = $this->renderPartial('default');

        $this->assertStringContainsString('<meta name="ga-id" content="G-PORTAL00">', $markup);
        $this->assertStringNotContainsString('analytics-defer', $markup);
    }

    /**
     * Die vier Portale ohne eigene Kennung: nach dem Umbau messen sie die
     * globale Einstellung, und wenn dort ein Platzhalter steht, gar nichts.
     */
    public function test_portal_ohne_eigene_kennung_laedt_bei_globalem_platzhalter_nichts(): void
    {
        config(['app.google_tracking_id' => 'G-XXXXX', 'app.tracking_scripts' => null]);

        $markup = $this->renderPartial('sun-v2');

        $this->assertStringNotContainsString('ga-id', $markup);
        $this->assertStringNotContainsString('G-XXXXX', $markup);
    }

    private function renderPartial(string $themeSlug): string
    {
        tenancy()->initialize($this->tenant);

        $themeManager = app(ThemeManager::class);
        app(ThemeViewFinder::class)->setThemePaths($themeManager->get($themeSlug));
        $themeManager->activate($themeSlug);

        return view('partials.analytics', ['currentTenant' => $this->tenant->fresh()])->render();
    }
}
