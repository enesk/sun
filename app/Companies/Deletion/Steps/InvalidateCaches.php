<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Services\Seo\StructuredDataService;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caches des Portals verwerfen (#8): Profil, Stadtseite, Suche, Zaehler.
 *
 * Laeuft zwingend **im Tenant-Kontext**. Der `CacheTenancyBootstrapper` ist
 * abgeschaltet, dafuer biegt `App\Listeners\Tenant\SetTenantStorageUrl` den
 * File-Store je Portal auf ein eigenes Verzeichnis um — ein `Cache::forget`
 * aus dem Central-Panel wuerde den Portal-Cache nicht treffen und die
 * geloeschte Firma bliebe bis zum Ablauf der TTL in Listen und Zaehlern
 * stehen. Die Schluessel tragen zusaetzlich den Mandanten im Namen
 * ({@see TenantCache}).
 *
 * Ein nicht erreichbarer Cache-Store darf die Loeschung nicht aufhalten: der
 * Fehler steht im Protokoll, die Daten sind trotzdem weg.
 */
class InvalidateCaches implements DeletionStep
{
    /**
     * Listen, Zaehler und Seitenbausteine, in denen ein aktiver Betrieb
     * vorkommen kann (docs/profile-requests.md 6).
     */
    private const PORTAL_KEYS = [
        'portal.stats',
        'portal.stats.total',
        'portal.cities.hero',
        'portal.cities.sidebar',
        'portal.cities.public.index.top20',
        'portal.featured_companies',
        'portal.random_companies',
        'sun-v2.home.cities',
        'sun-v2.home.service_counts',
        'sun-v2.home.top_rated_pool',
        'sun-v2.cities.index.v3',
        'sun-v2.claim.claimed_count',
        'sun-v2.login.companies',
    ];

    public function key(): string
    {
        return 'invalidate_caches';
    }

    public function label(): string
    {
        return 'Caches leeren';
    }

    public function plan(DeletionContext $context): string
    {
        return sprintf(
            '%d Portal-Schlüssel plus Profil-JSON-LD und die Schlüssel der Stadt- und Kategorieseiten leeren.',
            count(self::PORTAL_KEYS),
        );
    }

    public function run(DeletionContext $context): StepResult
    {
        $keys = self::PORTAL_KEYS;
        $cityId = $context->company->city_id;

        if ($cityId !== null) {
            $keys[] = "seo.city.active_count.{$cityId}";
            $keys[] = "city.{$cityId}.jsonld.page1";
            $keys[] = "portal.categories.city.{$cityId}";
            $keys[] = "portal.cities.related.{$cityId}";
        }

        $forgotten = 0;
        $errors = [];

        foreach ($keys as $key) {
            try {
                TenantCache::forget($key);
                $forgotten++;
            } catch (Throwable $exception) {
                $errors[$key] = $exception->getMessage();
            }
        }

        // Profil-JSON-LD: eigener Schluessel-Aufbau im StructuredDataService.
        try {
            StructuredDataService::forget((int) $context->company->getKey());
            $forgotten++;
        } catch (Throwable $exception) {
            $errors['company.jsonld'] = $exception->getMessage();
        }

        // Ortslisten je Kategorie (CategoryController): der Schluessel traegt
        // die Kategorie-ID. Die Zuordnung ist zu diesem Zeitpunkt schon
        // geloest, deshalb ueber die am Anfang des Laufs gemerkten IDs.
        foreach ((array) $context->note('category_ids', []) as $categoryId) {
            try {
                TenantCache::forget("portal.cities.category.{$categoryId}");
                $forgotten++;
            } catch (Throwable $exception) {
                $errors["portal.cities.category.{$categoryId}"] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            return StepResult::failed(
                sprintf('%d Schlüssel nicht geleert.', count($errors)),
                ['forgotten' => $forgotten, 'errors' => $errors],
            );
        }

        return StepResult::ok(sprintf('%d Cache-Schlüssel geleert.', $forgotten), [
            'forgotten' => $forgotten,
            'store' => (string) config('cache.default'),
        ]);
    }
}
