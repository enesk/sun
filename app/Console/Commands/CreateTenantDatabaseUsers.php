<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erstellt MySQL-User für bestehende Tenants, die noch mit der
 * zentralen root-Connection arbeiten.
 *
 * Nutzung: php artisan tenants:create-db-users
 * Dry-Run:  php artisan tenants:create-db-users --dry-run
 */
class CreateTenantDatabaseUsers extends Command
{
    protected $signature = 'tenants:create-db-users
        {--dry-run : Zeigt was passieren würde, ohne Änderungen}
        {--tenant= : Nur einen bestimmten Tenant migrieren (UUID)}';

    protected $description = 'Erstellt dedizierte MySQL-User für bestehende Tenants (DB-User-Isolation)';

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
            '%s %d Tenant(s)...',
            $dryRun ? '[DRY-RUN] Prüfe' : 'Verarbeite',
            $tenants->count()
        ));

        $centralConnection = config('tenancy.database.central_connection');
        $grants = implode(', ', \App\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::$grants);

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            $dbName = $tenant->database()->getName();
            $existingUsername = $tenant->getInternal('db_username');

            // Prüfen ob DB existiert
            $dbExists = DB::connection($centralConnection)
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);

            if (empty($dbExists)) {
                $this->warn("  [{$tenant->name}] DB '{$dbName}' existiert nicht — überspringe.");
                $skipped++;
                continue;
            }

            if ($existingUsername) {
                // Prüfen ob der User auch tatsächlich in MySQL existiert
                $userExists = DB::connection($centralConnection)
                    ->select("SELECT 1 FROM mysql.user WHERE user = ? AND host = 'localhost'", [$existingUsername]);

                if (! empty($userExists)) {
                    $this->line("  [{$tenant->name}] User '{$existingUsername}' existiert bereits — überspringe.");
                    $skipped++;
                    continue;
                }

                $this->warn("  [{$tenant->name}] Credentials gespeichert aber MySQL-User fehlt — erstelle neu.");
            }

            // Username: tenant_ + erste 8 Zeichen der UUID (eindeutig, lesbar)
            $username = 'tn_' . str_replace('-', '', substr($tenant->uuid, 0, 8));
            $password = Str::random(32);

            $this->line(sprintf(
                '  [%s] DB: %s | User: %s@localhost',
                $tenant->name,
                $dbName,
                $username
            ));

            if ($dryRun) {
                $this->info("    → [DRY-RUN] Würde User erstellen und Grants vergeben.");
                $created++;
                continue;
            }

            try {
                $db = DB::connection($centralConnection);

                // User erstellen
                $db->statement("CREATE USER IF NOT EXISTS `{$username}`@`localhost` IDENTIFIED BY '{$password}'");

                // Grants vergeben — NUR auf die Tenant-DB
                $db->statement("GRANT {$grants} ON `{$dbName}`.* TO `{$username}`@`localhost`");

                $db->statement('FLUSH PRIVILEGES');

                // Credentials im Tenant speichern
                $tenant->setInternal('db_username', $username);
                $tenant->setInternal('db_password', $password);
                $tenant->save();

                $this->info("    → User erstellt, Grants vergeben, Credentials gespeichert.");
                $created++;
            } catch (\Exception $e) {
                $this->error("    → Fehler: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Fertig: {$created} erstellt, {$skipped} übersprungen, {$errors} Fehler.");

        if (! $dryRun && $created > 0) {
            $this->newLine();
            $this->warn('WICHTIG: In config/tenancy.php muss der PermissionControlledMySQLDatabaseManager aktiviert sein,');
            $this->warn('damit neue Tenants automatisch eigene DB-User bekommen.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
