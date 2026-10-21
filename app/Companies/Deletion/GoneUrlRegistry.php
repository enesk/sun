<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

use App\Companies\Deletion\Models\GoneUrl;
use App\Models\Portal\Company;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Die eine Stelle, die `gone_urls` schreibt, liest und den Cache dazu
 * aktuell haelt (#8).
 *
 * Laeuft immer im Tenant-Kontext — die Tabelle liegt in der Tenant-DB, und
 * der File-Cache zeigt je Portal auf ein anderes Verzeichnis
 * (`SetTenantStorageUrl`). Ein `Cache::forget` aus dem zentralen Kontext
 * wuerde den Portal-Cache nicht treffen.
 */
class GoneUrlRegistry
{
    public const CACHE_KEY = 'portal.gone_urls';

    public const CACHE_TTL = 3600;

    /**
     * Beide URL-Muster eines Betriebs — das aktive und das andere. Wechselt
     * ein Portal `company_url_pattern`, bleibt der 410 damit gueltig.
     *
     * @return list<string>
     */
    public function pathsFor(Company $company): array
    {
        $companySlug = $company->id.'-'.$company->slug;
        $paths = ['/'.$companySlug];

        // getAttribute() statt ->slug: Company::city() ist ein BelongsTo ohne
        // Generics, statische Analyse sieht dort nur Model.
        $citySlug = $company->city?->getAttribute('slug');

        if (is_string($citySlug) && $citySlug !== '') {
            $paths[] = '/'.$citySlug.'/'.$companySlug;
        }

        // Falls die Pipeline nach dem Umschreiben des Slugs laeuft, zaehlt
        // auch der Pfad aus dem Original.
        $originalSlug = (array) ($company->pre_deletion_json ?? []);

        if (isset($originalSlug['slug']) && $originalSlug['slug'] !== $company->slug) {
            $paths[] = '/'.$company->id.'-'.$originalSlug['slug'];

            if (is_string($citySlug) && $citySlug !== '') {
                $paths[] = '/'.$citySlug.'/'.$company->id.'-'.$originalSlug['slug'];
            }
        }

        return array_values(array_unique(array_map([self::class, 'normalize'], $paths)));
    }

    /**
     * Pfade eintragen. Wiederholbar: `path` ist unique, ein zweiter Lauf
     * aktualisiert nur den Grund.
     *
     * @param  list<string>  $paths
     * @return list<string> die tatsaechlich eingetragenen Pfade
     */
    public function register(array $paths, ?int $companyId, ?string $reason = null): array
    {
        $stored = [];

        foreach ($paths as $path) {
            $path = self::normalize($path);

            if ($path === '' || $path === '/') {
                continue;
            }

            GoneUrl::query()->updateOrCreate(
                ['path' => $path],
                ['company_id' => $companyId, 'reason' => $reason],
            );

            $stored[] = $path;
        }

        $this->flushCache();

        return $stored;
    }

    /**
     * Die 410-Regel aufheben — der Betrieb ist wieder eingetragen.
     *
     * Aufgerufen beim Anlegen eines Profils ({@see Company} Observer-Weg in
     * {@see self::releaseForCompany()}): sonst liefert die neue Seite 410
     * und waere fuer Besucher und Suchmaschinen unsichtbar.
     *
     * @param  list<string>  $paths
     */
    public function release(array $paths): int
    {
        $normalized = array_values(array_filter(array_map([self::class, 'normalize'], $paths)));

        if ($normalized === []) {
            return 0;
        }

        $deleted = GoneUrl::query()->whereIn('path', $normalized)->delete();

        $this->flushCache();

        return (int) $deleted;
    }

    /**
     * Alle Pfade eines Betriebs freigeben — inklusive derer, die ein
     * anderer (gleichnamiger) Eintrag hinterlassen hat.
     */
    public function releaseForCompany(Company $company): int
    {
        $deleted = $this->release($this->pathsFor($company));
        $deleted += (int) GoneUrl::query()->where('company_id', $company->getKey())->delete();

        $this->flushCache();

        return $deleted;
    }

    /**
     * Wird ein Profil neu angelegt und belegt dabei einen Pfad, der auf 410
     * steht, muss die Regel weg — sonst ist der neue Eintrag fuer Besucher
     * und Suchmaschinen unsichtbar, und niemand sucht den Fehler in einer
     * Tabelle namens `gone_urls`.
     *
     * Erst der Cache, dann die Datenbank: ein Import mit 19 600 Betrieben
     * soll nicht 19 600 Abfragen gegen eine leere Tabelle fahren.
     */
    public function releaseOnReuse(Company $company): int
    {
        $paths = $this->pathsFor($company);
        $cached = $this->cachedPaths();

        if ($cached === []) {
            return 0;
        }

        $hits = array_values(array_filter($paths, fn (string $path): bool => isset($cached[$path])));

        return $hits === [] ? 0 : $this->release($hits);
    }

    /**
     * Gecachte Pfadliste des laufenden Portals. Die Middleware ruft das je
     * Seitenaufruf, deshalb eine Stunde Cache und ein Array statt Abfrage.
     *
     * @return array<string, true>
     */
    public function cachedPaths(): array
    {
        try {
            $paths = Cache::remember(
                TenantCache::key(self::CACHE_KEY),
                self::CACHE_TTL,
                fn (): array => $this->load(),
            );
        } catch (Throwable) {
            // Ein kaputter Cache-Store darf keine Seite ausknipsen.
            return [];
        }

        return is_array($paths) ? $paths : [];
    }

    public function has(string $path): bool
    {
        return isset($this->cachedPaths()[self::normalize($path)]);
    }

    public function flushCache(): void
    {
        TenantCache::forget(self::CACHE_KEY);
    }

    /**
     * Kleingeschrieben, mit fuehrendem Slash, ohne Query und ohne
     * abschliessenden Slash — dieselbe Form wie in der Middleware.
     */
    public static function normalize(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = strtok($path, '?') ?: $path;
        $path = '/'.trim(rawurldecode($path), '/');

        return mb_strtolower($path);
    }

    /**
     * @return array<string, true>
     */
    private function load(): array
    {
        if (! Schema::hasTable('gone_urls')) {
            return [];
        }

        /** @var list<string> $paths */
        $paths = GoneUrl::query()->pluck('path')->all();

        return array_fill_keys($paths, true);
    }
}
