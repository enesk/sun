<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\Support\AdEditorMode as EditorFlag;
use App\Ads\Support\AdSlotCatalog;
use Tests\TestCase;

/**
 * Der Anker-Rahmen von <x-sun.ad-slot> im Editor-Modus (#6).
 *
 * Ohne Portal-Kontext loest der Resolver nichts auf — genau der Fall, der
 * zaehlt: der Rahmen muss auch dort stehen, wo **keine** Belegung ist, sonst
 * fehlen im Editor genau die Plaetze, die belegt werden sollen.
 */
class AdSlotEditorFrameTest extends TestCase
{
    protected function tearDown(): void
    {
        EditorFlag::disable();

        parent::tearDown();
    }

    public function test_ohne_editor_modus_bleibt_nur_der_anker_kommentar(): void
    {
        $html = (string) $this->blade('<x-sun.ad-slot key="home_after_hero" />');

        $this->assertStringContainsString('ad-slot: home_after_hero', $html);
        $this->assertStringNotContainsString('data-ad-editor-frame', $html);
        $this->assertStringNotContainsString('<div class="ad-slot', $html);
    }

    public function test_im_editor_modus_steht_ein_rahmen_auch_ohne_belegung(): void
    {
        EditorFlag::enable();

        $html = (string) $this->blade('<x-sun.ad-slot key="home_after_hero" />');

        $this->assertStringContainsString('data-ad-editor-frame', $html);
        $this->assertStringContainsString('data-ad-slot-key="home_after_hero"', $html);
        $this->assertStringContainsString('data-ad-slot-filled="0"', $html);
        // Unbelegt ist gestrichelt, belegt gruen (Ticket #6).
        $this->assertStringContainsString('border-dashed', $html);
        $this->assertStringNotContainsString('border-emerald-500', $html);
        // Tastatur statt Klick-Handler auf einem <div>.
        $this->assertStringContainsString('<button type="button"', $html);
    }

    public function test_der_rahmen_zeigt_label_schluessel_und_geraet(): void
    {
        EditorFlag::enable();

        $slot = AdSlotCatalog::find('company_sidebar_sticky');
        $html = (string) $this->blade('<x-sun.ad-slot key="company_sidebar_sticky" />');

        $this->assertStringContainsString((string) $slot['label'], $html);
        $this->assertStringContainsString('company_sidebar_sticky', $html);
        $this->assertStringContainsString('Nur Desktop', $html);
    }

    /**
     * Die Mindesthoehe bleibt die des Katalogs: die Vorschau muss dieselbe
     * Geometrie zeigen wie die ausgelieferte Seite, sonst beurteilt Enes ein
     * Layout, das es nicht gibt.
     */
    public function test_der_rahmen_traegt_die_katalog_mindesthoehen(): void
    {
        EditorFlag::enable();

        $slot = AdSlotCatalog::find('home_after_services');
        $html = (string) $this->blade('<x-sun.ad-slot key="home_after_services" />');

        $this->assertStringContainsString(
            '--ad-mh:'.$slot['min_height_mobile'].'px;--ad-mh-lg:'.$slot['min_height_desktop'].'px',
            $html,
        );
    }

    /**
     * Ein belegter Anker: gruener Rand und Name des Werbemittels.
     *
     * Geprueft wird die Teil-View mit fertigem Container, nicht der Weg durch
     * den Resolver — der ist `final` und braucht eine Belegung in der
     * zentralen Datenbank. Dass der Container Beschriftung, Belegung und
     * Werbemittel-Namen fuehrt, deckt die statische Analyse ab.
     */
    public function test_belegter_anker_zeigt_werbemittel_und_gruenen_rand(): void
    {
        $html = view('components.sun.ad-slot-editor-frame', [
            'key' => 'home_after_hero',
            'container' => [
                'device' => 'all',
                'visibility' => '',
                'minHeightMobile' => 100,
                'minHeightDesktop' => 90,
                'html' => null,
                'provider' => null,
                'label' => 'Startseite · unter dem Suchbereich',
                'placementId' => 42,
                'adUnitName' => 'Hausbanner Sanitaer',
            ],
        ])->render();

        $this->assertStringContainsString('data-ad-slot-filled="1"', $html);
        $this->assertStringContainsString('data-ad-slot-placement="42"', $html);
        $this->assertStringContainsString('Hausbanner Sanitaer', $html);
        $this->assertStringContainsString('border-emerald-500', $html);
        $this->assertStringNotContainsString('border-dashed', $html);
        // Kein Werbemittel in der Vorschau — im Editor rendert kein Provider.
        $this->assertStringNotContainsString('data-ad-fragment', $html);
        $this->assertStringNotContainsString('adsbygoogle', $html);
    }

    /** Ein Anker, den der Katalog mobil nicht fuehrt, bleibt auch im Editor auf lg beschraenkt. */
    public function test_geraetegebundener_anker_behaelt_seine_sichtbarkeitsklasse(): void
    {
        EditorFlag::enable();

        $html = (string) $this->blade('<x-sun.ad-slot key="company_sidebar_sticky" />');

        $this->assertStringContainsString('hidden lg:block', $html);
        $this->assertStringContainsString('data-ad-slot-device="desktop"', $html);
    }
}
