<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;

/**
 * Anonymisierung nach dem Import (#1, #5).
 *
 * Laeuft LOKAL auf der schon importierten Datenbank, nie auf Live — ein
 * Anonymisierer mit Schreibrecht auf der Produktion waere genau das, was das
 * Sicherheitsmodell des Pulls ausschliesst.
 *
 * Die Ausdruecke stehen in config/db-pull.php und sind deterministisch ueber
 * die Zeilen-ID (`nutzer17@example.test`): ein zweiter Pull liefert dieselben
 * Werte, lokale Lesezeichen und Testlogins bleiben gueltig. Faker waere hier
 * falsch — zwei Laeufe wuerden verschiedene Daten liefern.
 *
 * Geschrieben wird ausschliesslich mit UPDATE/TRUNCATE auf der Verbindung,
 * nie ueber Eloquent: Model-Events, Observer und damit Mails oder Jobs
 * duerfen dabei nicht feuern.
 *
 * Grosse Tabellen (`reviews`, ~1,7 Mio. Zeilen) laufen in ID-Fenstern, damit
 * keine Transaktion ueber die ganze Tabelle entsteht.
 *
 * Tabellen oder Spalten, die es in dieser Datenbank nicht gibt, werden
 * uebersprungen — die Regeln duerfen also mehr abdecken als ein einzelnes
 * Portal besitzt.
 *
 * Bei einem Teil-Pull (`--tables=…`) wird die Liste der tatsaechlich
 * geholten Tabellen durchgereicht und die Regeln darauf geschnitten (#7):
 * ein Teil-Pull fasst laut Befehlsvertrag nur die genannten Tabellen an,
 * der uebrige lokale Stand — Testnutzer, lokale Schluessel — bleibt stehen.
 * Leere Liste heisst „alles", wie beim vollen Pull.
 */
class Anonymizer
{
    private ?string $passwordHash = null;

    public function __construct(private readonly LocalMariaDb $local) {}

    /**
     * @param  array<int, string>  $pulledTables  Nur diese Tabellen anfassen; leer = alle (voller Pull)
     * @return array{tables: int, columns: int, rows: int, truncated: int}
     */
    public function run(string $database, string $scope, array $pulledTables = []): array
    {
        /** @var array<string, array<string, string>> $rules */
        $rules = $this->limit((array) config("db-pull.anonymize_rules.{$scope}", []), $pulledTables);
        $json = $this->limit((array) config("db-pull.anonymize_json.{$scope}", []), $pulledTables);

        // Spalten zuerst: `connection()` setzt die Verbindung jedes Mal neu
        // auf, die Reihenfolge haelt den Handle unten frisch.
        $columns = $this->columns($database, array_keys($rules + $json));
        $connection = $this->local->connection($database);

        $touchedTables = 0;
        $touchedColumns = 0;
        $rows = 0;

        foreach ($rules as $table => $assignments) {
            if (! isset($columns[$table])) {
                continue;
            }

            $sets = [];
            $bindings = [];

            foreach ($assignments as $column => $expression) {
                if (! in_array($column, $columns[$table], true)) {
                    continue;
                }

                if (str_starts_with($expression, ':')) {
                    $sets[] = "`{$column}` = ?";
                    $bindings[] = $this->placeholder($expression);

                    continue;
                }

                $sets[] = "`{$column}` = {$expression}";
            }

            if ($sets === []) {
                continue;
            }

            $rows += $this->update($connection, $table, $sets, $bindings, in_array('id', $columns[$table], true));

            $touchedTables++;
            $touchedColumns += count($sets);
        }

        foreach ($json as $table => $jsonColumns) {
            if (! isset($columns[$table])) {
                continue;
            }

            foreach ((array) $jsonColumns as $column => $keys) {
                if (! in_array($column, $columns[$table], true) || $keys === []) {
                    continue;
                }

                $paths = implode(', ', array_map(
                    fn (string $key): string => "'$.".$key."'",
                    array_values((array) $keys)
                ));

                $rows += $connection->update(
                    "update `{$table}` set `{$column}` = JSON_REMOVE(`{$column}`, {$paths}) "
                    ."where `{$column}` is not null and JSON_VALID(`{$column}`)"
                );

                $touchedTables++;
                $touchedColumns++;
            }
        }

        $truncated = $this->truncate($connection, $database, $scope, $pulledTables);

        return [
            'tables' => $touchedTables,
            'columns' => $touchedColumns,
            'rows' => $rows,
            'truncated' => $truncated,
        ];
    }

    /**
     * Ein UPDATE je ID-Fenster. Ohne `id`-Spalte (Pivot-Tabellen) bleibt nur
     * das eine UPDATE ueber die ganze Tabelle — die betroffenen Tabellen
     * sind klein.
     *
     * @param  array<int, string>  $sets
     * @param  array<int, string>  $bindings
     */
    private function update(Connection $connection, string $table, array $sets, array $bindings, bool $hasId): int
    {
        $statement = 'update `'.$table.'` set '.implode(', ', $sets);

        if (! $hasId) {
            return $connection->update($statement, $bindings);
        }

        $maximum = (int) ($connection->selectOne('select max(`id`) as maximum from `'.$table.'`')->maximum ?? 0);

        if ($maximum === 0) {
            return 0;
        }

        $size = max(1, (int) config('db-pull.anonymize_batch_size', 20000));
        $rows = 0;

        for ($from = 0; $from < $maximum; $from += $size) {
            $rows += $connection->update(
                $statement.' where `id` > ? and `id` <= ?',
                array_merge($bindings, [$from, min($from + $size, $maximum)])
            );
        }

        return $rows;
    }

    /**
     * Reine Token- und Sitzungstabellen leeren. Fremdschluesselpruefung
     * waehrend des TRUNCATE aus: `sessions` & Co. haengen an `users`, und die
     * Reihenfolge der Liste soll keine Rolle spielen.
     *
     * @param  array<int, string>  $pulledTables
     */
    private function truncate(Connection $connection, string $database, string $scope, array $pulledTables): int
    {
        $wanted = (array) config("db-pull.anonymize_truncate.{$scope}", []);

        if ($pulledTables !== []) {
            $wanted = array_values(array_intersect($wanted, $pulledTables));
        }

        $tables = array_values(array_filter(
            $wanted,
            fn (string $table): bool => $this->tableExists($connection, $database, $table),
        ));

        if ($tables === []) {
            return 0;
        }

        $connection->statement('set foreign_key_checks = 0');

        foreach ($tables as $table) {
            $connection->statement('truncate table `'.$table.'`');
        }

        $connection->statement('set foreign_key_checks = 1');

        return count($tables);
    }

    private function tableExists(Connection $connection, string $database, string $table): bool
    {
        return $connection->selectOne(
            'select 1 as found from information_schema.tables where table_schema = ? and table_name = ?',
            [$database, $table]
        ) !== null;
    }

    /**
     * Platzhalterwerte, die nicht als SQL-Ausdruck in der Konfiguration
     * stehen koennen. Der Passwort-Hash wird zur Laufzeit erzeugt, damit kein
     * Hash im Repo liegt (Secret-Scan) — und nur einmal je Lauf, weil bcrypt
     * absichtlich langsam ist.
     */
    private function placeholder(string $name): string
    {
        return match ($name) {
            ':password_hash' => $this->passwordHash ??= Hash::make((string) config('db-pull.anonymize_password')),
            default => '',
        };
    }

    /**
     * Regeln auf die tatsaechlich geholten Tabellen schneiden. Leere Liste
     * heisst „alles" — beim vollen Pull aendert sich damit nichts.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<int, string>  $pulledTables
     * @return array<string, mixed>
     */
    private function limit(array $rules, array $pulledTables): array
    {
        if ($pulledTables === []) {
            return $rules;
        }

        return array_intersect_key($rules, array_flip($pulledTables));
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array<int, string>>
     */
    private function columns(string $database, array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        // Aliase in Kleinschreibung: MySQL 8 liefert die Spalten von
        // information_schema als TABLE_NAME/COLUMN_NAME zurueck, MariaDB
        // klein — ohne Alias haengt der Zugriff von der Serverversion ab.
        $rows = $this->local->connection($database)->select(
            'select table_name as tabelle, column_name as spalte from information_schema.columns '
            .'where table_schema = ? and table_name in ('.implode(',', array_fill(0, count($tables), '?')).')',
            array_merge([$database], $tables)
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[(string) $row->tabelle][] = (string) $row->spalte;
        }

        return $columns;
    }
}
