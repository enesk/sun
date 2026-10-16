<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Enums\AdPageType;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-Schluessel und Leeren der aufgeloesten Belegungen
 * (docs/ad-slots.md 8, Ticket #4).
 *
 *   Schluessel:  ads:v{katalogVersion}:{tenantId}:{pageType}
 *   Wert:        ads_enabled, Publisher-ID und die Belegungen je Anker/Geraet
 *   Dauer:       config('ad-slots.cache_ttl'), Vorgabe eine Stunde
 *
 * Drei Dinge, die hier bewusst so sind:
 *
 * * **Der Tenant steht im Schluessel.** Der Cache ist in diesem Projekt nicht
 *   mandantengetrennt; ohne Tenant im Schluessel sehen Portale fremde
 *   Belegungen.
 * * **Geleert wird im Tenant-Kontext.** Der file-Store wechselt je Portal
 *   (App\Support\Tenancy\TenantUrlBootstrapper). Ein im Portal geschriebener
 *   Schluessel laesst sich aus dem Admin-Panel nicht mit Cache::forget()
 *   entfernen — dafuer `$tenant->run(...)`. Muster:
 *   App\Models\TenantText::forgetFor().
 * * **Die Katalogversion steht im Schluessel.** Eine Aenderung an
 *   config/ad-slots.php entwertet alle Eintraege, ohne dass irgendwo geleert
 *   werden muss.
 */
final class AdCache
{
    public static function key(int|string $tenantId, AdPageType $pageType): string
    {
        return sprintf('ads:v%d:%s:%s', AdSlotCatalog::version(), $tenantId, $pageType->value);
    }

    /**
     * Schluessel der fertigen ads.txt eines Portals (#10). Steht hier, damit
     * {@see forgetTenant()} ihn mit vergisst — sonst behielte eine Domain nach
     * dem Umstellen der Publisher-ID bis zu einer Stunde die alte Datei.
     */
    public static function adsTxtKey(int|string $tenantId): string
    {
        return sprintf('ads-txt:v%d:%s', AdSlotCatalog::version(), $tenantId);
    }

    public static function ttl(): int
    {
        return (int) config('ad-slots.cache_ttl', 3600);
    }

    /**
     * Alle Seitentypen eines Portals vergessen — zentral und im Portal selbst,
     * weil beide Kontexte in verschiedene Stores schreiben.
     */
    public static function forgetTenant(Tenant|int|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $keys = [];

        foreach (AdPageType::cases() as $pageType) {
            $keys[] = self::key($tenantId, $pageType);
        }

        $keys[] = self::adsTxtKey($tenantId);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        if ((string) tenant()?->getKey() === (string) $tenantId) {
            return;
        }

        $model = $tenant instanceof Tenant ? $tenant : Tenant::query()->find($tenantId);

        $model?->run(function () use ($keys): void {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        });
    }

    /**
     * Alle Portale. Teuer (je Portal ein Kontextwechsel), deshalb nur aus dem
     * Job {@see \App\Ads\Jobs\FlushAdCache} heraus und nie im Request.
     *
     * @param  iterable<int, Tenant>|null  $tenants  null = alle
     */
    public static function forgetAll(?iterable $tenants = null): void
    {
        foreach ($tenants ?? Tenant::query()->cursor() as $tenant) {
            self::forgetTenant($tenant);
        }
    }
}
