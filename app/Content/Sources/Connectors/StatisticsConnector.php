<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Sources\AbstractHttpConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\FactSnippetWriter;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Sources\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Amtliche Zahlen als Faktenschnipsel (#11), woechentlich.
 *
 * Holt die in config/content_statistics.php genannten GENESIS-Tabellen
 * (Destatis und Regionalstatistik) sowie die Monatsmittel des DWD und legt
 * daraus fact_snippets an: Kennzahl, Einheit, Region, Quelle, Abrufdatum und
 * Haltbarkeit. Der Generator (#14) zitiert daraus, das Qualitaetsgate (#15)
 * verwirft alles, dessen valid_until abgelaufen ist.
 *
 * Zusaetzlich entsteht je Datensatz ein source_item, damit der Quellen-Monitor
 * und das Scoring (#12) sehen, dass die Zahl frisch ist. Die Zahl selbst steht
 * im Faktenschnipsel, nicht im Rohsignal.
 *
 * GENESIS braucht Zugangsdaten (kostenlose Registrierung). Fehlen sie, wird
 * der GENESIS-Teil uebersprungen und nur der DWD gelesen — ein halb
 * konfiguriertes Staging darf keinen Provider-Alarm ausloesen.
 */
class StatisticsConnector extends AbstractHttpConnector
{
    public function __construct(
        private readonly FactSnippetWriter $facts,
    ) {}

    public function key(): string
    {
        return 'statistics';
    }

    public function schedule(): string
    {
        return SourceFrequency::WEEKLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $items = collect();

        foreach ($this->genesisTables($context) as $table) {
            $items = $items->merge($this->readGenesis($table, $context));
        }

        if ((bool) config('content_statistics.dwd.enabled', true)) {
            $items = $items->merge($this->readDwd($context));
        }

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | GENESIS / Regionalstatistik
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $table
     * @return Collection<int, SourceItemDto>
     */
    private function readGenesis(array $table, TenantContext $context): Collection
    {
        $endpoint = (array) config("content_statistics.endpoints.{$table['endpoint']}", []);

        if ((string) ($endpoint['username'] ?? '') === '' || (string) ($endpoint['password'] ?? '') === '') {
            Log::info('GENESIS-Tabelle uebersprungen: keine Zugangsdaten.', [
                'connector' => $this->key(),
                'table' => $table['code'],
                'endpoint' => $table['endpoint'],
            ]);

            return collect();
        }

        $url = rtrim((string) $endpoint['base_url'], '/').'/data/tablefile';

        try {
            $response = $this->json($url, $context, [
                'username' => (string) $endpoint['username'],
                'password' => (string) $endpoint['password'],
                'name' => (string) $table['code'],
                'area' => 'all',
                'format' => 'ffcsv',
                'language' => 'de',
            ]);
        } catch (Throwable $exception) {
            Log::warning('GENESIS-Abruf gescheitert.', [
                'connector' => $this->key(),
                'table' => $table['code'],
                'exception' => $exception->getMessage(),
            ]);

            return collect();
        }

        $rows = $this->parseFfcsv($response->body());

        if ($rows === []) {
            Log::warning('GENESIS-Tabelle ohne auswertbare Zeilen.', [
                'connector' => $this->key(),
                'table' => $table['code'],
            ]);

            return collect();
        }

        return $this->genesisItems($rows, $table);
    }

    /**
     * Aus den Rohzeilen werden je Region die jeweils juengsten Werte.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, mixed>  $table
     * @return Collection<int, SourceItemDto>
     */
    private function genesisItems(array $rows, array $table): Collection
    {
        $sourceName = (string) config("content_statistics.endpoints.{$table['endpoint']}.source_name", 'Destatis');
        $retrievedAt = CarbonImmutable::now();
        $latest = [];

        foreach ($rows as $row) {
            $value = $this->numeric($row['value'] ?? null);

            if ($value === null) {
                continue;
            }

            $period = trim((string) ($row['Zeit'] ?? $row['time'] ?? ''));
            $regionCode = $table['area'] === 'state' ? StateCatalog::fromText($this->regionLabel($row)) : null;

            if ($table['area'] === 'state' && $regionCode === null) {
                continue;
            }

            $bucket = (string) $regionCode;

            // Die Datei ist chronologisch; die letzte Zeile je Region gewinnt.
            if (isset($latest[$bucket]) && strcmp($period, $latest[$bucket]['period']) < 0) {
                continue;
            }

            $latest[$bucket] = [
                'value' => $value,
                'period' => $period,
                'unit' => trim((string) ($row['value_unit'] ?? '')) ?: (string) $table['unit'],
                'region_code' => $regionCode,
                'label' => trim((string) ($row['value_variable_label'] ?? $table['name'])),
            ];
        }

        $items = collect();

        foreach ($latest as $entry) {
            $regionCode = $entry['region_code'];
            $regionScope = $regionCode === null ? 'national' : 'state';
            $regionName = $regionCode === null ? 'Deutschland' : (StateCatalog::name($regionCode) ?? $regionCode);

            $statement = strtr((string) $table['statement'], [
                ':value' => $this->format($entry['value']),
                ':unit' => $entry['unit'],
                ':period' => $entry['period'],
                ':region' => $regionName,
            ]);

            $this->facts->write([
                'fact_key' => $this->factKey((string) $table['key'], $regionCode),
                'statement' => $statement,
                'value' => $entry['value'],
                'unit' => $entry['unit'],
                'period' => $entry['period'],
                'region_scope' => $regionScope,
                'region_code' => $regionCode,
                'source_name' => $sourceName,
                // Belegseite fuer den Leser, nicht der REST-Endpunkt.
                'source_url' => $this->publicUrl($table),
                'retrieved_at' => $retrievedAt,
                'valid_days' => (int) $table['valid_days'],
            ]);

            $items->push(new SourceItemDto(
                type: 'statistic',
                title: "{$table['name']} — {$regionName} ({$entry['period']})",
                url: $this->publicUrl($table),
                snippet: $statement,
                regionScope: $regionScope,
                regionCode: $regionCode,
                keywords: [Str::lower((string) $table['key'])],
                signalStrength: (float) $this->option('signal_strength', 0.3),
                publishedAt: null,
                raw: [
                    'table_code' => $table['code'],
                    'value' => $entry['value'],
                    'unit' => $entry['unit'],
                    'period' => $entry['period'],
                    'fact_key' => $this->factKey((string) $table['key'], $regionCode),
                ],
                externalId: "genesis:{$table['code']}:".($regionCode ?? 'DE'),
                // Ein neuer Berichtszeitraum ist ein neues Signal, ein
                // erneuter Abruf desselben Zeitraums nicht.
                fingerprintSeed: "genesis|{$table['code']}|".($regionCode ?? 'DE')."|{$entry['period']}",
            ));
        }

        return $items;
    }

    /**
     * ffcsv ist semikolongetrennt mit Kopfzeile. Die Spaltennamen wechseln je
     * Tabelle, verlaesslich sind nur 'Zeit', 'value' und 'value_unit'.
     *
     * @return array<int, array<string, string>>
     */
    private function parseFfcsv(string $body): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];

        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines), ';', '"', '\\');
        $rows = [];
        $maxRows = max(1, (int) $this->option('max_rows_per_table', 5000));

        foreach ($lines as $line) {
            if (trim($line) === '' || count($rows) >= $maxRows) {
                continue;
            }

            $values = str_getcsv($line, ';', '"', '\\');

            if (count($values) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $values);
        }

        return $rows;
    }

    /**
     * Regionalstatistik-Tabellen fuehren das Gebiet je nach Tabelle unter
     * unterschiedlichen Spalten; gesucht wird in allen Label-Spalten.
     *
     * @param  array<string, string>  $row
     */
    private function regionLabel(array $row): string
    {
        $labels = [];

        foreach ($row as $column => $value) {
            if (str_ends_with($column, '_Label') || $column === 'Gebiet') {
                $labels[] = $value;
            }
        }

        return implode(' ', $labels);
    }

    /*
    |--------------------------------------------------------------------------
    | DWD-Regionalmittel
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, SourceItemDto>
     */
    private function readDwd(TenantContext $context): Collection
    {
        $items = collect();
        $monthsAhead = max(1, (int) config('content_statistics.dwd.months_ahead', 2));
        $now = CarbonImmutable::now();

        foreach ((array) config('content_statistics.dwd.datasets', []) as $dataset) {
            for ($offset = 0; $offset < $monthsAhead; $offset++) {
                $month = $now->addMonths($offset);

                try {
                    $items = $items->merge($this->readDwdMonth((array) $dataset, $month, $context));
                } catch (Throwable $exception) {
                    Log::warning('DWD-Abruf gescheitert.', [
                        'connector' => $this->key(),
                        'dataset' => $dataset['key'] ?? '?',
                        'month' => $month->format('m'),
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return Collection<int, SourceItemDto>
     */
    private function readDwdMonth(array $dataset, CarbonImmutable $month, TenantContext $context): Collection
    {
        $url = str_replace(':month', $month->format('m'), (string) $dataset['url']);
        $response = $this->get($url, $context);

        if ($response === null) {
            return collect();
        }

        $averages = $this->dwdAverages($response->body());

        if ($averages === []) {
            return collect();
        }

        $retrievedAt = CarbonImmutable::now();
        $monthName = $month->locale('de')->translatedFormat('F');
        $years = (int) config('content_statistics.dwd.reference_years', 30);
        $items = collect();

        // Nur die Laender, die der Mandant bespielt, plus Deutschland.
        $wanted = array_merge([null], $context->allowsRegionScope('state') ? $context->preferredStates() : []);

        foreach ($averages as $iso => $value) {
            $regionCode = $iso === 'DE' ? null : $iso;

            if (! in_array($regionCode, $wanted, true)) {
                continue;
            }

            $regionName = $regionCode === null ? 'Deutschland' : (StateCatalog::name($regionCode) ?? $regionCode);

            $statement = strtr((string) $dataset['statement'], [
                ':value' => $this->format($value),
                ':unit' => (string) $dataset['unit'],
                ':region' => $regionName,
                ':month_name' => $monthName,
                ':years' => (string) $years,
            ]);

            $factKey = $this->factKey((string) $dataset['key'].'-'.$month->format('m'), $regionCode);

            $this->facts->write([
                'fact_key' => $factKey,
                'statement' => $statement,
                'value' => $value,
                'unit' => (string) $dataset['unit'],
                'period' => "Monatsmittel {$monthName}",
                'region_scope' => $regionCode === null ? 'national' : 'state',
                'region_code' => $regionCode,
                'source_name' => (string) $dataset['name'],
                'source_url' => $url,
                'retrieved_at' => $retrievedAt,
                'valid_days' => (int) $dataset['valid_days'],
            ]);

            $items->push(new SourceItemDto(
                type: 'statistic',
                title: "{$dataset['name']} — {$regionName}, {$monthName}",
                url: $url,
                snippet: $statement,
                regionScope: $regionCode === null ? 'national' : 'state',
                regionCode: $regionCode,
                keywords: ['saison', mb_strtolower($monthName)],
                signalStrength: (float) $this->option('signal_strength', 0.3),
                raw: ['value' => $value, 'unit' => $dataset['unit'], 'month' => $month->format('m'), 'fact_key' => $factKey],
                externalId: "dwd:{$dataset['key']}:{$month->format('m')}:".($regionCode ?? 'DE'),
                fingerprintSeed: "dwd|{$dataset['key']}|{$month->format('m')}|".($regionCode ?? 'DE'),
            ));
        }

        return $items;
    }

    /**
     * Die DWD-Regionalmittel liegen als semikolongetrennte Datei mit einer
     * Spalte je Gebiet vor. Gebildet wird das Mittel der letzten
     * reference_years Jahre — der Einzelwert eines Jahres waere Wetter, das
     * Mittel ist Klima und damit als Saisonaussage belastbar.
     *
     * @return array<string, float> ISO-Code (oder 'DE') => Mittelwert
     */
    private function dwdAverages(string $body): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];
        $header = null;
        $sums = [];
        $counts = [];
        $minYear = (int) date('Y') - max(1, (int) config('content_statistics.dwd.reference_years', 30));

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = array_map('trim', explode(';', $line));

            if ($header === null) {
                // Die Kopfzeile ist die erste, die mit 'Jahr' beginnt.
                if (strcasecmp($columns[0], 'Jahr') === 0) {
                    $header = $columns;
                }

                continue;
            }

            $year = (int) $columns[0];

            if ($year < $minYear) {
                continue;
            }

            foreach ($columns as $index => $value) {
                $iso = $this->dwdColumnIso($header[$index] ?? '');
                $number = $this->numeric($value);

                if ($iso === null || $number === null) {
                    continue;
                }

                $sums[$iso] = ($sums[$iso] ?? 0.0) + $number;
                $counts[$iso] = ($counts[$iso] ?? 0) + 1;
            }
        }

        $averages = [];

        foreach ($sums as $iso => $sum) {
            $averages[$iso] = round($sum / max(1, $counts[$iso]), 1);
        }

        return $averages;
    }

    /**
     * Spaltenkoepfe des DWD sind Gebietsnamen. Zusammengefasste Gebiete
     * ("Niedersachsen/Hamburg/Bremen") werden uebersprungen, weil sie sich
     * keinem einzelnen Bundesland zuordnen lassen.
     */
    private function dwdColumnIso(string $column): ?string
    {
        $column = trim($column);

        if ($column === '' || in_array(strtolower($column), ['jahr', 'monat'], true)) {
            return null;
        }

        if (strcasecmp($column, 'Deutschland') === 0) {
            return 'DE';
        }

        if (str_contains($column, '/')) {
            return null;
        }

        return StateCatalog::fromText($column);
    }

    /*
    |--------------------------------------------------------------------------
    | Gemeinsames
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function genesisTables(TenantContext $context): array
    {
        $branch = $context->branch();

        $tables = array_merge(
            (array) config('content_statistics.genesis', []),
            $branch !== null ? (array) config("content_statistics.genesis_branches.{$branch}", []) : [],
        );

        $normalized = [];

        foreach ($tables as $table) {
            $table = (array) $table;

            if (trim((string) ($table['code'] ?? '')) === '' || trim((string) ($table['key'] ?? '')) === '') {
                continue;
            }

            if (($table['area'] ?? 'national') === 'state' && ! $context->allowsRegionScope('state')) {
                continue;
            }

            $normalized[] = array_merge([
                'endpoint' => 'destatis',
                'unit' => '',
                'area' => 'national',
                'valid_days' => 180,
                'name' => $table['key'],
                'statement' => ':value :unit (:period)',
            ], $table);
        }

        return $normalized;
    }

    private function factKey(string $key, ?string $regionCode): string
    {
        return 'stat.'.Str::slug($key).'.'.strtolower($regionCode ?? 'de');
    }

    /**
     * GENESIS liefert deutsch formatiert ("1.234,5"), der DWD englisch
     * ("-1.5"). Unterschieden wird am Komma: ohne Komma bleibt der Punkt das
     * Dezimaltrennzeichen. Sonderzeichen wie '-' oder '...' stehen in beiden
     * Quellen fuer "kein Wert".
     */
    private function numeric(mixed $value): ?float
    {
        if (! is_string($value)) {
            return is_numeric($value) ? (float) $value : null;
        }

        $clean = str_replace([' ', "\u{00a0}"], '', trim($value));

        if (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', str_replace('.', '', $clean));
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function format(float $value): string
    {
        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0, ',', '.')
            : number_format($value, 1, ',', '.');
    }

    /**
     * Menschenlesbare Belegseite statt des REST-Endpunkts.
     *
     * @param  array<string, mixed>  $table
     */
    private function publicUrl(array $table): string
    {
        $template = $table['endpoint'] === 'regionalstatistik'
            ? 'https://www.regionalstatistik.de/genesis/online?operation=table&code=:code'
            : 'https://www-genesis.destatis.de/datenbank/online/statistic/:code';

        return str_replace(':code', rawurlencode((string) $table['code']), $template);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.statistics.{$key}", $default);
    }
}
