<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Models\AdSetting;
use App\Ads\Support\AdsTxtBuilder;
use Illuminate\Database\Seeder;

/**
 * Portal-Einstellungen der GOLD-Belegung (SUN-ADS-GOLD-001, Ticket #2).
 *
 * Setzt fuer die Portale aus {@see AdsGoldTargets} die Publisher-ID des
 * GOLD-Kontos und den Hauptschalter `ads_enabled`. Auto Ads wird bewusst
 * **nicht** angefasst (Festlegung vom 16.10.2026: bleibt wie eingestellt);
 * fehlt die Zeile ganz, entsteht sie mit {@see AdSetting::defaults()} und
 * damit Auto Ads aus.
 *
 * Die Google-Zeile der ads.txt entsteht daraus automatisch
 * ({@see AdsTxtBuilder}) — genau einmal, auch wenn der Altbestand sie in
 * `ads_txt_extra` mitbringt (#41). Deshalb wird hier nichts an den
 * Zusatzzeilen geaendert.
 *
 * Idempotent; den Resolver- und ads.txt-Cache der betroffenen Portale leert
 * der Observer (App\Ads\Observers\AdCacheInvalidator) beim Speichern.
 *
 * Aufruf: php artisan db:seed --class=AdSettingsGoldSeeder
 */
class AdSettingsGoldSeeder extends Seeder
{
    public function run(): void
    {
        $builder = new AdsTxtBuilder;
        $touched = 0;

        foreach (AdsGoldTargets::tenants() as $tenant) {
            $setting = AdSetting::query()->forTenant($tenant->getKey())->first()
                ?? new AdSetting(['tenant_id' => $tenant->getKey()] + AdSetting::defaults());

            $setting->ads_enabled = true;
            $setting->adsense_publisher_id = AdsGoldTargets::PUBLISHER_ID;
            $setting->save();

            $hasGoogleLine = in_array(
                AdsGoldTargets::ADS_TXT_LINE,
                $builder->lines($tenant->getKey()),
                true,
            );

            $this->command?->info(sprintf(
                '%s (#%s): freigegeben, %s, ads.txt-Zeile %s',
                $tenant->domain,
                $tenant->getKey(),
                AdsGoldTargets::PUBLISHER_ID,
                $hasGoogleLine ? 'vorhanden' : 'FEHLT',
            ));

            $touched++;
        }

        $this->command?->info("Werbe-Einstellungen GOLD: {$touched} Portale gesetzt.");
    }
}
