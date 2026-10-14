<?php

declare(strict_types=1);

namespace App\Services\DbPull;

/**
 * Entscheidet je Datenbank, von welchen Tabellen Daten geholt werden und von
 * welchen nur die Struktur (#4).
 *
 * Drei Regeln bestimmen den Aufbau:
 *
 * 1. **Strukturen kommen immer.** Eine lokal fehlende Tabelle laesst Code und
 *    Migrationen auflaufen; eine leere Tabelle nicht. Uebersprungen werden
 *    deshalb nur die DATEN (`--no-data` fuer die Skip-Liste,
 *    `--ignore-table` fuer den Rest — zwei Dumplaeufe in derselben Pipe).
 * 2. **Muster werden gegen die Tabellen der Live-Datenbank aufgeloest, nie
 *    an `mariadb-dump` weitergegeben.** `--ignore-table` will den vollen
 *    Namen `db.tabelle` und kennt keine Platzhalter; eine Tabelle, die es auf
 *    Live nicht gibt, laesst den Dump abbrechen.
 * 3. **Laufzeit ergaenzt, ueberschreibt aber nicht.** `--skip-data=` kommt zur
 *    Liste aus `config/db-pull.php` hinzu. Nur `--only-tables=` setzt die
 *    Skip-Liste ausser Kraft: wer die Datenmenge selbst benennt, hat damit
 *    schon entschieden.
 */
class TableFilter
{
    /**
     * Aufgeloeste Muster je Tabellenliste. Der Befehl fragt dieselbe
     * Datenbank mehrfach (Start, Zusammenfassung, Probelauf); der Abgleich
     * laeuft dann einmal.
     *
     * @var array<string, array<int, string>>
     */
    private array $resolved = [];

    /**
     * @param  array<int, string>  $skipPatterns  Tabellennamen, Platzhalter erlaubt (telescope_*)
     * @param  array<int, string>  $onlyTables  leer = alle Tabellen bekommen Daten
     */
    public function __construct(
        private readonly array $skipPatterns = [],
        private readonly array $onlyTables = [],
    ) {}

    /**
     * Filter fuer eine Datenbank: 'central' oder 'tenant' aus
     * `config/db-pull.php`, dazu die Laufzeit-Listen des Aufrufs.
     *
     * @param  array<int, string>  $extraSkip  aus --skip-data=
     * @param  array<int, string>  $onlyTables  aus --only-tables=
     */
    public static function forScope(string $scope, array $extraSkip = [], array $onlyTables = []): self
    {
        return new self(
            skipPatterns: array_merge((array) config("db-pull.skip_data.{$scope}", []), $extraSkip),
            onlyTables: $onlyTables,
        );
    }

    public function hasOnlyTables(): bool
    {
        return $this->onlyTables !== [];
    }

    /**
     * Die Tabellen, von denen nur die Struktur geholt wird — immer
     * geschnitten auf die, die es auf Live wirklich gibt.
     *
     * Mit --only-tables ist das der Rest der Datenbank: alles, was nicht
     * ausdruecklich genannt wurde.
     *
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    public function skipData(array $liveTables): array
    {
        if ($this->hasOnlyTables()) {
            return array_values(array_diff($liveTables, $this->dataTables($liveTables)));
        }

        return $this->resolve($liveTables);
    }

    /**
     * Die Tabellen, von denen Daten geholt werden. Ohne --only-tables sind
     * das alle ausser der Skip-Liste; der Dump nimmt dann `--ignore-table`
     * und braucht diese Liste nicht aufzuzaehlen.
     *
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    public function dataTables(array $liveTables): array
    {
        if (! $this->hasOnlyTables()) {
            return array_values(array_diff($liveTables, $this->resolve($liveTables)));
        }

        // Platzhalter sind auch hier erlaubt (--only-tables=company_*).
        return $this->match($this->onlyTables, $liveTables);
    }

    /**
     * Mit --only-tables genannte Namen, die es auf Live nicht gibt — fuer
     * eine Warnung statt eines abbrechenden Dumps.
     *
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    public function unknownOnlyTables(array $liveTables): array
    {
        return array_values(array_filter(
            $this->onlyTables,
            fn (string $pattern): bool => $this->match([$pattern], $liveTables) === [],
        ));
    }

    /** @return array<int, string> */
    public function skipPatterns(): array
    {
        return array_values($this->skipPatterns);
    }

    /**
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    private function resolve(array $liveTables): array
    {
        $key = md5(implode("\n", $liveTables));

        return $this->resolved[$key] ??= $this->match($this->skipPatterns, $liveTables);
    }

    /**
     * Muster gegen echte Tabellennamen. `fnmatch` deckt `*` und `?` ab;
     * Namen ohne Platzhalter bleiben ein exakter Vergleich, damit ein
     * Tippfehler nicht versehentlich eine Nachbartabelle trifft.
     *
     * @param  array<int, string>  $patterns
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    private function match(array $patterns, array $liveTables): array
    {
        $matched = [];

        foreach ($liveTables as $table) {
            foreach ($patterns as $pattern) {
                $hit = str_contains($pattern, '*') || str_contains($pattern, '?')
                    ? fnmatch($pattern, $table)
                    : $pattern === $table;

                if ($hit) {
                    $matched[] = $table;

                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}
