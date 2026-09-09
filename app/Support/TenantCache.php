<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Mandantenanteil fuer Cache-Schluessel (#79).
 *
 * Der `CacheTenancyBootstrapper` ist in config/tenancy.php abgeschaltet, der
 * Anwendungscache also nicht mandantengetrennt. Jeder Schluessel, der Daten
 * eines Portals traegt, muss den Mandanten deshalb selbst im Namen fuehren —
 * sonst liefert das zuerst befuellte Portal seine Daten an alle anderen.
 *
 * Der Schluessel ist die `uuid` des Tenants (`getTenantKey()`), ausserhalb
 * eines Mandanten `central`.
 */
final class TenantCache
{
    /**
     * Stellt dem Schluessel den Mandantenanteil voran:
     * `portal.stats` wird zu `<uuid>.portal.stats`.
     */
    public static function key(string $key): string
    {
        return self::tenantKey().'.'.$key;
    }

    public static function tenantKey(): string
    {
        return (string) (tenant()?->getTenantKey() ?? 'central');
    }

    /**
     * Verwirft den Schluessel des laufenden Mandanten.
     */
    public static function forget(string $key): void
    {
        Cache::forget(self::key($key));
    }
}
