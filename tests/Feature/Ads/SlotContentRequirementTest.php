<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Ads\Support\AdSlotCatalog;
use Tests\TestCase;

/**
 * Inhaltsbedingung der Anker (Ticket #22).
 *
 * Fünf Anker stehen im Markup des Themes sun-v2 **innerhalb** ihrer Sektion
 * und fehlen mit ihr. Herausgezogen werden sie nicht: ohne die Sektionen
 * darüber stünden auf einer inhaltsarmen Startseite vier Banner unmittelbar
 * hintereinander. Stattdessen führt der Katalog `requires`, und das Panel sagt
 * damit, warum ein Anker in der Vorschau fehlen kann.
 *
 * Dieser Test hält die Liste fest. Wird ein Anker in eine Bedingung
 * verschoben, ohne `requires` zu setzen — oder umgekehrt aus einer Bedingung
 * befreit, ohne den Eintrag zu entfernen —, schlägt er an. Genau dieser
 * Gleichlauf ist in #13, #14, #19 und #22 jedes Mal verlorengegangen.
 */
class SlotContentRequirementTest extends TestCase
{
    /**
     * Anker mit Inhaltsbedingung, Stand 16.10.2026.
     *
     * Die Zeile im Markup steht dahinter, damit beim Verschieben klar ist,
     * wo nachzusehen ist.
     *
     * @var array<string, string>
     */
    private const BEDINGTE_ANKER = [
        // pages/home.blade.php, @if($sun['services'] !== [])
        'home_after_services' => 'pages/home.blade.php',
        // pages/home.blade.php, @if($sun['topRated']->isNotEmpty())
        'home_after_top_rated' => 'pages/home.blade.php',
        // pages/home.blade.php, @if(config('themes.sun-v2.nav.guide') && $latestPosts->isNotEmpty())
        'home_after_guide' => 'pages/home.blade.php',
        // pages/home.blade.php, @if($sun['cities']->isNotEmpty())
        'home_after_cities' => 'pages/home.blade.php',
        // pages/companies/show.blade.php, @if($profile['paragraphs'] !== [])
        'company_after_about' => 'pages/companies/show.blade.php',
    ];

    public function test_die_bedingten_anker_fuehren_eine_begruendung(): void
    {
        foreach (array_keys(self::BEDINGTE_ANKER) as $key) {
            $this->assertNotNull(
                AdSlotCatalog::requirementOf($key),
                "{$key} steht im Markup in einer Sektions-Bedingung, fuehrt aber kein `requires` — ".
                'das Panel koennte dann nicht sagen, warum der Anker in der Vorschau fehlt.',
            );
        }
    }

    public function test_kein_anderer_anker_fuehrt_eine_begruendung(): void
    {
        $mitBedingung = [];

        foreach (array_keys(AdSlotCatalog::all()) as $key) {
            if (AdSlotCatalog::requirementOf($key) !== null) {
                $mitBedingung[] = $key;
            }
        }

        sort($mitBedingung);
        $erwartet = array_keys(self::BEDINGTE_ANKER);
        sort($erwartet);

        $this->assertSame(
            $erwartet,
            $mitBedingung,
            'Die Liste der bedingten Anker hat sich geaendert. Steht der Anker jetzt '.
            'unbedingt im Markup, gehoert `requires` weg; kam eine Bedingung hinzu, '.
            'gehoert sie in diesen Test.',
        );
    }

    /** Die Begruendung ist ein ganzer Satz — sie landet unverändert im Panel. */
    public function test_die_begruendungen_sind_lesbare_saetze(): void
    {
        foreach (array_keys(self::BEDINGTE_ANKER) as $key) {
            $satz = (string) AdSlotCatalog::requirementOf($key);

            $this->assertGreaterThan(15, mb_strlen($satz), "Begruendung zu {$key} ist zu knapp: {$satz}");
            $this->assertStringEndsWith('.', $satz, "Begruendung zu {$key} ist kein Satz: {$satz}");
        }
    }

    /**
     * Der Anker steht wirklich in der genannten View — sonst zeigt die Liste
     * oben auf eine Datei, in der niemand nachsehen kann.
     */
    public function test_die_genannten_views_enthalten_den_anker(): void
    {
        foreach (self::BEDINGTE_ANKER as $key => $view) {
            $pfad = resource_path("views/themes/sun-v2/views/{$view}");

            $this->assertFileExists($pfad);
            $this->assertStringContainsString(
                "key=\"{$key}\"",
                (string) file_get_contents($pfad),
                "{$key} steht nicht mehr in {$view}.",
            );
        }
    }
}
