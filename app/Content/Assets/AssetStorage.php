<?php

declare(strict_types=1);

namespace App\Content\Assets;

use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ablage der Artikel-Assets (#16).
 *
 * Alles liegt unter `ratgeber/<tenant>/<slug>/` auf der Platte `public`. Die
 * Platte ist bereits mandantengetrennt (FilesystemTenancyBootstrapper haengt
 * sie an storage/tenant<uuid>/app/public und biegt ihre URL auf den Symlink
 * public/storage-<uuid> um); der Tenant-Ordner steht trotzdem im Pfad, damit
 * eine Datei auch ausserhalb ihres Kontexts zuordenbar bleibt.
 *
 * Der Pfad haengt am Slug des Entwurfs, nicht an seiner ID: derselbe Ordner
 * wie die spaetere URL des Artikels macht Nacharbeit ohne Datenbankblick
 * moeglich. Ein erneuter Lauf ueberschreibt die Dateien.
 */
class AssetStorage
{
    /**
     * Verzeichnis eines Entwurfs, relativ zur Platte.
     */
    public function directory(Tenant $tenant, string $slug): string
    {
        $base = trim((string) config('content.assets.base_path', 'ratgeber'), '/');
        $tenantSegment = Str::slug((string) $tenant->getTenantKey()) ?: (string) $tenant->getKey();

        return "{$base}/{$tenantSegment}/".(Str::slug($slug) ?: 'artikel');
    }

    /**
     * Legt eine Datei ab und liefert ihren Pfad relativ zur Platte.
     */
    public function put(Tenant $tenant, string $slug, string $filename, string $contents): string
    {
        $path = $this->directory($tenant, $slug).'/'.$filename;

        $this->disk()->put($path, $contents);

        return $path;
    }

    /**
     * Oeffentliche URL einer abgelegten Datei.
     */
    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function size(string $path): int
    {
        return $this->exists($path) ? (int) $this->disk()->size($path) : 0;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('content.assets.disk', 'public'));
    }
}
