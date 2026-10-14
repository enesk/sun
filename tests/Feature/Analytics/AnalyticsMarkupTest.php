<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Das ausgelieferte Markup der Messkennung (#14).
 *
 * Der gefaehrliche Fall steht unten: zwei Portale in einem Prozess. Der
 * ConfigService legt die ueberschreibbaren Werte ueber cache()->many() in
 * Schluesseln ohne Tenant-Anteil ab. Faellt die Tenant-Kennung in denselben
 * Topf, meldet Portal B in die Property von Portal A — und das sieht man an
 * den Zahlen erst Wochen spaeter.
 */
class AnalyticsMarkupTest extends TestCase
{
    public function test_portal_liefert_seine_eigene_kennung_aus(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $markup = $this->render($this->tenant('G-PORTAL00', 'GTM-ABC123'));

        $this->assertStringContainsString('<meta name="ga-id" content="G-PORTAL00">', $markup);
        $this->assertStringContainsString('<meta name="gtm-id" content="GTM-ABC123">', $markup);
        $this->assertStringNotContainsString('G-GLOBAL00', $markup);
    }

    public function test_ohne_kennung_steht_kein_meta_im_markup(): void
    {
        config(['app.google_tracking_id' => null]);

        $markup = $this->render($this->tenant(null), defer: 1500);

        $this->assertStringNotContainsString('ga-id', $markup);
        $this->assertStringNotContainsString('gtm-id', $markup);
        // Ohne Kennung gibt es nichts zu verzoegern.
        $this->assertStringNotContainsString('analytics-defer', $markup);
    }

    public function test_sun_v2_verzoegert_das_skript(): void
    {
        $markup = $this->render($this->tenant('G-PORTAL00'), defer: 1500);

        $this->assertStringContainsString('<meta name="analytics-defer" content="1500">', $markup);
    }

    public function test_zwei_portale_im_selben_prozess_vermischen_ihre_kennungen_nicht(): void
    {
        config(['app.google_tracking_id' => 'G-GLOBAL00']);

        $a = $this->tenant('G-AAAAAAAAAA');
        $b = $this->tenant('G-BBBBBBBBBB');

        $ersterLauf = $this->render($a);
        $zweiterLauf = $this->render($b);
        // Noch einmal A, damit ein Treffer aus dem ersten Lauf auffaellt.
        $dritterLauf = $this->render($a);

        $this->assertStringContainsString('content="G-AAAAAAAAAA"', $ersterLauf);

        $this->assertStringContainsString('content="G-BBBBBBBBBB"', $zweiterLauf);
        $this->assertStringNotContainsString('G-AAAAAAAAAA', $zweiterLauf);

        $this->assertStringContainsString('content="G-AAAAAAAAAA"', $dritterLauf);
        $this->assertStringNotContainsString('G-BBBBBBBBBB', $dritterLauf);
    }

    public function test_ein_portal_ohne_kennung_erbt_nicht_vom_vorherigen(): void
    {
        config(['app.google_tracking_id' => null]);

        $this->render($this->tenant('G-AAAAAAAAAA'));

        $this->assertStringNotContainsString('ga-id', $this->render($this->tenant(null)));
    }

    /**
     * Die Aufloesung gehoert an eine Stelle. Vorher stand sie doppelt in den
     * Theme-Partials, und genau dabei ist sun-v2 beim globalen Wert
     * haengengeblieben.
     */
    public function test_kein_partial_liest_die_globale_kennung_selbst(): void
    {
        foreach ($this->analyticsPartials() as $pfad) {
            $this->assertStringNotContainsString(
                'google_tracking_id',
                (string) file_get_contents($pfad),
                basename(dirname($pfad, 2))." liest die globale Kennung selbst statt ueber <x-analytics-meta>: {$pfad}",
            );
        }
    }

    public function test_jedes_analytics_partial_benutzt_die_gemeinsame_aufloesung(): void
    {
        $partials = $this->analyticsPartials();

        $this->assertGreaterThanOrEqual(4, count($partials), 'Partials nicht gefunden — Pfade geaendert?');

        foreach ($partials as $pfad) {
            $this->assertStringContainsString(
                'x-analytics-meta',
                (string) file_get_contents($pfad),
                "Partial ohne gemeinsame Aufloesung: {$pfad}",
            );
        }
    }

    /** @return list<string> */
    private function analyticsPartials(): array
    {
        $pfade = array_merge(
            glob(resource_path('views/themes/*/views/partials/analytics.blade.php')) ?: [],
            glob(resource_path('views/components/layouts/partials/analytics.blade.php')) ?: [],
        );

        return array_values($pfade);
    }

    private function render(Tenant $tenant, ?int $defer = null): string
    {
        $deferAttribut = $defer === null ? '' : ' :defer="$defer"';

        return Blade::render(
            "<x-analytics-meta :tenant=\"\$tenant\"{$deferAttribut} />",
            ['tenant' => $tenant, 'defer' => $defer],
        );
    }

    private function tenant(?string $gaId, ?string $gtmId = null): Tenant
    {
        $tenant = new Tenant;
        $tenant->setAttribute(TenantConfigConstants::GOOGLE_ANALYTICS_ID, $gaId);
        $tenant->setAttribute(TenantConfigConstants::GOOGLE_TAG_MANAGER_ID, $gtmId);

        return $tenant;
    }
}
