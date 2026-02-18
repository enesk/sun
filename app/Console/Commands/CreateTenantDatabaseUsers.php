<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erstellt MySQL-User für bestehende Tenants.
 *
 * Zwei Modi:
 *   1. Standard: Erstellt neue Credentials für Tenants ohne DB-User
 *   2. --sync:   Legt MySQL-User mit den BESTEHENDEN gespeicherten
 *                Credentials an (z.B. nach Deployment auf neuem Server)
 *
 * Nutzung:
 *   php artisan tenants:create-db-users              # Neue Credentials
 *   php artisan tenants:create-db-users --sync        # Bestehende Credentials auf DB anlegen
 *   php artisan tenants:create-db-users --dry-run     # Nur anzeigen
 *   php artisan tenants:create-db-users --force       # Bestehende User droppen + neu anlegen
 */
class CreateTenantDatabaseUsers extends Command
{
    protected $signature = 'tenants:create-db-users
        {--dry-run : Zeigt was passieren würde, ohne Änderungen}
        {--tenant= : Nur einen bestimmten Tenant (UUID)}
        {--sync : MySQL-User mit bestehenden gespeicherten Credentials anlegen (Deploy-Modus)}
        {--force : Bestehende MySQL-User droppen und neu anlegen}';

    protected $description = 'Erstellt dedizierte MySQL-User für Tenants (DB-User-Isolation)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantUuid = $this->option('tenant');
        $sync = $this->option('sync');
        $force = $this->option('force');

        $query = Tenant::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');
            return self::SUCCESS;
        }

        $mode = $sync ? 'SYNC' : 'CREATE';
        $this->info(sprintf(
            '%s[%s] %d Tenant(s)...',
            $dryRun ? '[DRY-RUN] ' : '',
            $mode,
            $tenants->count()
        ));

        if ($sync) {
            $this->comment('  Sync-Modus: Nutze bestehende gespeicherte Credentials.');
        }

        $centralConnection = config('tenancy.database.central_connection');
        $grants = implode(', ', \App\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::$grants);

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            $dbName = $tenant->database()->getName();
            $existingUsername = $tenant->getInternal('db_username');
            $existingPassword = $tenant->getInternal('db_password');

            // Prüfen ob DB existiert
            $dbExists = DB::connection($centralConnection)
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);

            if (empty($dbExists)) {
                $this->warn("  [{$tenant->name}] DB '{$dbName}' existiert nicht — überspringe.");
                $skipped++;
                continue;
            }

            // Prüfen ob MySQL-User bereits existiert
            $mysqlUserExists = false;
            if ($existingUsername) {
                $userCheck = DB::connection($centralConnection)
                    ->select("SELECT 1 FROM mysql.user WHERE user = ? AND host = 'localhost'", [$existingUsername]);
                $mysqlUserExists = ! empty($userCheck);
            }

            // ─── SYNC-Modus: Bestehende Credentials verwenden ───
            if ($sync) {
                if (! $existingUsername || ! $existingPassword) {
                    $this->warn("  [{$tenant->name}] Keine gespeicherten Credentials — überspringe. Nutze den Modus ohne --sync.");
                    $skipped++;
                    continue;
                }

                if ($mysqlUserExists && ! $force) {
                    $this->line("  [{$tenant->name}] User '{$existingUsername}' existiert bereits in MySQL — überspringe.");
                    $skipped++;
                    continue;
                }

                $username = $existingUsername;
                $password = $existingPassword;

                $this->line(sprintf(
                    '  [%s] DB: %s | User: %s@localhost (sync)',
                    $tenant->name,
                    $dbName,
                    $username
                ));

                if ($dryRun) {
                    $this->info("    → [DRY-RUN] Würde MySQL-User mit gespeicherten Credentials anlegen.");
                    $created++;
                    continue;
                }

                try {
                    $db = DB::connection($centralConnection);

                    if ($force && $mysqlUserExists) {
                        $db->statement("DROP USER IF EXISTS `{$username}`@`localhost`");
                        $this->comment("    → Bestehender User gedroppt.");
                    }

                    $db->statement("CREATE USER IF NOT EXISTS `{$username}`@`localhost` IDENTIFIED BY " . $db->getPdo()->quote($password));
                    $db->statement("GRANT {$grants} ON `{$dbName}`.* TO `{$username}`@`localhost`");
                    $db->statement('FLUSH PRIVILEGES');

                    $this->info("    → MySQL-User angelegt mit gespeicherten Credentials.");
                    $created++;
                } catch (\Exception $e) {
                    $this->error("    → Fehler: {$e->getMessage()}");
                    $errors++;
                }

                continue;
            }

            // ─── Standard-Modus: Neue Credentials generieren ───
            if ($mysqlUserExists && ! $force) {
                $this->line("  [{$tenant->name}] User '{$existingUsername}' existiert bereits — überspringe.");
                $skipped++;
                continue;
            }

            if ($existingUsername && ! $mysqlUserExists) {
                $this->warn("  [{$tenant->name}] Credentials gespeichert aber MySQL-User fehlt — erstelle neu.");
            }

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

                if ($force && $mysqlUserExists) {
                    $db->statement("DROP USER IF EXISTS `{$existingUsername}`@`localhost`");
                    $this->comment("    → Bestehender User gedroppt.");
                }

                $db->statement("CREATE USER IF NOT EXISTS `{$username}`@`localhost` IDENTIFIED BY " . $db->getPdo()->quote($password));
                $db->statement("GRANT {$grants} ON `{$dbName}`.* TO `{$username}`@`localhost`");
                $db->statement('FLUSH PRIVILEGES');

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
