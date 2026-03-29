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
use Illuminate\Support\Facades\DB;
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
        $existingTenant = $domain ? Tenant::where('domain', $domain)->first() : null;

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

                    $url = "https://widimedia.com/tenant-info?domain={$domain}&token=SecretX1FC";
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
                            $dumpUrl = "https://widimedia.com/tenant-dump?domain={$domain}&token=SecretX1FC";
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

                            $dumpUrl = "https://widimedia.com/tenant-dump?domain={$domain}&token=SecretX1FC";
                            $dumpFileName = "tenant-dump-dryrun-" . Str::slug($domain) . "-" . now()->format('Y-m-d_His') . ".sql.gz";
                            $dumpPath = sys_get_temp_dir() . "/{$dumpFileName}";

                            $dumpResponse = Http::timeout(300)->withOptions(['sink' => $dumpPath])->get($dumpUrl);

                            if ($dumpResponse->successful() && file_exists($dumpPath)) {
                                $sqlDumpProcessor = app(SqlDumpProcessor::class);
                                $validation = $sqlDumpProcessor->validate($dumpPath);

                                if ($validation->valid) {
                                    $tempDbInfo = $sqlDumpProcessor->process($dumpPath);
                                    $tenantIds = $sqlDumpProcessor->getAvailableTenantIds();
                                    $sourceTenantId = 0; // Alle Tenant-IDs importieren

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

        // ─── Tenant erstellen oder bestehenden verwenden ───────────
        $this->newLine();

        if ($existingTenant) {
            $tenant = $existingTenant;
            $uuid = $tenant->uuid;
            $this->warn("  Tenant mit Domain '{$domain}' existiert bereits (UUID: {$uuid}) — überspringe Anlage, fahre mit nächsten Steps fort.");
        } else {
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
        }

        // ─── link-db-to-dbuser ─────────────────────────────────────
        try {
            $this->output->write('  link-db-to-dbuser... ');

            $dbName = $tenant->database()->getName();
            $result = Process::run("link-db-to-dbuser {$dbName}");

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

                $url = "https://widimedia.com/tenant-info?domain={$domain}&token=SecretX1FC";
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

                $dumpUrl = "https://widimedia.com/tenant-dump?domain={$domain}&token=SecretX1FC";
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
                // Zielverzeichnis erstellen falls nicht vorhanden
                if (! is_dir($tenantStoragePath)) {
                    mkdir($tenantStoragePath, 0755, true);
                }

                $this->info("  Fotos übertragen ({$remoteSlug}):");

                $rsyncCommand = "rsync -avz --progress --stats root@widimedia.com:/var/www/vhosts/widimedia.com/httpdocs/storage/app/public/{$remoteSlug}/photos {$tenantStoragePath}/";

                $fullOutput = '';
                $result = Process::forever()->run($rsyncCommand, function (string $type, string $output) use (&$fullOutput) {
                    $fullOutput .= $output;
                    // Echtzeit-Ausgabe: jede Zeile direkt anzeigen
                    foreach (explode("\n", $output) as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        // Fortschrittszeilen (z.B. "  1,234,567 100%  12.34MB/s") mit \r überschreiben
                        if (preg_match('/\d+%/', $line)) {
                            $this->output->write("\r    {$line}");
                        } else {
                            $this->line("    {$line}");
                        }
                    }
                });

                // Neue Zeile nach letzter \r-Zeile
                $this->newLine();

                if ($result->successful()) {
                    // rsync --stats Output parsen
                    $filesTransferred = 0;
                    $totalSize = '';

                    if (preg_match('/Number of regular files transferred:\s*([\d,]+)/', $fullOutput, $m)) {
                        $filesTransferred = (int) str_replace(',', '', $m[1]);
                    } elseif (preg_match('/Number of files transferred:\s*([\d,]+)/', $fullOutput, $m)) {
                        $filesTransferred = (int) str_replace(',', '', $m[1]);
                    }

                    if (preg_match('/Total transferred file size:\s*([\d,.]+\s*\w*)/', $fullOutput, $m)) {
                        $totalSize = trim($m[1]);
                    }

                    $this->info("  ✓ {$filesTransferred} Dateien übertragen" . ($totalSize ? " ({$totalSize})" : ''));

                    // chown setzen
                    $this->output->write('  Berechtigungen setzen... ');
                    $chownResult = Process::timeout(600)->run('chown -R sanitaerfinden:sanitaerfinden /home/sanitaerfinden/htdocs/sanitaerfinden.dev');

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

                    // Alle Daten importieren (kein Tenant-ID-Filter)
                    $sourceTenantId = 0;
                    $tenantIds = $sqlDumpProcessor->getAvailableTenantIds();
                    $this->line("    → Gefundene Tenant-IDs: " . implode(', ', $tenantIds) . " (alle werden importiert)");

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

        // ─── Tenant-Daten (data-Feld) setzen ─────────────────────────
        if ($domain && ! empty($tenantInfo)) {
            try {
                $this->output->write('  Tenant-Daten setzen... ');

                $remoteTenantName = $tenantInfo['name'] ?? $name;
                $remoteGoogleAnalytics = $tenantInfo['google_analytics_id'] ?? null;

                $tenantData = [
                    'created_at' => '2026-02-14 02:26:02',
                    'updated_at' => '2026-02-14 02:26:02',
                    'logo_url' => null,
                    'site_name' => null,
                    'theme.active' => 'default',
                    'primary_color' => null,
                    'theme.options' => [
                        'listing_layout' => 'list',
                        'show_sidebar' => true,
                        'show_hero' => true,
                    ],
                    'branding.impressum' => "<h1>Impressum</h1>\n\n<h2>Angaben gemäß § 5 DDG</h2>\n<p>\n    <strong>Rolland Szalai</strong><br />\n    Karlsruherstr. 31<br />\n    76437 Rastatt<br />\n    Deutschland\n</p>\n\n<h2>Kontakt</h2>\n<p>\n    Telefon: [BETREIBER_TELEFON]<br />\n    E-Mail: <a href=\"mailto:[BETREIBER_EMAIL]\">[BETREIBER_EMAIL]</a>\n</p>\n\n<h2>Vertreten durch</h2>\n<p>\n    <strong>Rolland Szalai</strong><br />\n</p>\n\n<h2>Redaktionell verantwortlich</h2>\n<p>\n    <strong>Rolland Szalai</strong><br />\n    Karlsruherstr. 31<br />\n    76437 Rastatt<br />\n    Deutschland\n</p>\n\n<h2>EU-Streitschlichtung</h2>\n<p>\n    Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:\n    <a href=\"https://ec.europa.eu/consumers/odr/\" target=\"_blank\" rel=\"noreferrer noopener\">https://ec.europa.eu/consumers/odr/</a>\n</p>\n<p>\n    Unsere E-Mail-Adresse finden Sie oben im Impressum.\n</p>\n\n<h2>Verbraucherstreitbeilegung / Universalschlichtungsstelle</h2>\n<p>\n    Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer\n    Verbraucherschlichtungsstelle teilzunehmen.\n</p>\n\n<h2>Haftung für Inhalte</h2>\n<p>\n    Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten\n    nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als\n    Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde\n    Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige\n    Tätigkeit hinweisen.\n</p>\n<p>\n    Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den\n    allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch\n    erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei\n    Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend\n    entfernen.\n</p>\n\n<h2>Haftung für Links</h2>\n<p>\n    Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen\n    Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen.\n    Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber\n    der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf\n    mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der\n    Verlinkung nicht erkennbar.\n</p>\n<p>\n    Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete\n    Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von\n    Rechtsverletzungen werden wir derartige Links umgehend entfernen.\n</p>\n\n<h2>Urheberrecht</h2>\n<p>\n    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen\n    dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art\n    der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen\n    Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind\n    nur für den privaten, nicht kommerziellen Gebrauch gestattet.\n</p>\n<p>\n    Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die\n    Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche\n    gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden,\n    bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen\n    werden wir derartige Inhalte umgehend entfernen.\n</p>\n\n<h2>Branchenportal — Nutzergenerierte Inhalte</h2>\n<p>\n    Dieses Portal (<strong>[PORTAL_NAME]</strong>) ermöglicht es Firmeninhabern, eigenständig\n    Unternehmenseinträge zu erstellen und zu verwalten, sowie Nutzern, Bewertungen abzugeben.\n    Für die Richtigkeit und Vollständigkeit der von Firmeninhabern eingestellten\n    Unternehmensdaten übernehmen wir keine Haftung. Jeder Firmeninhaber ist für die Inhalte\n    seines Eintrags selbst verantwortlich.\n</p>\n<p>\n    Bewertungen geben die persönliche Meinung der jeweiligen Verfasser wieder und stellen\n    keine Empfehlung oder Bewertung durch den Portalbetreiber dar. Wir behalten uns vor,\n    Bewertungen zu moderieren und bei Verstoß gegen unsere Nutzungsbedingungen zu entfernen.\n</p>\n",
                    'branding.logo_path' => null,
                    'branding.site_title' => $remoteTenantName,
                    'branding.datenschutz' => "\n    <h1>Datenschutzerklärung</h1>\n    <p><strong>Stand:</strong> Februar 2026</p>\n\n    <h2>1. Verantwortlicher</h2>\n    <p>\n        Verantwortlich für die Datenverarbeitung auf diesem Portal ist:<br />\n    <strong>Rolland Szalai</strong><br />\n    Karlsruherstr. 31<br />\n    76437 Rastatt<br />\n    Deutschland<br />\n        E-Mail: <a href=\"mailto:[BETREIBER_EMAIL]\">[BETREIBER_EMAIL]</a><br />\n        Telefon: [BETREIBER_TELEFON]\n    </p>\n    <h2>2. Übersicht der Datenverarbeitungen</h2>\n    <p>\n        Dieses Branchenportal (<strong>[PORTAL_NAME]</strong>) ermöglicht es Nutzern, Unternehmen zu finden, zu bewerten\n        und eigene Firmeneinträge zu erstellen. Die nachfolgende Datenschutzerklärung erläutert, welche personenbezogenen\n        Daten wir erheben, zu welchem Zweck und auf welcher Rechtsgrundlage.\n    </p>\n\n    <h2>3. Hosting und technische Bereitstellung</h2>\n    <p>\n        Unser Portal wird auf Servern in Deutschland/der EU gehostet. Beim Aufruf der Website werden automatisch\n        folgende Daten in Server-Logfiles gespeichert:\n    </p>\n    <ul>\n        <li>IP-Adresse des anfragenden Rechners (anonymisiert)</li>\n        <li>Datum und Uhrzeit des Zugriffs</li>\n        <li>Name und URL der abgerufenen Seite</li>\n        <li>Übertragene Datenmenge</li>\n        <li>Browsertyp und -version</li>\n        <li>Verwendetes Betriebssystem</li>\n        <li>Referrer-URL (zuvor besuchte Seite)</li>\n    </ul>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der technischen\n        Bereitstellung und Sicherheit des Portals).<br />\n        <strong>Speicherdauer:</strong> Logfiles werden nach 30 Tagen automatisch gelöscht.\n    </p>\n\n    <h2>4. Registrierung und Nutzerkonto</h2>\n    <p>\n        Sie können auf unserem Portal ein Nutzerkonto erstellen, um Firmeneinträge zu verwalten oder Bewertungen\n        abzugeben. Dabei werden folgende Daten erhoben:\n    </p>\n    <ul>\n        <li>Vor- und Nachname</li>\n        <li>E-Mail-Adresse</li>\n        <li>Passwort (verschlüsselt gespeichert)</li>\n        <li>Optional: Firmenname, Telefonnummer</li>\n    </ul>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung — Bereitstellung des Nutzerkontos).<br />\n        <strong>Speicherdauer:</strong> Bis zur Löschung des Nutzerkontos durch den Nutzer oder auf Anfrage.\n    </p>\n\n    <h2>5. Firmeneinträge (Self-Service)</h2>\n    <p>\n        Firmeninhaber können über unser Portal kostenlos oder kostenpflichtig (Premium) einen Firmeneintrag erstellen.\n        Dabei werden folgende Daten verarbeitet:\n    </p>\n    <ul>\n        <li>Firmenname, Rechtsform</li>\n        <li>Anschrift (Straße, PLZ, Ort)</li>\n        <li>Kontaktdaten (Telefon, E-Mail, Website)</li>\n        <li>Branche/Kategorie</li>\n        <li>Beschreibungstext</li>\n        <li>Firmenlogo und Bilder</li>\n        <li>Öffnungszeiten</li>\n    </ul>\n    <p>\n        Diese Daten werden <strong>öffentlich</strong> auf dem Portal angezeigt, da dies der ausdrückliche Zweck\n        des Eintrags ist.\n    </p>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) und Art. 6 Abs. 1 lit. a\n        DSGVO (Einwilligung zur Veröffentlichung).<br />\n        <strong>Speicherdauer:</strong> Bis zur Löschung des Eintrags durch den Firmeninhaber oder auf Anfrage.\n    </p>\n\n    <h2>6. Bewertungen</h2>\n    <p>\n        Nutzer können Unternehmen auf unserem Portal bewerten. Dabei werden erhoben:\n    </p>\n    <ul>\n        <li>Sternebewertung (1–5)</li>\n        <li>Bewertungstext</li>\n        <li>Name (optional — andernfalls „Anonym\")</li>\n        <li>E-Mail-Adresse (nicht öffentlich, nur zur Verifizierung)</li>\n        <li>IP-Adresse (zur Missbrauchsprävention, nicht öffentlich)</li>\n    </ul>\n    <p>\n        Bewertungen durchlaufen eine Moderation, bevor sie veröffentlicht werden.\n    </p>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einem\n        vertrauenswürdigen Bewertungssystem).<br />\n        <strong>Speicherdauer:</strong> Bis zur Löschung durch den Nutzer, den Portaladministrator oder auf\n        berechtigten Antrag des bewerteten Unternehmens.\n    </p>\n\n    <h2>7. Zahlungsabwicklung (Premium-Einträge)</h2>\n    <p>\n        Für kostenpflichtige Premium-Einträge nutzen wir <strong>Stripe</strong> (Stripe Inc., 354 Oyster Point Blvd,\n        South San Francisco, CA 94080, USA) als Zahlungsdienstleister.\n    </p>\n    <p>\n        Bei einer Zahlung werden Ihre Zahlungsdaten (Kreditkartennummer, IBAN etc.) direkt an Stripe übermittelt.\n        Wir selbst speichern <strong>keine</strong> vollständigen Zahlungsdaten — lediglich eine Referenz-ID,\n        den Zahlungsstatus und das Abonnement-Modell.\n    </p>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).<br />\n        Datenschutzerklärung von Stripe:\n        <a href=\"https://stripe.com/de/privacy\" target=\"_blank\" rel=\"noreferrer noopener\">stripe.com/de/privacy</a>\n    </p>\n\n    <h2>8. Cookies</h2>\n    <p>Unser Portal verwendet folgende Cookies:</p>\n\n    <h3>8.1 Technisch notwendige Cookies</h3>\n    <table>\n        <thead>\n            <tr>\n                <th>Cookie</th>\n                <th>Zweck</th>\n                <th>Dauer</th>\n            </tr>\n        </thead>\n        <tbody>\n            <tr>\n                <td>session</td>\n                <td>Sitzungsverwaltung, Login-Status</td>\n                <td>Sitzungsende</td>\n            </tr>\n            <tr>\n                <td>XSRF-TOKEN</td>\n                <td>Schutz vor Cross-Site-Request-Forgery</td>\n                <td>Sitzungsende</td>\n            </tr>\n            <tr>\n                <td>cookie_consent</td>\n                <td>Speicherung Ihrer Cookie-Präferenzen</td>\n                <td>12 Monate</td>\n            </tr>\n        </tbody>\n    </table>\n    <p><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (technisch erforderlich).</p>\n\n    <h3>8.2 Analyse-Cookies (nur mit Einwilligung)</h3>\n    <p>\n        Sofern Sie einwilligen, setzen wir Google Analytics ein, um die Nutzung des Portals auszuwerten.\n        Google Analytics verwendet Cookies, die eine Analyse der Benutzung der Website ermöglichen.\n        Die IP-Adresse wird anonymisiert (anonymizeIp).\n    </p>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).<br />\n        Sie können Ihre Einwilligung jederzeit über den Cookie-Banner widerrufen.\n    </p>\n\n    <h2>9. Kontaktaufnahme</h2>\n    <p>\n        Wenn Sie uns per E-Mail oder über ein Kontaktformular kontaktieren, werden die von Ihnen mitgeteilten\n        Daten (Name, E-Mail, Nachrichteninhalt) zur Bearbeitung Ihrer Anfrage gespeichert.\n    </p>\n    <p>\n        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Beantwortung\n        von Anfragen).<br />\n        <strong>Speicherdauer:</strong> Bis zur abschließenden Bearbeitung, maximal 6 Monate.\n    </p>\n\n    <h2>10. Ihre Rechte</h2>\n    <p>Sie haben nach der DSGVO folgende Rechte:</p>\n    <ul>\n        <li><strong>Auskunftsrecht</strong> (Art. 15 DSGVO) — Welche Daten speichern wir über Sie?</li>\n        <li><strong>Berichtigungsrecht</strong> (Art. 16 DSGVO) — Korrektur unrichtiger Daten</li>\n        <li><strong>Löschungsrecht</strong> (Art. 17 DSGVO) — Löschung Ihrer Daten („Recht auf Vergessenwerden\")</li>\n        <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO)</li>\n        <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO) — Export Ihrer Daten in maschinenlesbarem Format</li>\n        <li><strong>Widerspruchsrecht</strong> (Art. 21 DSGVO) — Widerspruch gegen Verarbeitung auf Basis berechtigter Interessen</li>\n        <li><strong>Widerruf der Einwilligung</strong> (Art. 7 Abs. 3 DSGVO) — Jederzeit für die Zukunft</li>\n    </ul>\n    <p>\n        Zur Ausübung Ihrer Rechte kontaktieren Sie uns unter:\n        <a href=\"mailto:[BETREIBER_EMAIL]\">[BETREIBER_EMAIL]</a>\n    </p>\n\n    <h2>11. Beschwerderecht</h2>\n    <p>\n        Sie haben das Recht, sich bei einer Datenschutz-Aufsichtsbehörde über unsere Verarbeitung personenbezogener\n        Daten zu beschweren. Die zuständige Aufsichtsbehörde richtet sich nach dem Bundesland des Betreibers.\n        Eine Liste der Aufsichtsbehörden finden Sie unter:\n        <a href=\"https://www.bfdi.bund.de\" target=\"_blank\" rel=\"noreferrer noopener\">www.bfdi.bund.de</a>\n    </p>\n\n    <h2>12. SSL/TLS-Verschlüsselung</h2>\n    <p>\n        Dieses Portal nutzt aus Sicherheitsgründen eine SSL/TLS-Verschlüsselung. Eine verschlüsselte Verbindung\n        erkennen Sie an dem Schloss-Symbol in der Browserzeile und daran, dass die Adresszeile mit\n        https:// beginnt.\n    </p>\n\n    <h2>13. Änderungen dieser Datenschutzerklärung</h2>\n    <p>\n        Wir behalten uns vor, diese Datenschutzerklärung bei Bedarf anzupassen, um sie an geänderte Rechtslagen\n        oder bei Änderungen unserer Datenverarbeitungen anzupassen. Die jeweils aktuelle Version finden Sie\n        stets auf dieser Seite.\n    </p>\n",
                    'branding.footer_text' => '© {year} {tenant_name}. Alle Rechte vorbehalten.',
                    'branding.accent_color' => '#F77F00',
                    'branding.favicon_path' => null,
                    'branding.meta_keywords' => null,
                    'branding.og_image_path' => null,
                    'branding.primary_color' => '#0077B6',
                    'settings.contact_email' => 'info@widimedia.com',
                    'settings.contact_phone' => '+4915561476133',
                    'settings.social_twitter' => null,
                    'branding.secondary_color' => '#023E8A',
                    'features.reviews_enabled' => true,
                    'settings.contact_address' => "Karlsruherstr. 31\n76437 Rastatt",
                    'settings.social_facebook' => null,
                    'settings.social_linkedin' => null,
                    'branding.site_description' => null,
                    'settings.social_instagram' => null,
                    'settings.google_analytics_id' => $remoteGoogleAnalytics,
                    'features.registration_enabled' => true,
                    'settings.google_tag_manager_id' => null,
                    'features.premium_listings_enabled' => false,
                ];

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['data' => json_encode($tenantData)]);

                $this->info('✓');
                $this->line("    → site_title: {$remoteTenantName}");
                if ($remoteGoogleAnalytics) {
                    $this->line("    → google_analytics_id: {$remoteGoogleAnalytics}");
                }
            } catch (\Exception $e) {
                $this->warn("✗ Tenant-Daten setzen fehlgeschlagen: {$e->getMessage()}");
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
