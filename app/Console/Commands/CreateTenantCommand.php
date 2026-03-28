<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantImport\SqlDumpProcessor;
use App\Services\TenantImport\TenantImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Erstellt einen neuen Tenant mit DB, Storage und optionaler Domain.
 *
 * Die Pipeline in TenancyServiceProvider erledigt automatisch:
 *   1. CreateDatabase — Tenant-DB anlegen
 *   2. MigrateDatabase — Tenant-Migrationen ausführen
 *   3. CreateTenantStorage — Storage-Verzeichnisse + Symlink
 *
 * Der PermissionControlledMySQLDatabaseManager erstellt den isolierten MySQL-User.
 *
 * Usage:
 *   php artisan tenant:create "Mein Portal"
 *   php artisan tenant:create "Mein Portal" --domain=mein-portal.de
 *   php artisan tenant:create "Mein Portal" --domain=mein-portal.de --owner=admin@example.com
 *   php artisan tenant:create "Mein Portal" --dry-run
 */
class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {name : Name des Tenants}
        {--domain= : Domain für den Tenant (z.B. mein-portal.de)}
        {--owner= : E-Mail des Tenant-Owners (wird als User verknüpft)}
        {--uuid= : Eigene UUID verwenden (Default: automatisch generiert)}
        {--dry-run : Zeigt was passieren würde, ohne Änderungen}
        {--force : Überspringt Bestätigungsabfrage}';

    protected $description = 'Erstellt einen neuen Tenant mit Datenbank, Storage und optionaler Domain';

    public function handle(): int
    {
        $name = $this->argument('name');
        $domain = $this->option('domain');
        $ownerEmail = $this->option('owner');
        $uuid = $this->option('uuid') ?? (string) Str::uuid();
        $dryRun = $this->option('dry-run');
        $dumpPath = null;
        $tenantInfo = null;

        // ─── Validierung ───────────────────────────────────────────
        if ($domain && Tenant::where('domain', $domain)->exists()) {
            $this->error("Domain '{$domain}' ist bereits einem Tenant zugewiesen.");
            return self::FAILURE;
        }

        if (Tenant::where('name', $name)->exists()) {
            $this->warn("Ein Tenant mit dem Namen '{$name}' existiert bereits.");
            if (! $this->option('force') && ! $this->confirm('Trotzdem fortfahren?')) {
                return self::SUCCESS;
            }
        }

        $owner = null;
        if ($ownerEmail) {
            $owner = User::where('email', $ownerEmail)->first();
            if (! $owner) {
                $this->error("User mit E-Mail '{$ownerEmail}' nicht gefunden.");
                return self::FAILURE;
            }
        }

        // ─── Übersicht ─────────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Neuer Tenant');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $this->table(
            ['Eigenschaft', 'Wert'],
            [
                ['Name', $name],
                ['UUID', $uuid],
                ['Domain', $domain ?: '(keine)'],
                ['Owner', $owner ? "{$owner->name} ({$owner->email})" : '(keiner)'],
                ['Datenbank', 'tenant_' . Str::of($uuid)->replace('-', '_')],
                ['Storage', 'storage/tenant' . $uuid],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('[DRY-RUN] Keine Änderungen durchgeführt.');
            $this->info('Folgende Aktionen würden ausgeführt:');
            $this->line('  1. Tenant in zentraler DB anlegen');
            $this->line('  2. Tenant-Datenbank erstellen');
            $this->line('  3. Tenant-Migrationen ausführen');
            $this->line('  4. link-db-to-dbuser ausführen');
            $this->line('  5. Storage-Verzeichnisse + Symlink erstellen');
            $this->line('  6. MySQL-User mit isolierten Rechten anlegen');
            if ($domain) {
                $this->line('  7. Domain zuweisen');
                $this->line('  8. add-domain-to-vhost ' . $domain . ' ausführen');
                $this->line('  9. Tenant-Info von widimedia.com abrufen');
                $this->line('  10. DB-Dump von widimedia.com herunterladen');
                $this->line('  11. Fotos vom Quellserver übertragen (rsync)');
                $this->line('  12. Berechtigungen setzen (chown)');
                $this->line('  13. Datenbank importieren (Companies, Kategorien, Öffnungszeiten, Reviews, Fotos)');

                // Im Dry-Run die Tenant-Info trotzdem abrufen und anzeigen
                try {
                    $this->newLine();
                    $this->output->write("  Tenant-Info abrufen ({$domain})... ");

                    $url = "https://widimedia.com/tenant-info/{$domain}?token=SecretX1FC";
                    $response = Http::timeout(15)->get($url);

                    if ($response->successful()) {
                        $this->info('✓');
                        $tenantInfo = $response->json();

                        $this->newLine();
                        $this->info('  ── Tenant-Info von widimedia.com ──');
                        $this->line('  ' . json_encode($tenantInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $this->newLine();
                    } else {
                        $this->warn("✗ HTTP {$response->status()}");
                    }
                    // Im Dry-Run: DB-Dump-Größe prüfen
                    if ($response->successful()) {
                        try {
                            $this->output->write("  DB-Dump prüfen ({$domain})... ");
                            $dumpUrl = "https://widimedia.com/tenant-dump/{$domain}?token=SecretX1FC";
                            $headResponse = Http::timeout(15)->head($dumpUrl);

                            if ($headResponse->successful()) {
                                $contentLength = $headResponse->header('Content-Length');
                                $dumpSize = $contentLength ? $this->formatBytes((int) $contentLength) : 'Größe unbekannt';
                                $this->info('✓');
                                $this->line("    → DB-Dump verfügbar ({$dumpSize})");
                            } else {
                                $this->warn("✗ HTTP {$headResponse->status()}");
                            }
                        } catch (\Exception $dumpEx) {
                            $this->warn("✗ DB-Dump nicht prüfbar: {$dumpEx->getMessage()}");
                        }
                    }

                    // Im Dry-Run: rsync --dry-run --stats ausführen um Dateianzahl zu zeigen
                    if ($response->successful() && ! empty($tenantInfo)) {
                        $remoteSlug = $tenantInfo['slug'] ?? Str::slug(str_replace('.de', '', $domain));
                        $remotePath = "root@widimedia.com:/var/www/vhosts/widimedia.com/httpdocs/storage/app/public/{$remoteSlug}/photos";

                        $this->output->write("  Fotos prüfen ({$remoteSlug})... ");

                        try {
                            $rsyncDryRun = Process::timeout(60)->run(
                                "rsync -avz --dry-run --stats {$remotePath} /tmp/dry-run-target/"
                            );

                            if ($rsyncDryRun->successful()) {
                                $this->info('✓');

                                $output = $rsyncDryRun->output();
                                $fileCount = 0;
                                $totalSize = '';

                                if (preg_match('/Number of regular files transferred:\s*([\d,]+)/', $output, $m)) {
                                    $fileCount = (int) str_replace(',', '', $m[1]);
                                } elseif (preg_match('/Number of files transferred:\s*([\d,]+)/', $output, $m)) {
                                    $fileCount = (int) str_replace(',', '', $m[1]);
                                }

                                if (preg_match('/Total transferred file size:\s*([\d,.]+\s*\w*)/', $output, $m)) {
                                    $totalSize = trim($m[1]);
                                }

                                $this->line("    → {$fileCount} Dateien zum Übertragen" . ($totalSize ? " ({$totalSize})" : ''));
                            } else {
                                $this->warn('✗');
                                $this->warn("    rsync-Vorschau fehlgeschlagen: {$rsyncDryRun->errorOutput()}");
                            }
                        } catch (\Exception $rsyncEx) {
                            $this->warn("✗ rsync-Vorschau nicht möglich: {$rsyncEx->getMessage()}");
                        }
                    }

                    // Im Dry-Run: DB-Dump herunterladen, Vorschau zeigen, wieder aufräumen
                    if ($response->successful()) {
                        try {
                            $this->output->write("  Import-Vorschau erstellen... ");

                            $dumpUrl = "https://widimedia.com/tenant-dump/{$domain}?token=SecretX1FC";
                            $dumpFileName = "tenant-dump-dryrun-" . Str::slug($domain) . "-" . now()->format('Y-m-d_His') . ".sql.gz";
                            $dumpPath = sys_get_temp_dir() . "/{$dumpFileName}";

                            $dumpResponse = Http::timeout(300)->withOptions(['sink' => $dumpPath])->get($dumpUrl);

                            if ($dumpResponse->successful() && file_exists($dumpPath)) {
                                $sqlDumpProcessor = app(SqlDumpProcessor::class);
                                $validation = $sqlDumpProcessor->validate($dumpPath);

                                if ($validation->valid) {
                                    $tempDbInfo = $sqlDumpProcessor->process($dumpPath);
                                    $tenantIds = $sqlDumpProcessor->getAvailableTenantIds();
                                    $sourceTenantId = count($tenantIds) === 1 ? $tenantIds[0] : 0;

                                    // Vorschau ohne Tenant-Context (Dry-Run hat keinen echten Tenant)
                                    $importService = app(TenantImportService::class);
                                    // Fake-Tenant für Dry-Run Vorschau
                                    $fakeTenant = new Tenant(['name' => $name, 'uuid' => $uuid]);
                                    $preview = $importService->dryRun($fakeTenant, $tempDbInfo->connectionName, $sourceTenantId);

                                    $this->info('✓');
                                    $this->newLine();
                                    $this->info('  ── Import-Vorschau ──');
                                    $this->table(
                                        ['Entität', 'Anzahl'],
                                        [
                                            ['Companies (importierbar)', $preview->placesCount],
                                            ['Duplikate', $preview->duplicatesCount],
                                            ['Neue Companies', $preview->expectedNewCompanies],
                                            ['Kategorien', $preview->categoriesCount],
                                            ['Öffnungszeiten', $preview->openingHoursCount],
                                            ['Bewertungen', $preview->reviewsCount],
                                            ['Fotos', $preview->photosCount],
                                        ],
                                    );

                                    if (count($tenantIds) > 1) {
                                        $this->line("    → Mehrere Tenant-IDs im Dump: " . implode(', ', $tenantIds));
                                    }

                                    if (! empty($preview->missingCategories)) {
                                        $this->warn("    → " . count($preview->missingCategories) . " nicht zuordenbare Kategorien (Fallback: Sonstiges)");
                                    }

                                    // Aufräumen
                                    $sqlDumpProcessor->cleanup();
                                } else {
                                    $this->warn('✗ Dump-Validierung fehlgeschlagen');
                                    foreach ($validation->errors as $error) {
                                        $this->error("    {$error}");
                                    }
                                }
                            } else {
                                $this->warn('✗ DB-Dump nicht herunterladbar');
                            }

                            // Temp-Datei immer aufräumen
                            if (file_exists($dumpPath)) {
                                @unlink($dumpPath);
                            }
                        } catch (\Exception $importEx) {
                            $this->warn("✗ Import-Vorschau nicht möglich: {$importEx->getMessage()}");
                            // Temp-Dateien aufräumen
                            if (isset($dumpPath) && file_exists($dumpPath)) {
                                @unlink($dumpPath);
                            }
                            if (isset($sqlDumpProcessor)) {
                                try { $sqlDumpProcessor->cleanup(); } catch (\Exception $cleanupEx) {}
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("✗ Tenant-Info nicht abrufbar: {$e->getMessage()}");
                }
            }
            $step = $domain ? 14 : 7;
            if ($owner) {
                $this->line("  {$step}. Owner verknüpfen");
            }
            return self::SUCCESS;
        }

        // ─── Bestätigung ───────────────────────────────────────────
        if (! $this->option('force') && ! $this->confirm('Tenant erstellen?', true)) {
            $this->info('Abgebrochen.');
            return self::SUCCESS;
        }

        // ─── Tenant erstellen ──────────────────────────────────────
        $this->newLine();

        try {
            $this->output->write('  Tenant anlegen... ');

            $tenantData = [
                'name' => $name,
                'uuid' => $uuid,
                'is_name_auto_generated' => false,
            ];

            if ($domain) {
                $tenantData['domain'] = $domain;
            }

            // Tenant::create() dispatcht Events\TenantCreated,
            // was die Pipeline auslöst: CreateDatabase → MigrateDatabase → CreateTenantStorage
            $tenant = Tenant::create($tenantData);

            $this->info('✓');

        } catch (\Exception $e) {
            $this->error('✗');
            $this->error("Fehler beim Erstellen: {$e->getMessage()}");
            return self::FAILURE;
        }

        // ─── link-db-to-dbuser ─────────────────────────────────────
        try {
            $this->output->write('  link-db-to-dbuser... ');

            $result = Process::run('link-db-to-dbuser');

            if ($result->successful()) {
                $this->info('✓');
            } else {
                $this->warn('✗');
                $this->warn("  link-db-to-dbuser fehlgeschlagen: {$result->errorOutput()}");
            }
        } catch (\Exception $e) {
            $this->warn("✗ link-db-to-dbuser nicht verfügbar: {$e->getMessage()}");
        }

        // ─── add-domain-to-vhost ───────────────────────────────────
        if ($domain) {
            try {
                $this->output->write("  add-domain-to-vhost {$domain}... ");

                $result = Process::run("add-domain-to-vhost {$domain}");

                if ($result->successful()) {
                    $this->info('✓');
                } else {
                    $this->warn('✗');
                    $this->warn("  add-domain-to-vhost fehlgeschlagen: {$result->errorOutput()}");
                }
            } catch (\Exception $e) {
                $this->warn("✗ add-domain-to-vhost nicht verfügbar: {$e->getMessage()}");
            }
        }

        // ─── Tenant-Info vom Quellserver holen ────────────────────
        if ($domain) {
            try {
                $this->output->write("  Tenant-Info abrufen ({$domain})... ");

                $url = "https://widimedia.com/tenant-info/{$domain}?token=SecretX1FC";
                $response = Http::timeout(15)->get($url);

                if ($response->successful()) {
                    $this->info('✓');
                    $tenantInfo = $response->json();

                    $this->newLine();
                    $this->info('  ── Tenant-Info von widimedia.com ──');
                    $this->line('  ' . json_encode($tenantInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->newLine();
                } else {
                    $this->warn("✗ HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->warn("✗ Tenant-Info nicht abrufbar: {$e->getMessage()}");
            }
        }

        // ─── DB-Dump vom Quellserver herunterladen ──────────────────
        if ($domain) {
            try {
                $this->output->write("  DB-Dump herunterladen ({$domain})... ");

                $dumpUrl = "https://widimedia.com/tenant-dump/{$domain}?token=SecretX1FC";
                $dumpFileName = "tenant-dump-" . Str::slug($domain) . "-" . now()->format('Y-m-d_His') . ".sql.gz";
                $dumpPath = "/home/sanitaerfinden/htdocs/{$dumpFileName}";

                $response = Http::timeout(300)->withOptions([
                    'sink' => $dumpPath,
                ])->get($dumpUrl);

                if ($response->successful()) {
                    $fileSize = file_exists($dumpPath) ? filesize($dumpPath) : 0;
                    $humanSize = $this->formatBytes($fileSize);
                    $this->info('✓');
                    $this->line("    → {$dumpFileName} ({$humanSize})");
                    $this->line("    → Pfad: {$dumpPath}");
                } else {
                    $this->warn("✗ HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->warn("✗ DB-Dump nicht abrufbar: {$e->getMessage()}");
            }
        }

        // ─── Fotos vom Quellserver übertragen ─────────────────────
        if ($domain && ! empty($tenantInfo)) {
            $remoteSlug = $tenantInfo['slug'] ?? Str::slug(str_replace('.de', '', $domain));
            $tenantStoragePath = "/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/tenant{$tenant->uuid}/app/public";
            $remotePath = "root@widimedia.com:/var/www/vhosts/widimedia.com/httpdocs/storage/app/public/{$remoteSlug}/photos";

            try {
                $this->output->write("  Fotos übertragen ({$remoteSlug})... ");

                $rsyncCommand = "rsync -avz --stats root@widimedia.com:/var/www/vhosts/widimedia.com/httpdocs/storage/app/public/{$remoteSlug}/photos {$tenantStoragePath}/";

                $result = Process::forever()->run($rsyncCommand);

                if ($result->successful()) {
                    $this->info('✓');

                    // rsync --stats Output parsen
                    $output = $result->output();
                    $filesTransferred = 0;
                    $totalSize = '';

                    if (preg_match('/Number of regular files transferred:\s*([\d,]+)/', $output, $m)) {
                        $filesTransferred = (int) str_replace(',', '', $m[1]);
                    } elseif (preg_match('/Number of files transferred:\s*([\d,]+)/', $output, $m)) {
                        $filesTransferred = (int) str_replace(',', '', $m[1]);
                    }

                    if (preg_match('/Total transferred file size:\s*([\d,.]+\s*\w*)/', $output, $m)) {
                        $totalSize = trim($m[1]);
                    }

                    $this->line("    → {$filesTransferred} Dateien übertragen" . ($totalSize ? " ({$totalSize})" : ''));

                    // chown setzen
                    $this->output->write('  Berechtigungen setzen... ');
                    $chownResult = Process::run('chown -R sanitaerfinden:sanitaerfinden /home/sanitaerfinden/htdocs/sanitaerfinden.dev');

                    if ($chownResult->successful()) {
                        $this->info('✓');
                    } else {
                        $this->warn('✗');
                        $this->warn("  chown fehlgeschlagen: {$chownResult->errorOutput()}");
                    }
                } else {
                    $this->warn('✗');
                    $this->warn("  rsync fehlgeschlagen: {$result->errorOutput()}");
                }
            } catch (\Exception $e) {
                $this->warn("✗ Foto-Transfer nicht möglich: {$e->getMessage()}");
            }
        }

        // ─── Datenbank importieren ───────────────────────────────────
        if ($domain && ! empty($dumpPath) && file_exists($dumpPath)) {
            try {
                $this->newLine();
                $this->output->write('  DB-Dump verarbeiten... ');

                $sqlDumpProcessor = app(SqlDumpProcessor::class);

                // Validierung
                $validation = $sqlDumpProcessor->validate($dumpPath);
                if (! $validation->valid) {
                    $this->warn('✗');
                    foreach ($validation->errors as $error) {
                        $this->error("    {$error}");
                    }
                } else {
                    $this->info('✓');

                    if (! empty($validation->warnings)) {
                        foreach ($validation->warnings as $warning) {
                            $this->warn("    ⚠ {$warning}");
                        }
                    }

                    // Temp-DB erstellen und Dump importieren
                    $this->output->write('  Temp-DB erstellen & importieren... ');
                    $tempDbInfo = $sqlDumpProcessor->process($dumpPath);
                    $this->info('✓');
                    $this->line("    → Temp-DB: {$tempDbInfo->databaseName}");

                    // Tenant-IDs ermitteln
                    $tenantIds = $sqlDumpProcessor->getAvailableTenantIds();
                    $sourceTenantId = 0;

                    if (count($tenantIds) === 1) {
                        $sourceTenantId = $tenantIds[0];
                        $this->line("    → Quell-Tenant-ID: {$sourceTenantId}");
                    } elseif (count($tenantIds) > 1) {
                        $this->line("    → Gefundene Tenant-IDs: " . implode(', ', $tenantIds));
                        $sourceTenantId = (int) $this->choice(
                            'Welche Tenant-ID soll importiert werden?',
                            array_map('strval', $tenantIds),
                            (string) $tenantIds[0],
                        );
                    }

                    // Import im Tenant-Context ausführen
                    $this->newLine();
                    $this->info('  ── Datenimport starten ──');

                    $importService = app(TenantImportService::class);

                    $tenant->run(function () use ($importService, $tenant, $tempDbInfo, $sourceTenantId) {
                        $result = $importService->import(
                            $tenant,
                            $tempDbInfo->connectionName,
                            $sourceTenantId,
                            [],
                            function (int $processed, int $total, int $percent, string $name) {
                                $bar = str_repeat('█', (int) ($percent / 5)) . str_repeat('░', 20 - (int) ($percent / 5));
                                $this->output->write("\r  [{$bar}] {$percent}% ({$processed}/{$total}) {$name}");
                            },
                        );

                        $this->newLine();
                        $this->newLine();
                        $this->info('  ── Import-Ergebnis ──');
                        $this->table(
                            ['Entität', 'Importiert', 'Übersprungen', 'Fehlgeschlagen'],
                            [
                                ['Companies', $result->companiesImported, $result->companiesSkipped, $result->companiesFailed],
                                ['Kategorien', $result->categoriesMapped, '-', '-'],
                                ['Öffnungszeiten', $result->openingHoursImported, '-', '-'],
                                ['Bewertungen', $result->reviewsImported, '-', '-'],
                                ['Fotos', $result->photosImported, '-', '-'],
                            ],
                        );

                        if (! empty($result->errors)) {
                            $this->newLine();
                            $errorCount = count($result->errors);
                            $this->warn("  {$errorCount} Fehler aufgetreten:");
                            foreach (array_slice($result->errors, 0, 10) as $error) {
                                $this->line("    • {$error}");
                            }
                            if (count($result->errors) > 10) {
                                $this->line("    ... und " . (count($result->errors) - 10) . " weitere.");
                            }
                        }
                    });

                    // Temp-DB aufräumen
                    $this->output->write('  Temp-DB aufräumen... ');
                    $sqlDumpProcessor->cleanupDatabase();
                    $this->info('✓');
                }
            } catch (\Exception $e) {
                $this->warn("✗ Datenimport fehlgeschlagen: {$e->getMessage()}");
            }
        }

        // ─── Owner verknüpfen ──────────────────────────────────────
        if ($owner) {
            try {
                $this->output->write('  Owner verknüpfen... ');

                $tenant->users()->attach($owner);

                $tenantPermissionService = app(\App\Services\TenantPermissionService::class);
                $tenantPermissionService->assignTenantUserRole(
                    $tenant,
                    $owner,
                    \App\Constants\TenancyPermissionConstants::TENANT_CREATOR_ROLE
                );

                $this->info('✓');
            } catch (\Exception $e) {
                $this->warn("✗ Owner-Verknüpfung fehlgeschlagen: {$e->getMessage()}");
            }
        }

        // ─── Zusammenfassung ───────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  ✅ Tenant erfolgreich erstellt');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $this->line("  Tenant-ID:   {$tenant->id}");
        $this->line("  UUID:        {$tenant->uuid}");
        $this->line("  Name:        {$tenant->name}");
        if ($domain) {
            $this->line("  Domain:      {$domain}");
            $this->line("  URL:         https://{$domain}");
        }
        $this->line("  Datenbank:   {$tenant->database()->getName()}");
        $this->line("  Storage:     storage/tenant{$tenant->uuid}");

        $this->newLine();
        if ($domain) {
            $this->comment('  Nächste Schritte:');
            $this->line('    1. DNS A-Record: ' . $domain . ' → Server-IP');
            $this->line('    2. SSL:  certbot --nginx -d ' . $domain);
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
