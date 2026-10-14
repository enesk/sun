<?php

declare(strict_types=1);

namespace Tests\Unit\Themes;

use Tests\TestCase;

/**
 * Vertrag der Branchenpakete (#12).
 *
 * Die Pakete entscheiden, was auf einem Portal steht. Ein halbfertiges Paket
 * ist gefaehrlich: fehlende Schluessel fallen auf die Vorgabe zurueck, und
 * das sind die Elektriker-Texte. Dieser Test haelt deshalb drei Dinge fest:
 * jede zugeordnete Branche hat ein Paket, jedes Geruest ist als solches
 * markiert und traegt keine halben Texte, und jede Kachel hat Label,
 * Suchbegriff und ein Symbol, das es im Sprite wirklich gibt.
 */
class BranchPackagesTest extends TestCase
{
    /** Schluessel, die ein fertiges Paket braucht (siehe sun:branches:status). */
    private const PFLICHTTEXTE = ['hero', 'seo', 'search', 'city', 'cities_index', 'login'];

    /** Textschluessel, die in einem Geruest NICHT stehen duerfen. */
    private const TEXTSCHLUESSEL = ['hero', 'seo', 'city', 'cities_index', 'blog', 'login'];

    public function test_jede_zugeordnete_branche_hat_ein_paket(): void
    {
        $zuordnungen = array_unique(array_merge(
            array_values((array) config('themes.sun-v2-branches.domains', [])),
            array_values((array) config('themes.sun-v2-branches.needles', [])),
        ));

        $this->assertNotSame([], $zuordnungen, 'Ohne Zuordnung greift kein Paket.');

        foreach ($zuordnungen as $slug) {
            $this->assertIsArray(
                config("branches.{$slug}"),
                "Zur Branche {$slug} fehlt config/branches/{$slug}.php.",
            );
        }
    }

    public function test_jedes_paket_ist_entweder_fertig_oder_als_geruest_markiert(): void
    {
        foreach ($this->pakete() as $slug => $paket) {
            if (($paket['_texte_fehlen'] ?? false) === true) {
                // Ein Geruest darf keine halben Texte tragen: sonst stehen auf
                // der Seite Teile der Branche und Teile der Vorgabe.
                foreach (self::TEXTSCHLUESSEL as $schluessel) {
                    if ($schluessel === 'search') {
                        continue;
                    }

                    $this->assertArrayNotHasKey(
                        $schluessel,
                        $paket,
                        "Geruest {$slug} traegt den Textschluessel {$schluessel} — entweder ganz oder gar nicht.",
                    );
                }

                continue;
            }

            foreach (self::PFLICHTTEXTE as $schluessel) {
                $this->assertArrayHasKey(
                    $schluessel,
                    $paket,
                    "Paket {$slug} gilt als fertig, aber {$schluessel} fehlt — der Text kaeme aus der Vorgabe.",
                );
            }
        }
    }

    public function test_jedes_geruest_bringt_kacheln_begriffe_und_jobfilter_mit(): void
    {
        foreach ($this->pakete() as $slug => $paket) {
            $this->assertArrayHasKey('brand_icon', $paket, "Paket {$slug} ohne Markensymbol.");
            $this->assertArrayHasKey('services', $paket, "Paket {$slug} ohne Leistungskacheln.");
            $this->assertNotSame('', (string) ($paket['search']['branch_plural'] ?? ''), "Paket {$slug} ohne Branchenbegriff.");
            $this->assertNotSame([], (array) ($paket['jobs']['quick_filters'] ?? []), "Paket {$slug} ohne Job-Schnellfilter.");
        }
    }

    public function test_jede_kachel_hat_label_suchbegriff_und_ein_vorhandenes_symbol(): void
    {
        $symbole = $this->spriteSymbole();

        foreach ($this->pakete() as $slug => $paket) {
            foreach ((array) ($paket['services'] ?? []) as $index => $kachel) {
                $this->assertNotSame('', (string) ($kachel['label'] ?? ''), "{$slug}: Kachel {$index} ohne Label.");
                $this->assertNotSame('', (string) ($kachel['query'] ?? ''), "{$slug}: Kachel {$index} ohne Suchbegriff.");

                // Woerter unter vier Zeichen findet die MySQL-Volltextsuche
                // nicht — eine solche Kachel bliebe immer leer.
                $this->assertGreaterThanOrEqual(
                    4,
                    mb_strlen((string) $kachel['query']),
                    "{$slug}: Suchbegriff \"{$kachel['query']}\" ist zu kurz fuer die Volltextsuche.",
                );

                $this->assertContains(
                    (string) ($kachel['icon'] ?? ''),
                    $symbole,
                    "{$slug}: Symbol \"{$kachel['icon']}\" steht nicht im Sprite.",
                );
            }

            $this->assertContains(
                (string) $paket['brand_icon'],
                $symbole,
                "{$slug}: Markensymbol \"{$paket['brand_icon']}\" steht nicht im Sprite.",
            );
        }
    }

    public function test_die_zuordnung_setzt_spezifische_stichwoerter_vor_allgemeine(): void
    {
        // 'arzt' trifft auch "zahnarzt" und "unfallarzt": die allgemeinen
        // Stichwoerter muessen deshalb hinten stehen, sonst bekommt das
        // Zahnarztportal das Hausarzt-Paket.
        $needles = array_keys((array) config('themes.sun-v2-branches.needles', []));

        foreach ([['zahnarzt', 'arzt'], ['unfallarzt', 'arzt'], ['bauunternehmen', 'bau'], ['solar', 'energieberater']] as [$spezifisch, $allgemein]) {
            $this->assertContains($spezifisch, $needles, "Stichwort {$spezifisch} fehlt.");
            $this->assertContains($allgemein, $needles, "Stichwort {$allgemein} fehlt.");
            $this->assertLessThan(
                array_search($allgemein, $needles, true),
                array_search($spezifisch, $needles, true),
                "{$spezifisch} muss vor {$allgemein} stehen.",
            );
        }
    }

    public function test_nicht_ausgelieferte_portale_behalten_ihr_paket(): void
    {
        // #16: solar und tierarzt bleiben zugeordnet, obwohl die Portale nicht
        // ausgeliefert werden — wird die Entscheidung zurueckgedreht, ist
        // nichts nachzubauen. Maßgeblich ist das Attribut am Tenant
        // (Tenant::NOT_DELIVERED), nicht eine Liste in der Konfiguration.
        $domains = (array) config('themes.sun-v2-branches.domains', []);

        foreach (['firmenfreund.net' => 'solar', 'tierarztportal.com' => 'tierarzt'] as $domain => $slug) {
            $this->assertSame($slug, $domains[$domain] ?? null, "Zuordnung von {$domain} fehlt.");
            $this->assertIsArray(config("branches.{$slug}"), "Paket {$slug} fehlt.");
        }
    }

    public function test_die_geruieste_nicht_ausgelieferter_portale_nennen_den_grund(): void
    {
        // Wer die Datei oeffnet, soll im Kopf lesen, warum hier keine Texte
        // stehen — sonst schreibt sie irgendwann jemand voll.
        foreach (['solar', 'tierarzt'] as $slug) {
            $quelle = (string) file_get_contents(config_path("branches/{$slug}.php"));

            $this->assertStringContainsString('NICHT AUSGELIEFERT', $quelle, "{$slug}: Hinweis fehlt.");
            $this->assertStringContainsString('#16', $quelle, "{$slug}: Ticketbezug fehlt.");
            $this->assertStringContainsString('docs/portale.md', $quelle, "{$slug}: Verweis auf die Begruendung fehlt.");
        }
    }

    public function test_die_begruendung_fuer_21_portale_steht_in_der_doku(): void
    {
        $doku = (string) file_get_contents(base_path('docs/portale.md'));

        $this->assertStringContainsString('14.10.2026', $doku, 'Datum der Entscheidung fehlt.');
        $this->assertStringContainsString('Enes', $doku, 'Entscheidungsgeber fehlt.');
        $this->assertStringContainsString('21', $doku);
        $this->assertStringContainsString('firmenfreund.net', $doku);
        $this->assertStringContainsString('tierarztportal.com', $doku);
    }

    public function test_die_vorgabe_selbst_ist_kein_geruest(): void
    {
        $vorgabe = (array) config('themes.sun-v2', []);

        $this->assertArrayNotHasKey('_texte_fehlen', $vorgabe);

        foreach (self::PFLICHTTEXTE as $schluessel) {
            $this->assertArrayHasKey($schluessel, $vorgabe, "Der Vorgabe fehlt {$schluessel}.");
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pakete(): array
    {
        $pakete = [];

        foreach (glob(config_path('branches/*.php')) ?: [] as $datei) {
            $slug = basename($datei, '.php');
            $pakete[$slug] = (array) config("branches.{$slug}", []);
        }

        $this->assertNotSame([], $pakete, 'Es wurde kein Branchenpaket gefunden.');

        return $pakete;
    }

    /** @return array<int, string> */
    private function spriteSymbole(): array
    {
        $sprite = (string) file_get_contents(public_path('themes/sun-v2/images/icons.svg'));

        preg_match_all('/<symbol id="([a-z0-9-]+)"/', $sprite, $treffer);

        return $treffer[1];
    }
}
