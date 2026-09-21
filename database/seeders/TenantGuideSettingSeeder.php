<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Guide\Models\TenantGuideSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legt fuer jeden bestehenden Tenant genau eine Zeile in
 * `tenant_guide_settings` mit Defaults an.
 *
 * Idempotent: vorhandene Einstellungen bleiben unangetastet. `is_active`
 * bleibt aus (Spaltenvorgabe), freigeschaltet wird je Portal im Go-Live (#21).
 * YMYL-Kennzeichen und Autor werden aus `tenant_content_settings` uebernommen,
 * soweit dort gepflegt; die Branchenbegriffe aus tenant('terms').
 */
class TenantGuideSettingSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$created, &$skipped): void {
            $tenant->run(function () use ($tenant, &$created, &$skipped): void {
                if (TenantGuideSetting::query()->exists()) {
                    $skipped++;

                    return;
                }

                $content = Schema::hasTable('tenant_content_settings')
                    ? DB::table('tenant_content_settings')->first(['is_ymyl', 'author_name', 'author_bio'])
                    : null;

                $isYmyl = (bool) ($content->is_ymyl ?? false);
                $terms = is_array($tenant->terms ?? null) ? $tenant->terms : [];

                TenantGuideSetting::create([
                    'is_ymyl' => $isYmyl,
                    'auto_publish_threshold' => $isYmyl ? TenantGuideSetting::YMYL_THRESHOLD : TenantGuideSetting::DEFAULT_THRESHOLD,
                    'author_name' => $content->author_name ?? "{$tenant->name} Redaktion",
                    'author_bio' => $content->author_bio ?? null,
                    'branch' => $this->term($terms, 'branche'),
                    'branch_plural' => $this->term($terms, 'branche_plural'),
                    'category_cta_mapping_json' => [],
                    'source_whitelist_json' => [],
                    'source_blacklist_json' => [],
                ]);

                $created++;
            });
        });

        $this->command?->info("Ratgeber-Einstellungen: {$created} angelegt, {$skipped} bereits vorhanden.");
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function term(array $terms, string $key): ?string
    {
        $value = trim((string) ($terms[$key] ?? ''));

        return $value !== '' ? $value : null;
    }
}
