<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Entfernt gespeicherte DB-Credentials aus Tenant-Records.
 *
 * Notwendig wenn TENANCY_DB_ISOLATION=false gesetzt wird (z.B. Shared-Hosting),
 * da stancl/tenancy die gespeicherten db_username/db_password IMMER für die
 * Tenant-Connection nutzt — unabhängig vom DatabaseManager.
 *
 * Nutzung:
 *   php artisan tenants:clear-db-credentials              # Credentials entfernen
 *   php artisan tenants:clear-db-credentials --dry-run     # Nur anzeigen
 *   php artisan tenants:clear-db-credentials --tenant=UUID # Nur einen Tenant
 */
class ClearTenantDatabaseCredentials extends Command
{
    protected $signature = 'tenants:clear-db-credentials
        {--dry-run : Zeigt was passieren würde, ohne Änderungen}
        {--tenant= : Nur einen bestimmten Tenant (UUID)}';

    protected $description = 'Entfernt gespeicherte DB-User-Credentials aus Tenant-Records (für Shared-Hosting)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantUuid = $this->option('tenant');

        $query = Tenant::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d Tenant(s) prüfen...',
            $dryRun ? '[DRY-RUN] ' : '',
            $tenants->count()
        ));

        $cleared = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $username = $tenant->getInternal('db_username');
            $password = $tenant->getInternal('db_password');

            if (! $username && ! $password) {
                $this->line("  [{$tenant->name}] Keine DB-Credentials gespeichert — überspringe.");
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '  [%s] db_username=%s, db_password=%s',
                $tenant->name,
                $username ?? '(null)',
                $password ? str_repeat('*', 8) : '(null)'
            ));

            if ($dryRun) {
                $this->info("    → [DRY-RUN] Würde db_username und db_password entfernen.");
                $cleared++;
                continue;
            }

            // Credentials aus der data-JSON-Spalte entfernen
            $data = $tenant->getAttribute('data') ?? [];
            unset($data['db_username'], $data['db_password']);
            $tenant->setAttribute('data', $data);
            $tenant->save();

            $this->info("    → db_username und db_password entfernt.");
            $cleared++;
        }

        $this->newLine();
        $this->info("Fertig: {$cleared} bereinigt, {$skipped} übersprungen.");

        if (! $dryRun && $cleared > 0) {
            $this->newLine();
            $this->comment('Die Tenant-Connections nutzen jetzt den zentralen DB-User aus der .env.');
            $this->comment('Vergiss nicht: php artisan config:clear');
        }

        return self::SUCCESS;
    }
}
