<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\Support\TenantSelection;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sicherung vor dem ersten Produktivlauf der Bestandsbereinigung (#10).
 *
 *   php artisan antispam:backup
 *   php artisan antispam:backup --tenant=28 --date=2026-10-08
 *
 * Gesichert werden die beiden Tabellen, die `antispam:scan` anfasst: die
 * zentrale `users` und die `companies` jedes Portals. Der Lauf selbst loescht
 * nichts hart, und eine Markierung laesst sich im Admin zuruecknehmen — die
 * Sicherung ist trotzdem Vorbedingung des ersten Produktivlaufs: sie ist die
 * einzige Antwort auf einen Fehler in einer Regel, der mehr Datensaetze trifft
 * als gedacht.
 *
 * Format ist NDJSON, eine Zeile je Datensatz — wie bei
 * {@see ContentGoLiveBackup}: kein mysqldump auf dem Rechner noetig,
 * unabhaengig von der MySQL-Version und zeilenweise wieder einlesbar.
 *
 * Datenschutz: die Dateien enthalten Klarnamen, E-Mail-Adressen und
 * Passwort-Hashes. Sie werden mit 0600 geschrieben, liegen ausserhalb des
 * oeffentlichen Verzeichnisses und gehoeren nach der Abnahme geloescht
 * (docs/turnstile.md §16.5).
 */
class AntispamBackup extends Command
{
    protected $signature = 'antispam:backup
        {--tenant=* : Portale (ID, UUID, Name oder Domain); "*" oder ohne Angabe = alle}
        {--date= : Datum des Sicherungsordners (Y-m-d), Vorgabe: heute}';

    protected $description = 'Sichert die zentrale users und die companies jedes Portals als NDJSON vor dem ersten antispam:scan';

    public function handle(): int
    {
        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();

        $directory = self::directory($date);

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            $this->components->error("Sicherungsordner nicht anlegbar: {$directory}");

            return self::FAILURE;
        }

        $tenants = TenantSelection::resolve((array) $this->option('tenant'));

        if ($tenants->isEmpty()) {
            $this->components->error('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        try {
            $rows[] = ['zentral', 'users', $this->dumpCentral($directory), 'users.ndjson'];
        } catch (Throwable $exception) {
            $failed++;
            $this->components->error("users: {$exception->getMessage()}");
        }

        foreach ($tenants as $tenant) {
            try {
                $file = 'tenant-'.(int) $tenant->getKey().'-companies.ndjson';
                $rows[] = [(string) $tenant->name, 'companies', $this->dumpTenant($tenant, $directory.'/'.$file), $file];
            } catch (Throwable $exception) {
                $failed++;
                $this->components->error("[{$tenant->name}] companies: {$exception->getMessage()}");
            }
        }

        $this->table(['Portal', 'Tabelle', 'Datensätze', 'Datei'], $rows);
        $this->components->info("Sicherung liegt in {$directory}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Der Ordner, in dem die Sicherung eines Tages liegt. */
    public static function directory(string $date): string
    {
        return storage_path('app/backups/antispam/'.$date);
    }

    private function dumpCentral(string $directory): int
    {
        return $this->writeNdjson(
            $directory.'/users.ndjson',
            fn (callable $write): int => $this->stream(DB::connection(config('tenancy.database.central_connection', 'central')), 'users', $write),
        );
    }

    private function dumpTenant(Tenant $tenant, string $file): int
    {
        return $this->writeNdjson(
            $file,
            fn (callable $write): int => (int) $tenant->run(fn (): int => $this->stream(DB::connection(), 'companies', $write)),
        );
    }

    /**
     * @param  callable(callable(object): void): int  $producer
     */
    private function writeNdjson(string $file, callable $producer): int
    {
        $handle = fopen($file, 'w');

        if ($handle === false) {
            throw new RuntimeException("Datei nicht schreibbar: {$file}");
        }

        chmod($file, 0600);

        try {
            return $producer(static function (object $record) use ($handle): void {
                fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  callable(object): void  $write
     */
    private function stream(\Illuminate\Database\Connection $connection, string $table, callable $write): int
    {
        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        $written = 0;

        foreach ($connection->table($table)->orderBy('id')->cursor() as $record) {
            $write($record);
            $written++;
        }

        return $written;
    }
}
