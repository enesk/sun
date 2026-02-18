<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Erstellt Symlinks für Tenant-Storage-Verzeichnisse.
 *
 * Jeder Tenant braucht einen Symlink:
 *   public/storage-{uuid} → storage/tenant{uuid}/app/public
 *
 * Nutzung:
 *   php artisan tenants:storage-link              # Alle Tenants
 *   php artisan tenants:storage-link --tenant=UUID # Nur einen Tenant
 *   php artisan tenants:storage-link --dry-run     # Nur anzeigen
 *   php artisan tenants:storage-link --relative    # Relative Symlinks (empfohlen für Linux)
 */
class LinkTenantStorage extends Command
{
    protected $signature = 'tenants:storage-link
        {--dry-run : Zeigt was passieren würde, ohne Änderungen}
        {--tenant= : Nur einen bestimmten Tenant (UUID)}
        {--relative : Relative statt absolute Symlinks erstellen}';

    protected $description = 'Erstellt Storage-Symlinks für Tenant public-Verzeichnisse';

    public function handle(): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');

            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $useRelative = $this->option('relative');
        $basePath = base_path();
        $publicPath = public_path();
        $storagePath = storage_path();

        $this->info(($isDryRun ? '[DRY-RUN] ' : '') . $tenants->count() . ' Tenant(s) verarbeiten...');
        $this->newLine();

        $created = 0;
        $existed = 0;
        $errors = 0;
        $dirCreated = 0;

        foreach ($tenants as $tenant) {
            $tenantKey = $tenant->getTenantKey();
            $suffixBase = config('tenancy.filesystem.suffix_base', 'tenant');

            // Symlink: public/storage-{uuid} → storage/tenant{uuid}/app/public
            $linkPath = $publicPath . '/storage-' . $tenantKey;
            $targetPath = $storagePath . '/' . $suffixBase . $tenantKey . '/app/public';

            $this->line("  <comment>[{$tenant->name}]</comment> {$tenantKey}");

            // 1. Prüfen ob Target-Verzeichnis existiert — wenn nicht, anlegen
            if (! is_dir($targetPath)) {
                if ($isDryRun) {
                    $this->line("    → Verzeichnis würde erstellt: <info>{$this->relativePath($targetPath, $basePath)}</info>");
                    $dirCreated++;
                } else {
                    if (mkdir($targetPath, 0755, true)) {
                        $this->line("    → Verzeichnis erstellt: <info>{$this->relativePath($targetPath, $basePath)}</info>");
                        $dirCreated++;
                    } else {
                        $this->error("    → Fehler: Verzeichnis konnte nicht erstellt werden: {$targetPath}");
                        $errors++;

                        continue;
                    }
                }
            }

            // 2. Prüfen ob Symlink bereits existiert
            if (is_link($linkPath)) {
                $currentTarget = readlink($linkPath);
                $resolvedTarget = $useRelative
                    ? $this->getRelativeTarget($linkPath, $targetPath)
                    : $targetPath;

                if ($currentTarget === $resolvedTarget || realpath($currentTarget) === realpath($targetPath)) {
                    $this->line("    → Symlink existiert bereits ✓");
                    $existed++;

                    continue;
                }

                // Symlink existiert aber zeigt woanders hin
                if ($isDryRun) {
                    $this->warn("    → Symlink existiert, zeigt aber auf: {$currentTarget}");
                    $this->line("    → Würde aktualisiert auf: {$this->relativePath($targetPath, $basePath)}");
                } else {
                    unlink($linkPath);
                    $this->warn("    → Alter Symlink entfernt (zeigte auf: {$currentTarget})");
                }
            } elseif (file_exists($linkPath)) {
                // Kein Symlink, aber Datei/Verzeichnis existiert
                $this->error("    → Fehler: {$this->relativePath($linkPath, $basePath)} existiert und ist kein Symlink!");
                $errors++;

                continue;
            }

            // 3. Symlink erstellen
            if ($isDryRun) {
                $this->line("    → Symlink würde erstellt:");
                $this->line("      <info>{$this->relativePath($linkPath, $basePath)}</info>");
                $this->line("      → <info>{$this->relativePath($targetPath, $basePath)}</info>");
                $created++;
            } else {
                $symlinkTarget = $useRelative
                    ? $this->getRelativeTarget($linkPath, $targetPath)
                    : $targetPath;

                if (symlink($symlinkTarget, $linkPath)) {
                    $this->line("    → Symlink erstellt ✓");
                    $this->line("      <info>{$this->relativePath($linkPath, $basePath)}</info>");
                    $this->line("      → <info>{$this->relativePath($targetPath, $basePath)}</info>");
                    $created++;
                } else {
                    $this->error("    → Fehler: Symlink konnte nicht erstellt werden!");
                    $errors++;
                }
            }

            $this->newLine();
        }

        // Auch den zentralen Storage-Link prüfen
        $this->checkCentralStorageLink($isDryRun, $useRelative);

        // Zusammenfassung
        $this->newLine();
        $this->info('─── Zusammenfassung ───');
        $this->line("  Verzeichnisse erstellt: {$dirCreated}");
        $this->line("  Symlinks erstellt:      {$created}");
        $this->line("  Bereits vorhanden:      {$existed}");

        if ($errors > 0) {
            $this->error("  Fehler: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function getTenants()
    {
        $tenantUuid = $this->option('tenant');

        if ($tenantUuid) {
            $tenant = Tenant::where('uuid', $tenantUuid)->first();

            if (! $tenant) {
                $this->error("Tenant mit UUID '{$tenantUuid}' nicht gefunden.");

                return collect();
            }

            return collect([$tenant]);
        }

        return Tenant::all();
    }

    private function checkCentralStorageLink(bool $isDryRun, bool $useRelative): void
    {
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        $this->newLine();
        $this->line('  <comment>[Zentral]</comment> public/storage → storage/app/public');

        if (is_link($linkPath)) {
            $this->line("    → Zentraler Symlink existiert ✓");
        } elseif (! file_exists($linkPath)) {
            if ($isDryRun) {
                $this->line("    → Zentraler Symlink würde erstellt");
            } else {
                $symlinkTarget = $useRelative
                    ? $this->getRelativeTarget($linkPath, $targetPath)
                    : $targetPath;

                if (symlink($symlinkTarget, $linkPath)) {
                    $this->line("    → Zentraler Symlink erstellt ✓");
                } else {
                    $this->warn("    → Zentraler Symlink konnte nicht erstellt werden — ggf. php artisan storage:link nutzen");
                }
            }
        }
    }

    /**
     * Berechnet den relativen Pfad vom Symlink-Verzeichnis zum Ziel.
     */
    private function getRelativeTarget(string $linkPath, string $targetPath): string
    {
        $linkDir = dirname($linkPath);

        // Beide Pfade in Teile zerlegen
        $linkParts = explode('/', rtrim($linkDir, '/'));
        $targetParts = explode('/', rtrim($targetPath, '/'));

        // Gemeinsamen Prefix finden
        $common = 0;
        $max = min(count($linkParts), count($targetParts));
        for ($i = 0; $i < $max; $i++) {
            if ($linkParts[$i] !== $targetParts[$i]) {
                break;
            }
            $common++;
        }

        // Relative Pfad-Teile berechnen
        $upCount = count($linkParts) - $common;
        $relativeParts = array_merge(
            array_fill(0, $upCount, '..'),
            array_slice($targetParts, $common)
        );

        return implode('/', $relativeParts);
    }

    private function relativePath(string $path, string $basePath): string
    {
        return str_replace($basePath . '/', '', $path);
    }
}
