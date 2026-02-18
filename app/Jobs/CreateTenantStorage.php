<?php

declare(strict_types=1);

namespace App\Jobs;

use Stancl\Tenancy\Contracts\Tenant;

/**
 * Erstellt Storage-Verzeichnisse + Symlink für einen neuen Tenant.
 *
 * Wird automatisch nach Tenant-Erstellung aufgerufen (TenancyServiceProvider).
 * Erstellt:
 *   - storage/tenant{uuid}/app/public/       (public files)
 *   - storage/tenant{uuid}/framework/cache/   (file cache)
 *   - storage/tenant{uuid}/framework/views/   (compiled views)
 *   - storage/tenant{uuid}/logs/              (log files)
 *   - public/storage-{uuid} → storage/tenant{uuid}/app/public (symlink)
 */
class CreateTenantStorage
{
    public function handle(Tenant $tenant): void
    {
        $tenantKey = $tenant->getTenantKey();
        $suffixBase = config('tenancy.filesystem.suffix_base', 'tenant');
        $basePath = storage_path() . '/' . $suffixBase . $tenantKey;

        // Storage-Verzeichnisse anlegen
        $directories = [
            $basePath . '/app/public',
            $basePath . '/framework/cache/data',
            $basePath . '/framework/views',
            $basePath . '/framework/sessions',
            $basePath . '/logs',
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Symlink: public/storage-{uuid} → storage/tenant{uuid}/app/public
        $linkPath = public_path('storage-' . $tenantKey);
        $targetPath = $basePath . '/app/public';

        if (! is_link($linkPath) && ! file_exists($linkPath)) {
            symlink($targetPath, $linkPath);
        }
    }
}
