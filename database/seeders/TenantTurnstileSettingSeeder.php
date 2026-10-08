<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Models\TenantTurnstileSetting;
use Illuminate\Database\Seeder;

/**
 * Legt fuer jeden bestehenden Tenant genau eine Zeile in
 * `tenant_turnstile_settings` mit den Vorgaben aus #3 an.
 *
 * Idempotent: vorhandene Einstellungen bleiben unangetastet, auch wenn der
 * Betreiber sie im Admin (#9) schon angepasst hat. Keys bleiben leer, es gilt
 * also die .env-Vorgabe; eigene Widgets je Portal kommen in #14.
 *
 * Aufruf: php artisan db:seed --class=TenantTurnstileSettingSeeder
 */
class TenantTurnstileSettingSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$created, &$skipped): void {
            $exists = TenantTurnstileSetting::query()
                ->where('tenant_id', $tenant->getKey())
                ->exists();

            if ($exists) {
                $skipped++;

                return;
            }

            TenantTurnstileSetting::create([
                'tenant_id' => $tenant->getKey(),
                'is_enabled' => true,
                'site_key' => null,
                'secret_key' => null,
                'fail_mode' => ResolvedTurnstileConfig::FAIL_MODE_OPEN,
                'actions_json' => TenantTurnstileSetting::defaultActions(),
                'updated_by' => null,
            ]);

            $created++;
        });

        $this->command?->info("Turnstile-Einstellungen: {$created} angelegt, {$skipped} bereits vorhanden.");
    }
}
