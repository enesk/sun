<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Models\TenantContentSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Legt fuer jeden bestehenden Tenant genau eine Zeile in
 * `tenant_content_settings` mit Defaults an.
 *
 * Der Seeder ist idempotent: vorhandene Einstellungen werden nicht
 * ueberschrieben, es wird nur die fehlende Zeile ergaenzt.
 */
class TenantContentSettingSeeder extends Seeder
{
    /**
     * Branchen mit erhoehten Sorgfaltsanforderungen (Gesundheit, Geld, Recht,
     * Sicherheit). Abgleich gegen den Slug aus Tenant-Name und -Domain.
     *
     * @var array<int, string>
     */
    private const YMYL_SLUGS = [
        'apotheke',
        'arzt',
        'zahnarzt',
        'unfall',
        'klinik',
        'pflege',
        'bestatter',
        'anwalt',
        'recht',
        'steuer',
        'finanz',
        'kredit',
        'versicherung',
        'immo',
        'energie',
    ];

    /**
     * Standard-Property fuer die Search Console (#9). Die Domain-Property
     * ('sc-domain:...') deckt alle Protokoll- und Subdomain-Varianten ab und
     * ist damit die richtige Vorbelegung. Ohne Domain bleibt das Feld leer,
     * der Connector ueberspringt den Mandanten dann mit einer Warnung.
     */
    private function gscProperty(Tenant $tenant): ?string
    {
        $domain = is_string($tenant->domain) ? trim($tenant->domain) : '';

        return $domain !== '' ? 'sc-domain:'.$domain : null;
    }

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$created, &$skipped): void {
            $isYmyl = $this->isYmyl($tenant);

            $tenant->run(function () use ($tenant, $isYmyl, &$created, &$skipped): void {
                if (TenantContentSetting::query()->exists()) {
                    $skipped++;

                    return;
                }

                // `is_active` bleibt bewusst ungesetzt: die Vorgabe der
                // Spalte ist false, freigeschaltet wird einzeln beim
                // Rollout (#26, `content:rollout`).
                TenantContentSetting::create([
                    'auto_publish_threshold' => $isYmyl ? 90 : 80,
                    'is_ymyl' => $isYmyl,
                    'tone' => 'sachlich',
                    'preferred_states_json' => [],
                    'author_name' => $tenant->name.' Redaktion',
                    'author_bio' => null,
                    'organization_same_as_json' => [],
                    'gsc_property' => $this->gscProperty($tenant),
                ]);

                $created++;
            });
        });

        $this->command?->info("Content-Einstellungen: {$created} angelegt, {$skipped} bereits vorhanden.");
    }

    /**
     * YMYL-Erkennung ueber den Slug aus Tenant-Name und -Domain.
     */
    private function isYmyl(Tenant $tenant): bool
    {
        $slug = Str::slug($tenant->name).'-'.Str::slug((string) $tenant->domain);

        foreach (self::YMYL_SLUGS as $needle) {
            if (str_contains($slug, $needle)) {
                return true;
            }
        }

        return false;
    }
}
