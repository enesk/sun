<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Analytics\AnalyticsResolver;
use App\Constants\ConfigConstants;
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

    /**
     * Der Riegel gegen Vermischung zwischen zwei Portalen (#14).
     *
     * `ConfigService::loadConfigs()` liest ConfigConstants::OVERRIDABLE_CONFIGS
     * ueber `cache()->many()` — mit Schluesseln OHNE Tenant-Anteil, und der
     * Cache ist in diesem Projekt nicht mandantengetrennt
     * (CacheTenancyBootstrapper ist in config/tenancy.php abgeschaltet). Eine
     * portalbezogene Kennung in diesem Topf hiesse: Portal B meldet in die
     * Property von Portal A, bis der Cache verfaellt.
     *
     * Die globale Kennung darf dort stehen — sie ist fuer alle dieselbe.
     * Portalbezogene Schluessel duerfen es nie.
     */
    public function test_portalbezogene_kennung_steht_nicht_im_ueberschreibbaren_cache(): void
    {
        foreach (ConfigConstants::OVERRIDABLE_CONFIGS as $schluessel) {
            $this->assertStringNotContainsString(
                'google_analytics_id',
                $schluessel,
                "{$schluessel} liegt im nicht mandantengetrennten Cache von ConfigService — Portale wuerden ihre Kennungen vermischen.",
            );

            $this->assertStringNotContainsString(
                'google_tag_manager_id',
                $schluessel,
                "{$schluessel} liegt im nicht mandantengetrennten Cache von ConfigService — Portale wuerden ihre Container vermischen.",
            );
        }
    }

    /**
     * Dieselbe Falle aus der anderen Richtung: die Aufloesung darf die
     * Portalkennung nicht durch den ConfigService schleusen. Die Begruendung
     * steht im Klassenkopf von AnalyticsResolver, dieser Test haelt sie fest.
     */
    public function test_aufloesung_benutzt_den_configservice_nicht(): void
    {
        // Ohne Kommentare: im Klassenkopf steht der Name absichtlich, dort als
        // Begruendung. Gemeint ist der Code.
        $quelle = (string) file_get_contents((new \ReflectionClass(AnalyticsResolver::class))->getFileName());
        $code = implode('', array_map(
            fn (array|string $token): string => is_string($token) ? $token : (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $token[1]),
            token_get_all($quelle),
        ));

        $this->assertStringNotContainsString(
            'ConfigService',
            $code,
            'Die Portalkennung darf nicht ueber den ConfigService laufen, dessen Cache-Schluessel haben keinen Tenant-Anteil.',
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
