<?php

declare(strict_types=1);

namespace App\Guide\Legacy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sicherung und Wiederherstellung der Tabellen der alten Content-Pipeline (#23).
 *
 * Vor dem Drop (Migrationen 2026_09_22_000005 tenant, 2026_09_22_000014
 * central) schreibt diese Klasse jede Tabelle als NDJSON (eine Zeile je
 * Datensatz) nach storage/app/backups/legacy-content/<datum>/:
 *
 *   tenant-<id>-<tabelle>.ndjson   je Portal, Tenant-Verbindung
 *   central-<tabelle>.ndjson       Central-Verbindung
 *
 * `posts` gehoert nie dazu. Die Drop-Migration des dritten Rueckbauteils
 * (tenant/2026_09_23_000002, #34) sichert ihre Tabellen ueber den Parameter
 * `$tables` in denselben Ordner.
 *
 * Die Migrationen rufen backupTenant()/backupCentral() selbst auf und brechen
 * ab, wenn das Schreiben scheitert: ohne Sicherung kein Drop. Eine vorhandene
 * Datei wird nie ueberschrieben; weitere Laeufe desselben Tages schreiben
 * <tabelle>.<His>.ndjson daneben, zurueckgespielt wird die erste. Von Hand:
 * `php artisan guide:legacy:backup` (Sichern) bzw. `--restore=<datum>` nach
 * einem `migrate:rollback` (Tabellen stehen dann leer wieder da), mit
 * `--tables=a,b` fuer andere als die Standardtabellen.
 */
class LegacyPipelineBackup
{
    /**
     * Tenant-Tabellen, die die Drop-Migration entfernt, in Drop-Reihenfolge
     * (Kinder vor Eltern).
     *
     * @var list<string>
     */
    public const DROPPED_TENANT_TABLES = [
        'fact_snippets',
        'topic_candidates',
        'keyword_clusters',
        'source_items',
        'seasonal_topics',
        'geo_regions',
    ];

    /** @var list<string> */
    public const CENTRAL_TABLES = ['content_source_settings', 'content_fingerprints'];

    /**
     * Bewusst base_path statt storage_path(): im Tenant-Kontext (Migration
     * per tenants:migrate) haengt stancl an storage_path() den Tenant an —
     * die Sicherung aller Portale soll aber an einer Stelle liegen.
     */
    public static function directory(?string $date = null): string
    {
        return base_path('storage/app/backups/legacy-content/'.($date ?? now()->toDateString()));
    }

    /**
     * Sichert Tenant-Tabellen des aktuell initialisierten Portals, ohne
     * Angabe die aus DROPPED_TENANT_TABLES.
     *
     * @param  list<string>|null  $tables
     * @return array<string, int> Tabelle => Datensaetze (fehlende Tabellen fehlen)
     */
    public function backupTenant(int $tenantId, ?string $directory = null, ?array $tables = null): array
    {
        return $this->backup(DB::connection(), $tables ?? self::DROPPED_TENANT_TABLES, "tenant-{$tenantId}-", $directory);
    }

    /**
     * @return array<string, int>
     */
    public function backupCentral(?string $directory = null): array
    {
        return $this->backup(DB::connection('central'), self::CENTRAL_TABLES, 'central-', $directory);
    }

    /**
     * Spielt die gesicherten Tenant-Tabellen des aktuellen Portals zurueck,
     * ohne Angabe die aus DROPPED_TENANT_TABLES. Nur in leere Tabellen
     * (nach migrate:rollback), sonst Abbruch.
     *
     * @param  list<string>|null  $tables
     * @return array<string, int>
     */
    public function restoreTenant(int $tenantId, string $directory, ?array $tables = null): array
    {
        return $this->restore(DB::connection(), $tables ?? array_reverse(self::DROPPED_TENANT_TABLES), "tenant-{$tenantId}-", $directory);
    }

    /**
     * @return array<string, int>
     */
    public function restoreCentral(string $directory): array
    {
        return $this->restore(DB::connection('central'), self::CENTRAL_TABLES, 'central-', $directory);
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function backup(Connection $connection, array $tables, string $prefix, ?string $directory): array
    {
        $directory ??= self::directory();

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Sicherungsordner nicht anlegbar: {$directory}");
        }

        $counts = [];

        foreach ($tables as $table) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $file = "{$directory}/{$prefix}{$table}.ndjson";

            // Nie ueberschreiben: die erste Sicherung des Tages ist der Stand
            // vor dem Drop. Ein zweiter Lauf (etwa nach migrate:rollback ohne
            // Rueckspielen) wuerde sonst leere Tabellen darueberschreiben.
            if (is_file($file)) {
                $file = "{$directory}/{$prefix}{$table}.".now()->format('His').'.ndjson';
            }

            $handle = fopen($file, 'x');

            if ($handle === false) {
                throw new RuntimeException("Datei nicht schreibbar: {$file}");
            }

            $written = 0;

            try {
                foreach ($connection->table($table)->orderBy('id')->cursor() as $record) {
                    if (fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
                        throw new RuntimeException("Schreiben fehlgeschlagen: {$file}");
                    }

                    $written++;
                }
            } finally {
                fclose($handle);
            }

            $counts[$table] = $written;
        }

        return $counts;
    }

    /**
     * Fremdschluessel sind waehrend des Einspielens aus: die Sicherung ist
     * ein konsistenter Stand, die Reihenfolge der Tabellen spielt dann keine
     * Rolle (etwa Fremdschluessel auf Tabellen einer spaeteren Migration).
     *
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function restore(Connection $connection, array $tables, string $prefix, string $directory): array
    {
        return $connection->getSchemaBuilder()->withoutForeignKeyConstraints(
            fn (): array => $this->restoreTables($connection, $tables, $prefix, $directory),
        );
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function restoreTables(Connection $connection, array $tables, string $prefix, string $directory): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $file = "{$directory}/{$prefix}{$table}.ndjson";

            if (! is_file($file)) {
                continue;
            }

            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Tabelle {$table} fehlt — zuerst migrate:rollback bzw. tenants:rollback.");
            }

            if ($connection->table($table)->exists()) {
                throw new RuntimeException("Tabelle {$table} ist nicht leer, Wiederherstellung abgebrochen.");
            }

            $handle = fopen($file, 'r');

            if ($handle === false) {
                throw new RuntimeException("Datei nicht lesbar: {$file}");
            }

            $restored = 0;
            $batch = [];

            try {
                while (($line = fgets($handle)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $batch[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                    if (count($batch) === 500) {
                        $connection->table($table)->insert($batch);
                        $restored += count($batch);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $connection->table($table)->insert($batch);
                    $restored += count($batch);
                }
            } finally {
                fclose($handle);
            }

            $counts[$table] = $restored;
        }

        return $counts;
    }
}
