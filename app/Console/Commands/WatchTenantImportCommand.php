<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessTenantImportJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WatchTenantImportCommand extends Command
{
    protected $signature = 'tenant:import-watch
        {tenant : Tenant-ID oder Domain}
        {--poll=1 : Poll-Intervall in Sekunden}
        {--timeout=600 : Maximale Wartezeit in Sekunden}';

    protected $description = 'Verfolgt den Fortschritt eines laufenden Queue-basierten Tenant-Imports live';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }

        $cacheKey = "tenant_import_progress_{$tenant->id}";
        $logKey = ProcessTenantImportJob::getLogKey($tenant->id);
        $pollInterval = max(1, (int) $this->option('poll'));
        $timeout = max(10, (int) $this->option('timeout'));

        $this->printBanner($tenant);

        $logCursor = 0;
        $startTime = time();
        $lastStatus = '';

        while (true) {
            // Timeout-Check
            if ((time() - $startTime) > $timeout) {
                $this->newLine();
                $this->error("Timeout nach {$timeout}s — Import läuft möglicherweise noch im Hintergrund.");
                $this->line("  Erneut prüfen: <fg=cyan>php artisan tenant:import-watch {$tenant->id}</>");
                return self::FAILURE;
            }

            // Log-Stream lesen und neue Einträge ausgeben
            $logs = Cache::get($logKey, []);
            $newLogs = array_slice($logs, $logCursor);
            $logCursor = count($logs);

            foreach ($newLogs as $entry) {
                $this->renderLogEntry($entry);
            }

            // Fortschritt lesen
            $progress = Cache::get($cacheKey);

            if ($progress) {
                $status = $progress['status'] ?? 'unknown';

                // Status-Wechsel anzeigen
                if ($status !== $lastStatus) {
                    $lastStatus = $status;
                }

                // Terminal — fertig oder fehlgeschlagen
                if ($status === 'completed') {
                    $this->newLine();
                    $this->info('═══════════════════════════════════════════════════════');
                    $this->info('  Import abgeschlossen');
                    $this->info('═══════════════════════════════════════════════════════');

                    if (! empty($progress['result'])) {
                        $this->renderResult($progress['result']);
                    }

                    return self::SUCCESS;
                }

                if ($status === 'failed') {
                    $this->newLine();
                    $this->error('═══════════════════════════════════════════════════════');
                    $this->error("  Import fehlgeschlagen: {$progress['message']}");
                    $this->error('═══════════════════════════════════════════════════════');
                    return self::FAILURE;
                }
            } else {
                // Noch kein Fortschritt — warten
                if ($logCursor === 0 && (time() - $startTime) > 5) {
                    $this->line("  <fg=gray>[" . now()->format('H:i:s') . "]</> Warte auf Import-Job... (Queue-Worker läuft?)");
                }
            }

            sleep($pollInterval);
        }
    }

    private function resolveTenant(): ?Tenant
    {
        $input = $this->argument('tenant');

        $tenant = Tenant::find($input);
        if (! $tenant) {
            $tenant = Tenant::whereHas('domains', fn ($q) => $q->where('domain', $input))->first();
        }

        if (! $tenant) {
            $this->error("Tenant nicht gefunden: {$input}");
            return null;
        }

        return $tenant;
    }

    private function printBanner(Tenant $tenant): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("  Import-Watch: {$tenant->name}");
        $this->info('  ' . now()->format('d.m.Y H:i:s'));
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();
        $this->line('  <fg=gray>Verfolge Queue-Job live. Abbrechen mit Ctrl+C.</>');
        $this->newLine();
    }

    private function renderLogEntry(array $entry): void
    {
        $time = $entry['time'] ?? now()->format('H:i:s');
        $level = $entry['level'] ?? 'detail';
        $message = $entry['message'] ?? '';

        match ($level) {
            'step' => $this->renderStep($message),
            'ok' => $this->line("  <fg=green>[OK]</> {$message}"),
            'warn' => $this->line("  <fg=yellow>[!]</> {$message}"),
            'error' => $this->line("  <fg=red>[✗]</> {$message}"),
            'preview' => $this->renderPreview($message),
            'result' => $this->renderResultFromLog($message),
            default => $this->line("  <fg=gray>[{$time}]</> {$message}"),
        };
    }

    private function renderStep(string $message): void
    {
        $this->newLine();
        $this->line("<fg=cyan>━━━ {$message} ━━━</>");
    }

    private function renderPreview(string $json): void
    {
        $data = json_decode($json, true);
        if (! $data) {
            return;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, $value];
        }
        $this->table(['Metrik', 'Anzahl'], $rows);
    }

    private function renderResultFromLog(string $json): void
    {
        $data = json_decode($json, true);
        if (! $data) {
            return;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, $value];
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Ergebnis');
        $this->info('═══════════════════════════════════════════════════════');
        $this->table(['Metrik', 'Wert'], $rows);
    }

    private function renderResult(array $result): void
    {
        $rows = [];
        $labelMap = [
            'companies_imported' => 'Firmen importiert',
            'companies_skipped' => 'Firmen übersprungen',
            'companies_failed' => 'Firmen fehlgeschlagen',
            'categories_mapped' => 'Kategorien gemappt',
            'opening_hours_imported' => 'Öffnungszeiten',
            'reviews_imported' => 'Bewertungen',
            'photos_imported' => 'Fotos',
        ];

        foreach ($labelMap as $key => $label) {
            if (isset($result[$key])) {
                $rows[] = [$label, $result[$key]];
            }
        }

        if (! empty($rows)) {
            $this->table(['Metrik', 'Anzahl'], $rows);
        }

        if (! empty($result['errors'])) {
            $this->warn('Fehler (' . count($result['errors']) . '):');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->warn("   {$error}");
            }
        }
    }
}
