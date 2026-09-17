<?php

declare(strict_types=1);

namespace App\Console\Commands\ProfileDescriptions\Concerns;

use App\Models\Tenant;

/**
 * --tenant= der profiles:*-Befehle: ID, UUID, Domain oder Domain ohne Endung
 * ('elektrikerportal' trifft elektrikerportal.com). Mehrdeutige Kurznamen
 * werden abgelehnt.
 */
trait ResolvesPortal
{
    protected function resolvePortal(): ?Tenant
    {
        $needle = mb_strtolower(trim((string) $this->option('tenant')));

        if ($needle === '') {
            $this->error('--tenant fehlt (ID, UUID, Domain oder Domain ohne Endung, z. B. elektrikerportal).');

            return null;
        }

        $tenant = is_numeric($needle)
            ? Tenant::query()->find((int) $needle)
            : Tenant::query()->where('uuid', $needle)->orWhere('domain', $needle)->first();

        if ($tenant !== null) {
            return $tenant;
        }

        $matches = Tenant::query()->where('domain', 'like', "{$needle}.%")->get();

        if ($matches->count() === 1) {
            /** @var Tenant */
            return $matches->first();
        }

        $this->error($matches->isEmpty()
            ? "Portal '{$needle}' nicht gefunden."
            : "Portal '{$needle}' ist mehrdeutig: ".$matches->pluck('domain')->implode(', '));

        return null;
    }

    /**
     * @return list<int>
     */
    protected function idsOption(): array
    {
        $ids = preg_split('/[\s,]+/', (string) $this->option('ids'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
    }
}
