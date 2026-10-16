<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant as TenantModel;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Portal-Domain, public-Disk und file-Cache je Tenant — und hinterher wieder
 * zentral (#24).
 *
 * Vorher hat ein Listener auf `TenancyBootstrapped` diese drei Werte gesetzt
 * und nie zurueckgestellt. Nach einem `$tenant->run(...)` las im Panel, in
 * Commands und im Queue-Worker jeder die fremde Portal-Domain, der
 * `config('app.url')`, `url()`, `route(..., absolute: true)` oder `URL::to()`
 * nimmt — etwa Links in einer Mail, die im selben Prozessdurchlauf entsteht.
 * Im Worker laeuft ein Portal nach dem anderen durch denselben Prozess, der
 * falsche Wert blieb also ueber Job-Grenzen hinweg stehen.
 *
 * Als Bootstrapper ist beides an einer Stelle: `bootstrap()` setzt, `revert()`
 * stellt zurueck. Stancl haelt Bootstrapper als Singletons, die zentralen
 * Werte werden darum einmal im Konstruktor gemerkt — der laeuft vor dem ersten
 * `bootstrap()` und damit im zentralen Kontext (siehe
 * `Tenancy::getBootstrappers()`).
 *
 * Reihenfolge: gehoert hinter den FilesystemTenancyBootstrapper, weil der
 * `storage_path()` auf das Tenant-Verzeichnis umbiegt und der Cache-Pfad
 * darauf aufbaut.
 */
class TenantUrlBootstrapper implements TenancyBootstrapper
{
    /** Die zentralen Werte, einmal gemerkt. @var array<string, mixed> */
    protected array $central;

    public function __construct()
    {
        $this->central = [
            'app.url' => config('app.url'),
            'filesystems.disks.public.url' => config('filesystems.disks.public.url'),
            'cache.stores.file.path' => config('cache.stores.file.path'),
            'cache.stores.file.lock_path' => config('cache.stores.file.lock_path'),
        ];
    }

    public function bootstrap(Tenant $tenant)
    {
        $tenantKey = $tenant->getTenantKey();

        // APP_URL dynamisch auf die aktuelle Tenant-Domain setzen.
        // Ohne das generiert Spatie Media Library alle URLs mit der
        // statischen .env APP_URL — Cross-Tenant URL-Leak.
        $domain = $tenant instanceof TenantModel ? $tenant->domain : null;

        if ($domain) {
            $scheme = request()->isSecure() ? 'https' : 'http';
            config(['app.url' => $scheme.'://'.$domain]);
        }

        // URL der public Disk auf den Tenant-Symlink umbiegen:
        // public/storage-{tenant_key} → storage/tenant{key}/app/public
        config([
            'filesystems.disks.public.url' => config('app.url').'/storage-'.$tenantKey,
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

    public function revert()
    {
        config($this->central);

        Cache::forgetDriver('file');
    }
}
