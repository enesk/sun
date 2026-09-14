<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Portal\City;
use App\Support\CityUrl;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Gemeinsame Sitemap-Bausteine fuer Verzeichnisseiten (#7).
 *
 * Genutzt vom Befehl tenants:generate-sitemap und von GenerateTenantSitemapJob.
 * Staedte kommen ausschliesslich als statische Stadtseite /staedte/{slug} in
 * die Sitemap — nie als /firmen?city=..., das per SeoService noindex ist.
 */
final class SitemapGenerator
{
    /**
     * Alle benannten Staedte mit mindestens einem aktiven Betrieb.
     */
    public function addCityPages(Sitemap $sitemap, string $baseUrl): void
    {
        $sitemap->add(Url::create("{$baseUrl}/staedte")->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));

        City::query()
            ->named()
            ->whereNotNull('slug')
            ->where('slug', '<>', '')
            ->whereHas('companies', fn ($query) => $query->where('is_active', true))
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(1000, function ($cities) use ($sitemap, $baseUrl) {
                foreach ($cities as $city) {
                    $sitemap->add(
                        Url::create("{$baseUrl}/staedte/".rawurlencode((string) $city->slug))
                            ->setPriority(0.8)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setLastModificationDate($city->updated_at ?? now())
                    );
                }
            });
    }
}
