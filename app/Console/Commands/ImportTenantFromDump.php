<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessTenantImportJob;
use App\Models\Tenant;
use App\Services\TenantImport\SqlDumpProcessor;
use App\Services\TenantImport\TenantImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportTenantFromDump extends Command
{
    protected $signature = 'tenant:import-dump
        {file : Pfad zur SQL-Dump-Datei (.sql oder .sql.gz)}
        {--tenant= : Ziel-Tenant ID oder Domain}
        {--source-tenant-id=0 : Source-Tenant-ID im Dump (0 = alle)}
        {--skip-reviews : Reviews nicht importieren}
        {--skip-photos : Fotos nicht importieren}
        {--force : Bestehende Einträge überschreiben}
        {--no-preview : Vorschau überspringen und direkt importieren}
        {--queue : Import als Queue-Job ausführen statt synchron}
        {--create-tenant : Tenant automatisch erstellen falls er nicht existiert}';

    protected $description = 'Importiert Tenant-Daten aus einem SQL-Dump (CLI-Wrapper für TenantImportService)';

    public function handle(SqlDumpProcessor $processor, TenantImportService $importService): int
    {
        $startTime = microtime(true);
        $filePath = (string) $this->argument('file');

        $this->printBanner();

        // ── Datei prüfen ──
        $this->step('Datei prüfen');
        if (! file_exists($filePath)) {
            $this->error("Datei nicht gefunden: {$filePath}");
            return self::FAILURE;
        }

        $fileSize = $this->humanFileSize(filesize($filePath));
        $this->detail("Pfad: {$filePath}");
        $this->detail("Größe: {$fileSize}");
        $this->detail('Typ: ' . (str_ends_with($filePath, '.gz') ? 'gzip-komprimiert' : 'unkomprimiert'));
        $this->ok("Datei gefunden ({$fileSize})");

        // ── Tenant ermitteln ──
        $this->step('Ziel-Tenant ermitteln');
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }

        $this->detail("Tenant-Name: {$tenant->name}");
        $this->detail("Tenant-ID: {$tenant->id}");
        $this->ok("Ziel-Tenant: {$tenant->name}");

        // ── Validierung ──
        $this->step('Schritt 1/4: SQL-Dump validieren');
        $this->detail('Prüfe Dump-Struktur auf erwartete Tabellen...');
        $this->detail('Prüfe auf gefährliche SQL-Statements (DROP, GRANT, DELETE)...');

        $validation = $processor->validate($filePath);

        if (! $validation->valid) {
            $this->error('Validierung fehlgeschlagen:');
            foreach ($validation->errors as $error) {
                $this->error("   {$error}");
            }
            return self::FAILURE;
        }

        foreach ($validation->foundTables as $table) {
            $this->detail("Tabelle gefunden: {$table}");
        }
        $this->ok('Gefundene Tabellen: ' . implode(', ', $validation->foundTables));

        if (! empty($validation->warnings)) {
            foreach ($validation->warnings as $warning) {
                $this->warn("   {$warning}");
            }
        }

        $this->ok('Sicherheitsprüfung bestanden — keine gefährlichen Statements');

        // ── Dump verarbeiten (Temp-DB erstellen) ──
        $this->step('Schritt 2/4: SQL-Dump in Temp-DB importieren');
        $this->detail('Erstelle temporäre Datenbank...');

        $tempDbInfo = $processor->process($filePath);

        $this->detail("DB-Name: {$tempDbInfo->databaseName}");
        $this->detail("Connection: {$tempDbInfo->connectionName}");
        $this->ok("Temp-DB erstellt: {$tempDbInfo->databaseName}");

        // ── Source-Tenant-ID ermitteln ──
        $this->detail('Lese verfügbare Tenant-IDs aus Dump...');
        $sourceTenantId = (int) $this->option('source-tenant-id');
        $availableIds = $processor->getAvailableTenantIds();

        if (! empty($availableIds)) {
            $this->detail('Verfügbare Tenant-IDs: ' . implode(', ', $availableIds));

            if ($sourceTenantId === 0 && count($availableIds) > 1 && ! $this->option('no-preview')) {
                $sourceTenantId = (int) $this->choice(
                    'Welche Source-Tenant-ID importieren?',
                    array_merge(['0 (alle)'], array_map('strval', $availableIds)),
                    '0 (alle)',
                );
                $sourceTenantId = (int) preg_replace('/\D/', '', (string) $sourceTenantId);
            }
        }

        $this->ok("Source-Tenant-ID: {$sourceTenantId}" . ($sourceTenantId === 0 ? ' (alle)' : ''));

        // ── Vorschau / Dry-Run ──
        if (! $this->option('no-preview')) {
            $this->step('Schritt 3/4: Vorschau (Dry-Run)');
            $this->detail('Initialisiere Tenancy...');

            tenancy()->initialize($tenant);
            SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);

            $this->detail('Zähle importierbare Datensätze...');
            $this->detail('Prüfe Duplikate gegen bestehende Daten...');
            $this->detail('Analysiere Kategorie-Mappings...');

            $preview = $importService->dryRun($tenant, $tempDbInfo->connectionName, $sourceTenantId);

            tenancy()->end();
            $this->detail('Tenancy beendet');

            $this->table(
                ['Metrik', 'Anzahl'],
                [
                    ['Firmen (importierbar)', $preview->placesCount],
                    ['Kategorien', $preview->categoriesCount],
                    ['Bewertungen', $preview->reviewsCount],
                    ['Fotos', $preview->photosCount],
                    ['Öffnungszeiten', $preview->openingHoursCount],
                    ['Duplikate (bereits vorhanden)', $preview->duplicatesCount],
                    ['Erwartete neue Einträge', $preview->expectedNewCompanies],
                ],
            );

            if (! empty($preview->missingCategories)) {
                $this->warn('Fehlende Kategorie-Mappings: ' . implode(', ', $preview->missingCategories));
            }

            $this->ok('Dry-Run abgeschlossen');

            if (! $this->confirm('Import starten?', true)) {
                $this->detail('Räume Temp-DB auf...');
                $processor->cleanup();
                $this->ok('Abgebrochen — Temp-DB aufgeräumt');
                return self::SUCCESS;
            }
        } else {
            $this->step('Schritt 3/4: Vorschau übersprungen (--no-preview)');
            $this->detail('--no-preview Flag gesetzt, springe direkt zum Import');
        }

        // ── Import ausführen ──
        $this->step('Schritt 4/4: Import durchführen');

        $options = [
            'force' => (bool) $this->option('force'),
            'skip_reviews' => (bool) $this->option('skip-reviews'),
            'skip_photos' => (bool) $this->option('skip-photos'),
        ];

        $this->detail('Optionen:');
        $this->detail('  Force-Modus: ' . ($options['force'] ? 'JA — Duplikate werden überschrieben' : 'NEIN — Duplikate werden übersprungen'));
        $this->detail('  Reviews: ' . ($options['skip_reviews'] ? 'ÜBERSPRUNGEN' : 'werden importiert'));
        $this->detail('  Fotos: ' . ($options['skip_photos'] ? 'ÜBERSPRUNGEN' : 'werden importiert'));

        if ($this->option('queue')) {
            $this->detail('Queue-Modus aktiv — dispatche Job...');

            // Alten Log-Stream leeren, damit der Watch-Command sauber startet
            $logKey = ProcessTenantImportJob::getLogKey($tenant->id);
            Cache::forget($logKey);
            Cache::forget("tenant_import_progress_{$tenant->id}");

            ProcessTenantImportJob::dispatch(
                $tenant,
                $filePath,
                $sourceTenantId,
                $options,
            );

            $this->ok('Import-Job in Queue eingereiht');
            $this->detail('Temp-DB wird NICHT aufgeräumt — der Job braucht sie noch');
            $this->newLine();
            $this->info('Wechsle in Live-Watch-Modus...');
            $this->newLine();

            // Automatisch in Watch-Modus wechseln
            return $this->call('tenant:import-watch', [
                'tenant' => $tenant->id,
            ]);
        }

        // Synchroner Import mit Progress-Bar
        $this->detail('Initialisiere Tenancy für Import...');
        tenancy()->initialize($tenant);
        SqlDumpProcessor::ensureConnection($tempDbInfo->databaseName, $tempDbInfo->connectionName);
        $this->detail('Tenancy initialisiert — starte Datenimport...');

        $progressBar = null;
        $lastEntity = '';

        try {
            $result = $importService->import(
                $tenant,
                $tempDbInfo->connectionName,
                $sourceTenantId,
                $options,
                function (int $processed, int $total, int $percent, string $currentEntity) use (&$progressBar, &$lastEntity) {
                    if ($progressBar === null && $total > 0) {
                        $progressBar = $this->output->createProgressBar($total);
                        $progressBar->setFormat(" %current%/%max% [%bar%] %percent:3s%% | %message%\n");
                        $progressBar->setBarCharacter('█');
                        $progressBar->setEmptyBarCharacter('░');
                        $progressBar->setProgressCharacter('▓');
                        $progressBar->start();
                    }

                    if ($progressBar) {
                        // Zeige Entity-Wechsel als Live-Detail
                        if ($currentEntity !== $lastEntity) {
                            $lastEntity = $currentEntity;
                        }
                        $progressBar->setMessage("[{$this->timestamp()}] {$currentEntity}");
                        $progressBar->setProgress($processed);
                    }
                },
            );

            if ($progressBar) {
                $progressBar->finish();
                $this->newLine(2);
            }

            // Ergebnis anzeigen
            $elapsed = round(microtime(true) - $startTime, 1);
            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════');
            $this->info('  Import abgeschlossen');
            $this->info("  Dauer: {$elapsed}s");
            $this->info('═══════════════════════════════════════════════════════');
            $this->table(
                ['Metrik', 'Anzahl'],
                [
                    ['Firmen importiert', $result->companiesImported],
                    ['Firmen übersprungen', $result->companiesSkipped],
                    ['Firmen fehlgeschlagen', $result->companiesFailed],
                    ['Kategorien gemappt', $result->categoriesMapped],
                    ['Öffnungszeiten', $result->openingHoursImported],
                    ['Bewertungen', $result->reviewsImported],
                    ['Fotos', $result->photosImported],
                ],
            );

            if (! empty($result->errors)) {
                $this->warn('Fehler (' . count($result->errors) . '):');
                foreach (array_slice($result->errors, 0, 20) as $error) {
                    $this->warn("   {$error}");
                }
                if (count($result->errors) > 20) {
                    $this->warn('   ... und ' . (count($result->errors) - 20) . ' weitere.');
                }
            }
        } finally {
            $this->detail('Beende Tenancy...');
            tenancy()->end();
            $this->detail('Räume Temp-DB und Dump-Datei auf...');
            $processor->cleanup();
            $this->ok('Temp-DB und Dump-Datei aufgeräumt');
        }

        $totalElapsed = round(microtime(true) - $startTime, 1);
        $this->ok("Gesamtdauer: {$totalElapsed}s");

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $tenantOption = $this->option('tenant');

        if ($tenantOption) {
            $this->detail("Suche Tenant: {$tenantOption}");

            // Erst nach ID suchen, dann nach Domain
            $tenant = Tenant::find($tenantOption);
            if (! $tenant) {
                $this->detail('Nicht per ID gefunden, suche per Domain...');
                $tenant = Tenant::where('domain', $tenantOption)->first();
            }

            if (! $tenant && $this->option('create-tenant')) {
                $this->detail('Tenant nicht gefunden — erstelle neuen Tenant...');
                $tenant = $this->createTenantFromDomain($tenantOption);
            }

            if (! $tenant) {
                $this->error("Tenant nicht gefunden: {$tenantOption}");
                $this->error('Tipp: Verwende --create-tenant um den Tenant automatisch zu erstellen.');
                return null;
            }

            return $tenant;
        }

        // Interaktiv auswählen
        $this->detail('Kein --tenant angegeben, lade Tenant-Liste...');
        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        if ($tenants->isEmpty()) {
            $this->error('Keine Tenants vorhanden. Bitte zuerst einen Tenant anlegen oder --create-tenant verwenden.');
            return null;
        }

        $this->detail($tenants->count() . ' Tenants gefunden');

        $choices = $tenants->pluck('name', 'id')->toArray();
        $selectedId = $this->choice('Ziel-Tenant wählen:', $choices);

        return Tenant::find($selectedId);
    }

    private function createTenantFromDomain(string $domain): ?Tenant
    {
        // Tenant-Name aus Domain ableiten: "klempner-mueller.de" → "Klempner Mueller"
        $namePart = explode('.', $domain)[0]; // "klempner-mueller"
        $tenantName = str_replace(['-', '_'], ' ', $namePart);
        $tenantName = mb_convert_case($tenantName, MB_CASE_TITLE, 'UTF-8');

        $this->detail("Tenant-Name: {$tenantName}");
        $this->detail("Domain: {$domain}");
        $this->detail("UUID wird automatisch generiert...");

        try {
            // Tenant::create() feuert TenantCreated Event → CreateDatabase → MigrateDatabase → CreateTenantStorage
            $tenant = Tenant::create([
                'name' => $tenantName,
                'uuid' => (string) Str::uuid(),
                'domain' => $domain,
                'is_name_auto_generated' => false,
            ]);

            $this->detail("UUID: {$tenant->uuid}");
            $this->detail('Datenbank erstellt: tenant_' . $tenant->uuid);
            $this->detail('Migrationen ausgeführt');

            // Storage-Verzeichnisse erstellen
            $this->detail('Erstelle Tenant-Storage-Verzeichnisse...');
            $this->callSilently('tenants:storage', ['--tenants' => [$tenant->id]]);
            $this->detail('Storage-Verzeichnisse erstellt');

            $this->ok("Tenant erstellt: {$tenantName} (ID: {$tenant->id})");

            return $tenant;
        } catch (\Throwable $e) {
            $this->error("Tenant-Erstellung fehlgeschlagen: {$e->getMessage()}");
            return null;
        }
    }

    // ── Live-Output Hilfsmethoden ────────────────────────────────────

    private function printBanner(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Tenant-Datenimporter via SQL-Dump');
        $this->info('  ' . now()->format('d.m.Y H:i:s'));
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function step(string $message): void
    {
        $this->newLine();
        $this->line("<fg=cyan>━━━ {$message} ━━━</>");
    }

    private function detail(string $message): void
    {
        $this->line("  <fg=gray>[{$this->timestamp()}]</> {$message}");
    }

    private function ok(string $message): void
    {
        $this->line("  <fg=green>[OK]</> {$message}");
    }

    private function timestamp(): string
    {
        return now()->format('H:i:s');
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
