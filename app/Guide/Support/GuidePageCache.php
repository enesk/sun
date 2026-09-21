<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Seo\LlmsTxtBuilder;
use App\Support\TenantCache;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

/**
 * Seiten-Cache der oeffentlichen Ratgeber-Seiten (#17).
 *
 * Gecacht werden die fertig aufbereiteten View-Daten (Arrays, keine Modelle),
 * nicht das HTML — CSRF-Token, Anzeigen und SeoService-Zustand entstehen je
 * Anfrage neu. Alle Eintraege eines Portals haengen am Tag `<tenant>.guide`;
 * der Publisher (#12) verwirft sie nach jeder Veroeffentlichung, jedem
 * Rollback und jeder Kategorieaenderung mit GuidePageCache::flush(). Daran
 * haengen seit #18 auch /sitemap-ratgeber.xml (GuideSitemapGenerator) und
 * /llms.txt (LlmsTxtBuilder, eigener Schluessel, wird hier mit verworfen).
 *
 * Stores ohne Tags (file, database — lokal CACHE_DRIVER=file) bekommen
 * dieselbe Wirkung ueber einen Versionszaehler im Schluessel: flush() zaehlt
 * ihn hoch, alte Eintraege laufen ueber die TTL aus.
 */
final class GuidePageCache
{
    public const TTL_SECONDS = 3600;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        if (self::supportsTags()) {
            return self::tagged()->remember(TenantCache::key("guide.page.{$key}"), self::TTL_SECONDS, $callback);
        }

        return Cache::remember(TenantCache::key('guide.page.v'.self::version().".{$key}"), self::TTL_SECONDS, $callback);
    }

    /**
     * Verwirft alle Ratgeber-Seiten des laufenden Mandanten.
     */
    public static function flush(): void
    {
        LlmsTxtBuilder::flush();

        if (self::supportsTags()) {
            self::tagged()->flush();

            return;
        }

        Cache::forever(TenantCache::key('guide.page.version'), self::version() + 1);
    }

    public static function tag(): string
    {
        return TenantCache::key('guide');
    }

    private static function tagged(): TaggedCache
    {
        return Cache::tags([self::tag()]);
    }

    private static function version(): int
    {
        return (int) Cache::get(TenantCache::key('guide.page.version'), 1);
    }

    private static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
