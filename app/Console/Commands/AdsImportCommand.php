<?php

namespace App\Console\Commands;

use App\Jobs\BulkAdSlotImportJob;
use App\Models\Tenant;
use App\Services\AdSlotImportService;
use Illuminate\Console\Command;

class AdsImportCommand extends Command
{
    protected $signature = 'ads:import
        {file : Pfad zur JSON-Datei}
        {tenant? : Ziel-Tenant (UUID, ID oder Domain)}
        {--all-tenants : Import in alle Tenants}
        {--mode=add : Import-Modus: add oder replace}
        {--conflict=skip : Konfliktstrategie: skip oder update}
        {--force : Bestätigung bei replace überspringen}';

    protected $description = 'Importiert Ad-Slots aus einer JSON-Datei in einen oder mehrere Tenants';

    public function handle(AdSlotImportService $importService): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("Datei nicht gefunden: {$filePath}");

            return self::FAILURE;
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Ungültige JSON-Datei: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $validation = $importService->validate($data);

        if (! $validation->valid) {
            $this->error('Validierung fehlgeschlagen:');
            foreach ($validation->errors as $error) {
                $this->line("  • {$error}");
            }

            return self::FAILURE;
        }

        $mode = $this->option('mode');
        $conflictStrategy = $this->option('conflict');

        if (! in_array($mode, ['add', 'replace'], true)) {
            $this->error("Ungültiger Modus \"{$mode}\". Erlaubt: add, replace");

            return self::FAILURE;
        }

        if (! in_array($conflictStrategy, ['skip', 'update'], true)) {
            $this->error("Ungültige Konfliktstrategie \"{$conflictStrategy}\". Erlaubt: skip, update");

            return self::FAILURE;
        }

        if ($this->option('all-tenants')) {
            return $this->importAllTenants($data, $mode, $conflictStrategy);
        }

        $tenantIdentifier = $this->argument('tenant');

        if (! $tenantIdentifier) {
            $this->error('Bitte einen Tenant angeben oder --all-tenants verwenden.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($tenantIdentifier);

        if (! $tenant) {
            $this->error("Tenant \"{$tenantIdentifier}\" nicht gefunden.");

            return self::FAILURE;
        }

        return $this->importSingleTenant($importService, $data, $tenant, $mode, $conflictStrategy);
    }

    private function importSingleTenant(
        AdSlotImportService $importService,
        array $data,
        Tenant $tenant,
        string $mode,
        string $conflictStrategy,
    ): int {
        if ($mode === 'replace' && ! $this->option('force')) {
            if (! $this->confirm("ACHTUNG: Modus \"replace\" löscht ALLE bestehenden Ad-Slots von \"{$tenant->name}\". Fortfahren?")) {
                $this->info('Abgebrochen.');

                return self::SUCCESS;
            }
        }

        try {
            $result = $importService->import($data, $tenant, $mode, $conflictStrategy);
        } catch (\Throwable $e) {
            $this->error("Import fehlgeschlagen: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Import für \"{$tenant->name}\" abgeschlossen:");
        $this->table(
            ['Importiert', 'Übersprungen', 'Aktualisiert'],
            [[$result->imported, $result->skipped, $result->updated]],
        );

        return self::SUCCESS;
    }

    private function importAllTenants(array $data, string $mode, string $conflictStrategy): int
    {
        if ($mode === 'replace' && ! $this->option('force')) {
            if (! $this->confirm('ACHTUNG: Modus "replace" löscht ALLE bestehenden Ad-Slots in ALLEN Tenants. Fortfahren?')) {
                $this->info('Abgebrochen.');

                return self::SUCCESS;
            }
        }

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            BulkAdSlotImportJob::dispatch($data, $tenant, $mode, $conflictStrategy);
        }

        $this->info("{$tenants->count()} Queue-Jobs dispatcht. Import läuft asynchron.");

        return self::SUCCESS;
    }

    private function resolveTenant(string $identifier): ?Tenant
    {
        return Tenant::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->orWhere('domain', $identifier)
            ->first();
    }
}
