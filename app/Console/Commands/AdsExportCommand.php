<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AdSlotExportService;
use Illuminate\Console\Command;

class AdsExportCommand extends Command
{
    protected $signature = 'ads:export {tenant : Tenant-UUID, ID oder Domain} {--output= : Dateipfad für Export-JSON}';

    protected $description = 'Exportiert Ad-Slots eines Tenants als JSON-Datei';

    public function handle(AdSlotExportService $exportService): int
    {
        $tenantIdentifier = $this->argument('tenant');

        $tenant = Tenant::where('uuid', $tenantIdentifier)
            ->orWhere('id', $tenantIdentifier)
            ->orWhere('domain', $tenantIdentifier)
            ->first();

        if (! $tenant) {
            $this->error("Tenant \"{$tenantIdentifier}\" nicht gefunden.");

            return self::FAILURE;
        }

        $exportData = $exportService->export($tenant);
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $outputPath = $this->option('output')
            ?? storage_path('app/ad-slots-export-' . $tenant->uuid . '-' . now()->format('Y-m-d') . '.json');

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($outputPath, $json);

        $this->info("{$exportData['meta']['slot_count']} Ad-Slots exportiert nach {$outputPath}");

        return self::SUCCESS;
    }
}
