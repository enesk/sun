<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Events\TenancyBootstrapped;

class SetTenantStorageUrl
{
    public function handle(TenancyBootstrapped $event): void
    {
        $tenantKey = $event->tenancy->tenant->getTenantKey();

        // URL der public Disk auf den Tenant-Symlink umbiegen:
        // public/storage-{tenant_key} → storage/tenant{key}/app/public
        config([
            'filesystems.disks.public.url' => config('app.url') . '/storage-' . $tenantKey,
        ]);

        // Cache-Pfad für file-Treiber dynamisch auf Tenant-Storage umlenken.
        // config() wird beim Boot statisch resolvet, BEVOR suffix_storage_path
        // den storage_path() ändert. Deshalb muss der Pfad hier manuell gesetzt werden.
        $tenantCachePath = storage_path('framework/cache/data');
        config([
            'cache.stores.file.path' => $tenantCachePath,
            'cache.stores.file.lock_path' => $tenantCachePath,
        ]);

        // File-Cache-Store neu instanziieren, damit der neue Pfad greift
        Cache::forgetDriver('file');
    }
}
