<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Legacy\LegacyPipelineBackup;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sicherung der Tabellen der alten Content-Pipeline vor dem Rueckbau (#23).
 *
 * Die Drop-Migrationen sichern selbst; dieser Befehl ist fuer den Lauf von
 * Hand vorab und fuer die Wiederherstellung nach einem Rollback:
 *
 *   php artisan guide:legacy:backup
 *   php artisan guide:legacy:backup --tenant=7
 *   php artisan migrate:rollback --step=1 && php artisan tenants:rollback --step=1
 *   php artisan guide:legacy:backup --restore=2026-09-22
 *   php artisan guide:legacy:backup --central --restore=2026-09-22
 *   php artisan guide:legacy:backup --restore=2026-09-23 --tables=<tabelle>,<tabelle>
 *
 * Ablage: storage/app/backups/legacy-content/<datum>/ (LegacyPipelineBackup).
 */
class GuideLegacyBackup extends Command
{
    protected $signature = 'guide:legacy:backup
        {--tenant= : Nur dieses Portal (ID, UUID oder Domain), sonst alle}
        {--central : Nur die Central-Tabellen, keine Portale}
        {--restore= : Sicherung dieses Tages (Y-m-d) in die leeren Tabellen zurueckspielen}
        {--tables= : Nur diese Tenant-Tabellen (kommagetrennt), sonst die der Drop-Migration #23}';

    protected $description = 'Sichert die Tabellen der alten Content-Pipeline als NDJSON (oder spielt sie zurueck)';

    public function handle(LegacyPipelineBackup $backup): int
    {
        $central = (bool) $this->option('central');
        $tenants = $central ? collect() : $this->tenants();

        if (! $central && $tenants->isEmpty()) {
            $this->error('Keine Portale gefunden.');

            return self::FAILURE;
        }

        $restore = $this->option('restore');
        $directory = LegacyPipelineBackup::directory(
            $restore !== null ? CarbonImmutable::parse((string) $restore)->toDateString() : null,
        );

        if ($restore !== null && ! is_dir($directory)) {
            $this->error("Keine Sicherung in {$directory}");

            return self::FAILURE;
        }

        $tables = $this->option('tables') !== null
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('tables')))))
            : null;

        $rows = [];
        $failed = 0;

        foreach ($tenants as $tenant) {
            $id = (int) $tenant->getKey();

            try {
                $counts = $tenant->run(fn (): array => $restore !== null
                    ? $backup->restoreTenant($id, $directory, $tables)
                    : $backup->backupTenant($id, $directory, $tables));
            } catch (Throwable $exception) {
                $failed++;
                $this->error("[{$tenant->name}] {$exception->getMessage()}");

                continue;
            }

            foreach ($counts as $table => $count) {
                $rows[] = [(string) $tenant->name, $table, $count];
            }
        }

        if ($central || ($this->option('tenant') === null && $tables === null)) {
            try {
                $counts = $restore !== null ? $backup->restoreCentral($directory) : $backup->backupCentral($directory);

                foreach ($counts as $table => $count) {
                    $rows[] = ['central', $table, $count];
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error("[central] {$exception->getMessage()}");
            }
        }

        $this->table(['Portal', 'Tabelle', 'Datensätze'], $rows);
        $this->info(($restore !== null ? 'Zurückgespielt aus ' : 'Sicherung liegt in ').$directory);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        $needle = $this->option('tenant');
        $query = Tenant::query()->orderBy('id');

        if ($needle !== null) {
            ctype_digit((string) $needle)
                ? $query->whereKey((int) $needle)
                : $query->where(fn ($inner) => $inner->where('uuid', $needle)->orWhere('domain', $needle));
        }

        /** @var Collection<int, Tenant> $tenants */
        $tenants = $query->get()->toBase();

        return $tenants;
    }
}
