<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * SEO-Templates der Stadtseite fuer das Elektriker-Portal (#10).
 *
 * Idempotent: schreibt nur Felder, die der Tenant noch nicht gepflegt hat.
 * Alle anderen Portale bleiben auf CityMetaTemplates::DEFAULTS.
 *
 * php artisan db:seed --class=SeoTemplateSeeder --force
 */
class SeoTemplateSeeder extends Seeder
{
    /**
     * Domain-Stichwort => Templates.
     *
     * @var array<string, array<string, string>>
     */
    private const TEMPLATES = [
        'elektriker' => [
            TenantConfigConstants::SEO_TRADE_PLURAL => 'Elektriker',
            TenantConfigConstants::SEO_CITY_TITLE => 'Die {count} besten Elektriker in {city} ({year}) | Empfehlungen & Notdienst',
            TenantConfigConstants::SEO_CITY_DESCRIPTION => 'Vergleiche {count} Elektriker in {city}: Bewertungen, Kontakt, Öffnungszeiten und Notdienst. Kostenlos anfragen.',
            TenantConfigConstants::SEO_CITY_HEADING => 'Die {count} besten Elektriker in {city}',
            TenantConfigConstants::SEO_CITY_FALLBACK_TITLE => 'Elektriker in {city} finden ({year}) | Kontakt & Notdienst',
            TenantConfigConstants::SEO_CITY_FALLBACK_DESCRIPTION => 'Elektriker in {city} finden: Bewertungen, Kontakt, Öffnungszeiten und Notdienst. Kostenlos anfragen.',
            TenantConfigConstants::SEO_CITY_FALLBACK_HEADING => 'Elektriker in {city} finden',
        ],
    ];

    public function run(TenantBrandingService $branding): void
    {
        Tenant::query()->each(function (Tenant $tenant) use ($branding): void {
            foreach (self::TEMPLATES as $needle => $templates) {
                if (! Str::contains(Str::lower((string) $tenant->domain), $needle)) {
                    continue;
                }

                $missing = array_filter(
                    $templates,
                    fn (string $value, string $key): bool => blank($tenant->getAttribute($key)),
                    ARRAY_FILTER_USE_BOTH,
                );

                if ($missing !== []) {
                    $branding->setMany($tenant, $missing);
                }

                $this->command?->info("{$tenant->domain}: ".count($missing).' SEO-Template(s) gesetzt.');
            }
        });
    }
}
