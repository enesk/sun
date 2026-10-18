<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Support\AdSlotCatalog;
use Tests\TestCase;

/**
 * Der Anker ueber der Kopf-Karte des Firmenprofils (#68).
 *
 * Ausgespielt wird auf **beiden** Geraeten (Ansage von Enes, 18.10.2026);
 * die Desktop-Reservierung ist vom gleichformatigen Anker
 * home_after_services uebernommen und nicht nachgemessen.
 *
 * Besonderheit dieses Platzes: er steht **vor** dem ersten Inhalt der Seite.
 * Faellt die Anzeige hoeher aus als reserviert, verschiebt sich nicht ein
 * Abschnitt, sondern die ganze Seite — derselbe Fehler, der in #32 mit CLS
 * 0,136 gemessen wurde. Deshalb fuehrt der Katalog hier genau **ein** festes
 * Format und keine freie Hoehe.
 *
 * Der Test haelt diese drei Eigenschaften fest. Ein `responsive` zurueck in
 * die Formatliste ist eine Zeile, faellt im Review nicht auf und zeigt sich
 * erst in einer Feldmessung.
 */
class CompanyBeforeHeaderCardSlotTest extends TestCase
{
    private const KEY = 'company_before_header_card';

    public function test_der_anker_steht_im_katalog_auf_dem_firmenprofil(): void
    {
        $this->assertTrue(AdSlotCatalog::exists(self::KEY));
        $this->assertSame(AdPageType::COMPANY, AdSlotCatalog::pageTypeOf(self::KEY));
    }

    /** Beide Geraete (Ansage von Enes am 18.10.2026). */
    public function test_der_anker_gilt_auf_beiden_geraeten(): void
    {
        $this->assertSame(AdDevice::ALL, AdSlotCatalog::deviceOf(self::KEY));
        $this->assertTrue(AdSlotCatalog::allowsDevice(self::KEY, AdDevice::MOBILE));
        $this->assertTrue(AdSlotCatalog::allowsDevice(self::KEY, AdDevice::DESKTOP));
    }

    /**
     * Auf beiden Geraeten eine Reservierung. Eine 0 am Desktop waere hier der
     * gefaehrliche Fall: der Platz liegt vor dem ersten Inhalt, ohne
     * Reservierung schiebt die eingesetzte Anzeige die ganze Seite nach
     * unten.
     */
    public function test_der_anker_reserviert_auf_beiden_geraeten(): void
    {
        $eintrag = AdSlotCatalog::find(self::KEY);

        $this->assertNotNull($eintrag);
        $this->assertSame(280, (int) $eintrag['min_height_mobile']);
        $this->assertSame(250, (int) $eintrag['min_height_desktop']);
        $this->assertGreaterThan(0, (int) $eintrag['min_height_desktop']);
    }

    /**
     * Die eigentliche Zusage: ein festes Format, und `responsive` ist hier
     * **nicht** waehlbar. Sonst bestimmt das Werbemittel die Hoehe und die
     * Reservierung von 280 px ist wertlos.
     */
    public function test_responsive_ist_fuer_diesen_anker_nicht_waehlbar(): void
    {
        $formate = AdSlotCatalog::formatsOf(self::KEY);

        $this->assertNotContains('responsive', $formate);
        $this->assertSame(['rectangle_300x250'], $formate);
    }

    /**
     * Nur das Theme sun-v2 fuehrt Werbe-Anker (#16). Der Anker darf also
     * nicht fuer default/starter angeboten werden, sonst steht er im Panel
     * zur Wahl und rendert nie.
     */
    public function test_der_anker_gilt_nur_im_theme_sun_v2(): void
    {
        $this->assertTrue(AdSlotCatalog::existsForTheme(self::KEY, 'sun-v2'));
        $this->assertFalse(AdSlotCatalog::existsForTheme(self::KEY, 'default'));
        $this->assertFalse(AdSlotCatalog::existsForTheme(self::KEY, 'starter'));
    }

    /**
     * Der Anker muss im Markup stehen, und zwar **vor** der Kopf-Karte —
     * sonst sitzt er trotz Katalogeintrag an der falschen Stelle. Geprueft
     * wird die Reihenfolge im Quelltext der Vorlage, weil ein Rendern der
     * Profilseite einen vollstaendigen Portalkontext braucht.
     */
    public function test_der_anker_steht_in_der_vorlage_vor_der_kopf_karte(): void
    {
        $vorlage = (string) file_get_contents(
            resource_path('views/themes/sun-v2/views/pages/companies/show.blade.php'),
        );

        $anker = mb_strpos($vorlage, '<x-sun.ad-slot key="'.self::KEY.'"');
        $kopfKarte = mb_strpos($vorlage, '<!-- Kopf-Karte -->');

        $this->assertNotFalse($anker, 'Der Anker fehlt in pages/companies/show.blade.php.');
        $this->assertNotFalse($kopfKarte, 'Die Kopf-Karte ist in der Vorlage nicht mehr zu finden.');
        $this->assertLessThan($kopfKarte, $anker, 'Der Anker steht hinter der Kopf-Karte statt davor.');
        $this->assertStringContainsString(
            '<x-sun.ad-slot key="'.self::KEY.'" :subject="$company" />',
            $vorlage,
            'Ohne :subject prueft die Komponente das Premium-Merkmal AdFree nicht.',
        );
    }
}
