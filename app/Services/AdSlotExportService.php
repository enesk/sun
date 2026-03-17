<?php

namespace App\Services;

use App\Models\Portal\AdSlot;
use App\Models\Tenant;

class AdSlotExportService
{
    /**
     * Exportiert alle Ad-Slots eines Tenants als Array.
     */
    public function export(Tenant $tenant): array
    {
        $slots = $tenant->run(function () {
            return AdSlot::sorted()->get([
                'name',
                'position',
                'code',
                'is_active',
                'sort_order',
                'device_visibility',
            ])->toArray();
        });

        return [
            'meta' => [
                'schema_version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'source_tenant' => $tenant->name,
                'slot_count' => count($slots),
            ],
            'slots' => $slots,
        ];
    }

    /**
     * Exportiert alle Ad-Slots eines Tenants als JSON-String.
     */
    public function exportToJson(Tenant $tenant): string
    {
        return json_encode($this->export($tenant), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
