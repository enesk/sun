<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Models\AdSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Legt fuer jedes bestehende Portal genau eine Zeile in `ad_settings` an
 * (SUN-AD-003, Ticket #3).
 *
 * Vorgabe ist `ads_enabled = false`: nach der Migration erscheint nirgends
 * Werbung, bis sie im Panel (#10) je Portal freigegeben wird. Idempotent —
 * vorhandene Zeilen bleiben unangetastet, auch schon freigegebene.
 *
 * Aufruf: php artisan db:seed --class=AdSettingSeeder
 */
class AdSettingSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$created, &$skipped): void {
            $exists = AdSetting::query()
                ->where('tenant_id', $tenant->getKey())
                ->exists();

            if ($exists) {
                $skipped++;

                return;
            }

            AdSetting::create(['tenant_id' => $tenant->getKey()] + AdSetting::defaults());

            $created++;
        });

        $this->command?->info("Werbe-Einstellungen: {$created} angelegt, {$skipped} bereits vorhanden.");
    }
}
