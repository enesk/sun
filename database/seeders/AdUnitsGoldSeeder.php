<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use Illuminate\Database\Seeder;

/**
 * Die sieben AdSense-Anzeigenbloecke "GOLD - Profil - *" als Werbemittel
 * (Ticket #3 des Vorhabens "GOLD-Bloecke auf dem Firmenprofil").
 *
 * Die Namen sind exakt die aus dem AdSense-Konto — sie sind zugleich der
 * Schluessel des Upserts, damit ein zweiter Lauf nichts verdoppelt.
 *
 * Die Publisher-ID steht NICHT am Werbemittel: sie kommt je Portal aus
 * `ad_settings.adsense_publisher_id` (sonst `ad-slots.adsense.publisher_id`),
 * der AdSenseProvider holt sie aus dem Render-Kontext.
 *
 * Formate: `ad-slots.formats` kennt weder ein Multiplex- noch ein festes
 * 300x600-Format. Beide bekommen darum `responsive` — der AdSenseProvider
 * gibt dem <ins> dann die Hoehe der Reservierung des Ankers mit und KEINE
 * `data-ad-format`-Angabe (#32), die Form bestimmt also der Block im
 * AdSense-Konto innerhalb des reservierten Containers. Ein neues Format
 * anzulegen waere eine Katalogaenderung ausserhalb dieses Tickets.
 *
 * Aufruf: php artisan db:seed --class=AdUnitsGoldSeeder
 */
class AdUnitsGoldSeeder extends Seeder
{
    /**
     * Name => [slot_id, format]. Reihenfolge wie auf dem Profil von oben
     * nach unten, danach die Seitenspalte.
     *
     * @var array<string, array{string, string}>
     */
    private const UNITS = [
        // Ziel: company_before_header_card — feste 300x250, der Anker laesst
        // ausdruecklich nur dieses Format zu (kein `responsive` auf einem
        // Anker mit knapper Reservierung).
        'GOLD - Profil - Top' => ['7887957169', 'rectangle_300x250'],

        // Textfluss-Anker auf dem Profil: fluide Hoehe, kein Layout-Key —
        // der braucht nur In-Feed.
        'GOLD - Profil - Content 1' => ['1599635007', 'in_article'],
        'GOLD - Profil - Content 2' => ['6660389998', 'in_article'],
        'GOLD - Profil - Content 3' => ['9831594225', 'in_article'],

        // Multiplex: kein eigener Format-Schluessel im Katalog, siehe oben.
        'GOLD - Profil - Bottom Multiplex' => ['7943797487', 'responsive'],

        // Seitenspalte oben: 300x250 ist registriert und passt auf die
        // Reservierung von company_sidebar_top (250 px).
        'GOLD - Profil - Sidebar Top' => ['9221220138', 'rectangle_300x250'],

        // Seitenspalte klebend: 300x600 gibt es nicht als Format. Mit
        // `responsive` fuellt der Block die 600 px, die
        // company_sidebar_sticky reserviert — rectangle_300x250 wuerde nur
        // 250 davon nutzen.
        'GOLD - Profil - Sidebar Sticky' => ['3810865377', 'responsive'],
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;

        foreach (self::UNITS as $name => [$slotId, $format]) {
            $unit = AdUnit::query()->firstOrNew(['name' => $name]);
            $existed = $unit->exists;

            $unit->provider = AdProviderType::ADSENSE->value;
            $unit->config_json = ['slot_id' => $slotId, 'format' => $format];
            $unit->is_active = true;
            $unit->archived_at = null;
            $unit->save();

            $existed ? $updated++ : $created++;
        }

        $this->command?->info("GOLD-Werbemittel: {$created} angelegt, {$updated} aktualisiert.");
    }
}
