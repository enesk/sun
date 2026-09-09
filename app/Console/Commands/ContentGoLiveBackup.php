<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\TenantCollection;
use Throwable;

/**
 * Sicherung der Artikeltabellen vor dem ersten automatischen Lauf (#26).
 *
 * Die Pipeline schreibt in `posts` — also in dieselbe Tabelle, in der die
 * von Hand gepflegten Beitraege eines Portals liegen. Vor dem Go-Live
 * gehoert davon eine Kopie auf die Platte, damit ein Fehllauf sich
 * zurueckdrehen laesst, ohne das gesamte Portal aus dem Datenbank-Backup
 * zu holen.
 *
 * Geschrieben wird NDJSON (eine Zeile je Datensatz) statt eines Dumps:
 * ohne mysqldump auf dem Rechner, unabhaengig von der MySQL-Version und
 * zeilenweise wieder einlesbar.
 *
 *   php artisan content:golive:backup
 *   php artisan content:golive:backup --tenant=7
 */
class ContentGoLiveBackup extends Command
{
    /**
     * Die Tabellen, die der Go-Live veraendert. `posts` ist die
     * Veroeffentlichungsziel-Tabelle, `article_drafts` das Protokoll der
     * Pipeline dazu.
     *
     * @var array<int, string>
     */
    public const TABLES = ['posts', 'article_drafts'];

    protected $signature = 'content:golive:backup
        {--tenant= : Nur dieses Portal (ID, UUID oder Domain), sonst alle}
        {--date= : Datum des Sicherungsordners (Y-m-d), Vorgabe: heute}';

    protected $description = 'Sichert posts und article_drafts jedes Portals als NDJSON vor dem ersten Pipeline-Lauf';

    public function handle(): int
    {
        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();

        $directory = self::directory($date);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Sicherungsordner nicht anlegbar: {$directory}");

            return self::FAILURE;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            foreach (self::TABLES as $table) {
                try {
                    $rows[] = $this->dump($tenant, $table, $directory);
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("[{$tenant->name}] {$table}: {$exception->getMessage()}");
                }
            }
        }

        $this->table(['Portal', 'Tabelle', 'Datensätze', 'Datei'], $rows);
        $this->info("Sicherung liegt in {$directory}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Der Ordner, in dem die Sicherung eines Tages liegt. Auch die
     * Vorabpruefung (`content:golive:check`) fragt hier nach.
     */
    public static function directory(string $date): string
    {
        return storage_path('app/backups/content/'.$date);
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: string}
     */
    private function dump(Tenant $tenant, string $table, string $directory): array
    {
        $file = $directory.'/tenant-'.(int) $tenant->getKey().'-'.$table.'.ndjson';
        $handle = fopen($file, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Datei nicht schreibbar: {$file}");
        }

        try {
            $count = (int) $tenant->run(function () use ($table, $handle): int {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    return 0;
                }

                $written = 0;

                foreach (DB::table($table)->orderBy('id')->cursor() as $record) {
                    fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                    $written++;
                }

                return $written;
            });
        } finally {
            fclose($handle);
        }

        return [(string) $tenant->name, $table, $count, basename($file)];
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            return Tenant::all();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
