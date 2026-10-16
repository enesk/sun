<?php

declare(strict_types=1);

namespace Tests\Unit\Ads;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use App\Ads\Providers\AdSenseProvider;
use App\Ads\Providers\HtmlProvider;
use Tests\TestCase;

/**
 * Wer braucht eine Einwilligung, bevor er laedt? (#36)
 *
 * Die Begruendung, mit der eigene HTML-Werbemittel bisher ohne Einwilligung
 * liefen, war falsch: `HtmlProvider::SANDBOX` traegt `allow-scripts`. Der
 * Sandbox-iframe trennt Origins, **nicht Netzwerkziele** — ein Skript darin
 * darf jeden Host ansprechen und dabei IP-Adresse und Geraetedaten
 * uebertragen. Das ist nach § 25 Abs. 1 TDDDG einwilligungsbedurftig, auch
 * ohne Cookie.
 *
 * Diese Tests halten die Zusage fest, die in der Datenschutzerklaerung
 * steht (#35): „erst nach Einwilligung". Die Vorgabe ist deshalb `true`;
 * `false` gilt nur, wenn am Werbemittel ausdruecklich vermerkt ist, dass es
 * keine fremden Hosts anspricht.
 */
class RequiresConsentTest extends TestCase
{
    public function test_adsense_braucht_immer_eine_einwilligung(): void
    {
        $this->assertTrue(
            app(AdSenseProvider::class)->requiresConsent($this->unit(AdProviderType::ADSENSE, [
                'client' => 'ca-pub-0000000000000000',
                'slot' => '1234567890',
            ])),
        );
    }

    public function test_eigenes_html_braucht_eine_einwilligung_wenn_es_dritte_laedt(): void
    {
        $this->assertTrue(
            app(HtmlProvider::class)->requiresConsent($this->unit(AdProviderType::HTML, [
                'html' => '<img src="https://fremder-host.example/pixel.gif">',
                HtmlProvider::THIRD_PARTY => HtmlProvider::THIRD_PARTY_YES,
            ])),
        );
    }

    public function test_ohne_angabe_gilt_die_sichere_vorgabe(): void
    {
        // Der gefaehrliche Fall: ein Werbemittel aus der Uebernahme oder per
        // API angelegt, ohne den Schalter. Fehlt die Angabe, muss die
        // Einwilligung gelten — nicht umgekehrt.
        foreach ([[], [HtmlProvider::THIRD_PARTY => null], [HtmlProvider::THIRD_PARTY => '']] as $config) {
            $this->assertTrue(
                app(HtmlProvider::class)->requiresConsent(
                    $this->unit(AdProviderType::HTML, $config + ['html' => '<p>Anzeige</p>']),
                ),
                'Ohne Angabe muss die Einwilligung verlangt werden.',
            );
        }
    }

    public function test_nur_ein_ausdrueckliches_nein_laedt_sofort(): void
    {
        $this->assertFalse(
            app(HtmlProvider::class)->requiresConsent($this->unit(AdProviderType::HTML, [
                'html' => '<p>Eigener Hinweis</p>',
                HtmlProvider::THIRD_PARTY => HtmlProvider::THIRD_PARTY_NO,
            ])),
        );
    }

    public function test_nur_der_exakte_wert_zaehlt_als_nein(): void
    {
        // Absicht im Code: verglichen wird exakt gegen `no`. Alles andere —
        // auch ' no ' mit Leerzeichen oder 'NO' — gilt als
        // einwilligungspflichtig. Das Formular laesst ohnehin nur `yes`/`no`
        // zu; ein krummer Wert aus einem Import landet damit auf der
        // sicheren Seite, statt von einer Normalisierung abzuhaengen, die in
        // Pruefung und Auswertung auseinanderlaufen koennte.
        foreach ([' no ', 'NO', 'No', 'nein', 'false', '0', 'noo'] as $wert) {
            $this->assertTrue(
                app(HtmlProvider::class)->requiresConsent($this->unit(AdProviderType::HTML, [
                    'html' => '<p>x</p>',
                    HtmlProvider::THIRD_PARTY => $wert,
                ])),
                "Wert \"{$wert}\" darf nicht als Nein durchgehen — nur exakt 'no' zaehlt.",
            );
        }
    }

    public function test_normalisieren_setzt_den_schalter_auf_die_sichere_seite(): void
    {
        // Was im Panel gespeichert wird, soll den Schalter immer tragen —
        // sonst entscheidet spaeter die Vorgabe und niemand sieht den Wert.
        $clean = app(HtmlProvider::class)->normalizeConfig(['html' => '<p>x</p>']);

        $this->assertSame(HtmlProvider::THIRD_PARTY_YES, $clean[HtmlProvider::THIRD_PARTY]);

        $clean = app(HtmlProvider::class)->normalizeConfig([
            'html' => '<p>x</p>',
            HtmlProvider::THIRD_PARTY => HtmlProvider::THIRD_PARTY_NO,
        ]);

        $this->assertSame(HtmlProvider::THIRD_PARTY_NO, $clean[HtmlProvider::THIRD_PARTY]);
    }

    public function test_die_sandbox_erlaubt_skripte_und_begruendet_damit_die_vorgabe(): void
    {
        // Wird `allow-scripts` jemals entfernt, darf diese Begruendung neu
        // bewertet werden — bis dahin ist sie der Grund fuer `true`.
        $this->assertStringContainsString('allow-scripts', HtmlProvider::SANDBOX);
        $this->assertStringNotContainsString('allow-same-origin', HtmlProvider::SANDBOX);
    }

    /** @param  array<string, mixed>  $config */
    private function unit(AdProviderType $provider, array $config): AdUnit
    {
        $unit = new AdUnit;
        $unit->name = 'Probe';
        $unit->provider = $provider->value;
        $unit->config_json = $config;
        $unit->is_active = true;

        return $unit;
    }
}
