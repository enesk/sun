<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use Illuminate\Database\Seeder;

/**
 * Die sieben AdSense-Anzeigenbloecke "GOLD - Profil - *" des Kontos
 * ca-pub-1901800422608466 als Werbemittel (Ticket #9 des Vorhabens
 * "GOLD-Bloecke auf dem Firmenprofil, 8 Portale").
 *
 * Eigener Seeder und eigene Datensaetze: {@see AdUnitsGoldSeeder} pflegt die
 * gleichnamigen Bloecke des Kontos ca-pub-2563111817853634. Beide Mengen
 * leben nebeneinander, unterschieden allein durch das Namens-Suffix
 * {@see self::SUFFIX} — der Name ist der Schluessel des Upserts, ein zweiter
 * Lauf verdoppelt also nichts und fasst den Altbestand nicht an.
 *
 * Die Publisher-ID steht NICHT am Werbemittel: sie kommt je Portal aus
 * `ad_settings.adsense_publisher_id` (sonst `ad-slots.adsense.publisher_id`),
 * der AdSenseProvider holt sie aus dem Render-Kontext. Das Suffix im Namen
 * ist reine Beschriftung, damit im Panel sichtbar ist, zu welchem Konto ein
 * Block gehoert.
 *
 * Aufruf: php artisan db:seed --class=AdUnitsGold1901Seeder
 */
class AdUnitsGold1901Seeder extends Seeder
{
    /** Konto, zu dem diese sieben Bloecke gehoeren. */
    public const PUBLISHER_ID = 'ca-pub-1901800422608466';

    /** Namens-Suffix, das die Bloecke dieses Kontos kennzeichnet. */
    public const SUFFIX = ' [pub-1901800422608466]';

    /**
     * Grundname => [slot_id, Format-Schluessel aus `ad-slots.formats`].
     * Reihenfolge wie auf dem Profil von oben nach unten, danach die
     * Seitenspalte.
     *
     * Der Katalog kennt nur fuenf Format-Schluessel (responsive,
     * rectangle_300x250, leaderboard_728x90, in_feed, in_article) — weder
     * Multiplex noch ein festes 300x600. Die fachliche Vorgabe ist darum auf
     * das jeweils naechstpassende registrierte Format abgebildet; ein neues
     * Format anzulegen waere eine Katalogaenderung ausserhalb dieses Tickets.
     *
     * @var array<string, array{string, string}>
     */
    private const UNITS = [
        /*
         * Ziel: company_before_header_card. Der Anker laesst ausdruecklich
         * nur `rectangle_300x250` zu (280/250 px Reservierung) — `responsive`
         * bestimmt seine Hoehe selbst und sprengt die Reservierung.
         */
        'GOLD - Profil - Top' => ['7857921592', 'rectangle_300x250'],

        /*
         * Textfluss-Anker auf dem Profil (company_after_header_card,
         * company_after_about, company_after_reviews). In-Article wie
         * fachlich vorgegeben; `layout_key` bleibt leer, den braucht nur
         * In-Feed.
         */
        'GOLD - Profil - Content 1' => ['7666349905', 'in_article'],
        'GOLD - Profil - Content 2' => ['5214526510', 'in_article'],
        'GOLD - Profil - Content 3' => ['5873002064', 'in_article'],

        /*
         * Multiplex gibt es als Format-Schluessel nicht. Ziel ist
         * company_before_footer mit 100/90 px Reservierung, auf der
         * `responsive` nicht erlaubt ist — bleibt `leaderboard_728x90`, das
         * einzige dort registrierte Format und genau die Reservierung.
         */
        'GOLD - Profil - Bottom Multiplex' => ['8962199836', 'leaderboard_728x90'],

        /*
         * Seitenspalte oben (company_sidebar_top, 250 px): 300x250 ist
         * registriert und passt genau.
         */
        'GOLD - Profil - Sidebar Top' => ['2222451539', 'rectangle_300x250'],

        /*
         * Seitenspalte klebend (company_sidebar_sticky, 600 px): 300x600
         * gibt es nicht. Naechstpassendes registriertes Display-Format ist
         * `rectangle_300x250` — es passt in die Reservierung, waehrend
         * `responsive` sie sprengen kann.
         */
        'GOLD - Profil - Sidebar Sticky' => ['3709873156', 'rectangle_300x250'],
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;

        foreach (self::UNITS as $baseName => [$slotId, $format]) {
            $name = $baseName.self::SUFFIX;

            $unit = AdUnit::query()->firstOrNew(['name' => $name]);
            $existed = $unit->exists;

            $unit->provider = AdProviderType::ADSENSE->value;
            $unit->config_json = ['slot_id' => $slotId, 'format' => $format];
            $unit->is_active = true;
            $unit->archived_at = null;
            $unit->save();

            $existed ? $updated++ : $created++;

            $this->command?->info("{$name}: Slot {$slotId}, Format {$format}");
        }

        $this->command?->info(sprintf(
            'GOLD-Werbemittel (%s): %d angelegt, %d aktualisiert.',
            self::PUBLISHER_ID,
            $created,
            $updated,
        ));
    }
}
