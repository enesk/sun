<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdUnit;
use App\Ads\Observers\AdCacheInvalidator;
use Illuminate\Database\Seeder;

/**
 * Belegt die sieben Anker des Seitentyps `company` mit den GOLD-Werbemitteln
 * des Kontos ca-pub-1901800422608466 (Ticket #10).
 *
 * Zuordnung nach der Position im Layout (Katalog-`sort`), nicht nach
 * Beschriftung:
 *
 *   sort  5  company_before_header_card   GOLD - Profil - Top
 *   sort 10  company_after_header_card    GOLD - Profil - Content 1 (nur mobil)
 *   sort 20  company_after_about          GOLD - Profil - Content 2
 *   sort 30  company_after_reviews        GOLD - Profil - Content 3
 *   sort 35  company_sidebar_top          GOLD - Profil - Sidebar Top
 *   sort 40  company_sidebar_sticky       GOLD - Profil - Sidebar Sticky
 *   sort 50  company_before_footer        GOLD - Profil - Bottom Multiplex
 *
 * Das Geraet des Ankers gewinnt: AdPlacement::saving() verengt die Wahl auf
 * die Erlaubnis des Katalogs.
 *
 * Diese Belegungen stehen neben denen des ersten Kontos
 * ({@see AdPlacementsGoldCompanySeeder}) — derselbe Anker, anderes
 * Werbemittel, andere Portalliste. Die Tabelle hat dafuer bewusst keinen
 * Unique-Index; welche Belegung ein Portal sieht, entscheidet der Pivot, und
 * die beiden Portalmengen sind disjunkt.
 *
 * Portalliste aus {@see AdSettingsGold1901Seeder} — dieselbe Quelle wie
 * {@see AdSettingsGold1901Seeder}, `applies_to_all_tenants = false`. Der Pivot
 * loest kein Model-Ereignis aus, der Resolver-Cache wird darum nach dem
 * `sync()` ausdruecklich geleert (AdCacheInvalidator::forPlacement()).
 *
 * Die Namen der Werbemittel kommen aus {@see AdUnitsGold1901Seeder}
 * (Grundname + Konto-Suffix) — eine Quelle, damit beide Seeder dieselben
 * sieben Datensaetze treffen.
 *
 * Idempotent per (slot_key, ad_unit_id).
 *
 * Aufruf: php artisan db:seed --class=AdPlacementsGold1901CompanySeeder
 */
class AdPlacementsGold1901CompanySeeder extends Seeder
{
    /** Vorgabe-Prioritaet portalgenauer Belegungen (#10). */
    private const PRIORITY = 10;

    /**
     * slot_key => [Name des Werbemittels ohne Konto-Zusatz, gewuenschtes Geraet]
     *
     * @var array<string, array{string, AdDevice}>
     */
    private const PLACEMENTS = [
        'company_before_header_card' => ['GOLD - Profil - Top', AdDevice::ALL],
        'company_after_header_card' => ['GOLD - Profil - Content 1', AdDevice::MOBILE],
        'company_after_about' => ['GOLD - Profil - Content 2', AdDevice::ALL],
        'company_after_reviews' => ['GOLD - Profil - Content 3', AdDevice::ALL],
        'company_sidebar_top' => ['GOLD - Profil - Sidebar Top', AdDevice::DESKTOP],
        'company_sidebar_sticky' => ['GOLD - Profil - Sidebar Sticky', AdDevice::DESKTOP],
        'company_before_footer' => ['GOLD - Profil - Bottom Multiplex', AdDevice::ALL],
    ];

    /**
     * Portal-IDs der Freigabe aus #8 — eine Quelle fuer beide Seeder, sonst
     * stehen Belegungen in Portalen ohne Publisher-ID oder umgekehrt.
     *
     * @return list<int|string>
     */
    private static function tenantIds(): array
    {
        return array_map(
            fn ($tenant) => $tenant->getKey(),
            AdSettingsGold1901Seeder::tenants(),
        );
    }

    public function run(): void
    {
        $tenantIds = self::tenantIds();

        if ($tenantIds === []) {
            $this->command?->error('Keine Portale der Domains des Kontos pub-1901800422608466 gefunden — nichts belegt.');

            return;
        }

        foreach (self::PLACEMENTS as $slotKey => [$base, $device]) {
            $unitName = $base.AdUnitsGold1901Seeder::SUFFIX;

            $unit = AdUnit::query()
                ->where('provider', AdProviderType::ADSENSE->value)
                ->where('name', $unitName)
                ->first();

            if ($unit === null) {
                $this->command?->error("Werbemittel fehlt: {$unitName} — zuerst AdUnitsGold1901Seeder laufen lassen.");

                continue;
            }

            $placement = AdPlacement::query()
                ->where('slot_key', $slotKey)
                ->where('ad_unit_id', $unit->getKey())
                ->first() ?? new AdPlacement([
                    'slot_key' => $slotKey,
                    'ad_unit_id' => $unit->getKey(),
                ]);

            $previousTenantIds = $placement->exists
                ? $placement->tenants()->pluck('tenants.id')->all()
                : [];

            $placement->device = $device;
            $placement->status = AdPlacementStatus::ACTIVE;
            $placement->priority = self::PRIORITY;
            $placement->starts_at = null;
            $placement->ends_at = null;
            $placement->applies_to_all_tenants = false;
            $placement->save();

            $placement->tenants()->sync($tenantIds);
            AdCacheInvalidator::forPlacement($placement, $previousTenantIds);

            $this->command?->info(sprintf(
                '%s <- %s (%s), %d Portale',
                $slotKey,
                $unitName,
                $placement->device->value,
                count($tenantIds),
            ));
        }

        $this->command?->info('GOLD-Belegungen (pub-1901800422608466) auf dem Firmenprofil veroeffentlicht.');
    }
}
