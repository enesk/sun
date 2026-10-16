<?php

declare(strict_types=1);

namespace App\Ads\Observers;

use App\Ads\Jobs\FlushAdCache;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdSetting;
use App\Ads\Models\AdUnit;
use App\Ads\Support\AdCache;

/**
 * Haelt den Resolver-Cache aktuell (docs/ad-slots.md 8, Ticket #4).
 *
 * Registriert im AdsServiceProvider fuer ad_units, ad_placements und
 * ad_settings. Jede Aenderung an einer der drei Tabellen entwertet die
 * aufgeloesten Karten der betroffenen Portale — eine Belegung in Filament zu
 * pausieren wirkt damit bei der naechsten Seitenansicht, ohne `cache:clear`.
 *
 * Grenze von Eloquent: der Pivot `ad_placement_tenant` loest **kein**
 * Model-Ereignis aus. Nach einem `tenants()->sync(...)` muss die Verwaltung
 * (#8) deshalb {@see self::forPlacement()} selbst aufrufen — mit den Portalen
 * **vor** und **nach** der Aenderung, sonst behaelt ein abgewaehltes Portal
 * seine Anzeige.
 */
class AdCacheInvalidator
{
    /**
     * Grenze, ab der nicht mehr im Request geleert wird: jedes Portal kostet
     * einen Kontextwechsel samt Datenbankverbindung.
     */
    private const INLINE_LIMIT = 5;

    public function saved(AdPlacement|AdUnit|AdSetting $model): void
    {
        $this->invalidate($model);
    }

    /**
     * `deleting`, nicht `deleted`: beim Loeschen eines Werbemittels raeumt die
     * Datenbank seine Belegungen per ON DELETE CASCADE mit weg — danach ist
     * nicht mehr zu sehen, welche Portale betroffen waren.
     */
    public function deleting(AdPlacement|AdUnit|AdSetting $model): void
    {
        $this->invalidate($model);
    }

    /**
     * Nach dem Speichern einer Belegung samt Pivot aufrufen (#8).
     *
     * @param  list<int|string>  $additionalTenantIds  Portale, die vorher verknuepft waren
     */
    public static function forPlacement(AdPlacement $placement, array $additionalTenantIds = []): void
    {
        $invalidator = new self;

        if ($placement->applies_to_all_tenants) {
            FlushAdCache::dispatch();

            return;
        }

        $invalidator->flush(array_values(array_unique(array_merge(
            $placement->tenants()->pluck('tenants.id')->all(),
            $additionalTenantIds,
        ))));
    }

    private function invalidate(AdPlacement|AdUnit|AdSetting $model): void
    {
        if ($model instanceof AdSetting) {
            // Genau ein Portal — immer sofort, auch die Freigabe selbst.
            $this->flush([$model->tenant_id]);

            return;
        }

        if ($model instanceof AdPlacement) {
            self::forPlacement($model);

            return;
        }

        // Ein Werbemittel haengt an beliebig vielen Belegungen; netzweite
        // darunter heben die Portal-Liste auf.
        $placements = $model->placements()->get(['id', 'applies_to_all_tenants']);

        if ($placements->contains('applies_to_all_tenants', true)) {
            FlushAdCache::dispatch();

            return;
        }

        $tenantIds = AdPlacement::query()
            ->whereKey($placements->modelKeys())
            ->with('tenants:id')
            ->get()
            ->flatMap(fn (AdPlacement $placement) => $placement->tenants->modelKeys())
            ->unique()
            ->values()
            ->all();

        $this->flush($tenantIds);
    }

    /**
     * @param  list<int|string>  $tenantIds
     */
    private function flush(array $tenantIds): void
    {
        if ($tenantIds === []) {
            return;
        }

        if (count($tenantIds) > self::INLINE_LIMIT) {
            FlushAdCache::dispatch($tenantIds);

            return;
        }

        foreach ($tenantIds as $tenantId) {
            AdCache::forgetTenant($tenantId);
        }
    }
}
