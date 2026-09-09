<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prueft, ob die im Verlauf offengelegten Google-Places-Schluessel wirklich
 * geloescht sind (#88 / #97).
 *
 * Der haeufigste Irrtum bei dieser Rotation ist die Antwort
 * "You must enable Billing": sie sieht nach einer Sperre aus, ist aber keine.
 * Ein solcher Schluessel ist gueltig und funktioniert wieder, sobald jemand
 * auf seinem Projekt die Abrechnung einschaltet. Dasselbe gilt fuer
 * "Google has disabled the use of APIs from this API project" — auch das
 * betrifft das Projekt, nicht den Schluessel. Nur "API key is invalid" bzw.
 * "API key not valid" belegt, dass der Schluessel weg ist.
 *
 * Der Befehl liest die alten Werte selbst aus der Versionsgeschichte, damit
 * niemand einen kompromittierten Schluessel von Hand kopieren und dabei
 * erneut irgendwo ablegen muss. Ausgegeben wird nur der gekuerzte Wert.
 *
 *   php artisan places:keys:audit
 *   php artisan places:keys:audit --json
 *   php artisan places:keys:audit --markdown
 *
 * Rueckgabe 0 nur dann, wenn jeder Verlaufsschluessel als geloescht antwortet.
 * Die Schritte in der Konsole stehen in
 * docs/messungen/places-key-rotation-anleitung.md.
 */
class PlacesKeysAudit extends Command
{
    private const OK = 'geloescht';

    private const ALIVE = 'lebt';

    private const UNKLAR = 'unklar';

    /** Datei, die den Schluessel im Verlauf getragen hat. */
    private const HISTORY_FILE = 'app/Console/Commands/GetCompanies.php';

    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/place/textsearch/json';

    protected $signature = 'places:keys:audit
        {--json : Ergebnis als JSON statt als Tabelle}
        {--markdown : Zeilen fuer die Protokolltabelle der Anleitung ausgeben}';

    protected $description = 'Prueft, ob die im Git-Verlauf offengelegten Places-Schluessel geloescht sind';

    public function handle(): int
    {
        $keys = $this->historyKeys();

        if ($keys === []) {
            $this->components->error('Kein Schluessel im Verlauf gefunden — pruefe, ob '.self::HISTORY_FILE.' noch existiert.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($keys as $commit => $key) {
            $rows[] = $this->probe($key, $commit) + ['commit' => $commit];
        }

        $envKey = (string) config('services.google.places_api_key', '');
        $envRow = $envKey === ''
            ? ['commit' => '.env', 'key' => '(leer)', 'verdikt' => self::UNKLAR, 'status' => '-', 'meldung' => 'GOOGLE_PLACES_API_KEY ist nicht gesetzt']
            : $this->probe($envKey, '.env') + ['commit' => '.env'];

        $offen = array_values(array_filter($rows, fn (array $row): bool => $row['verdikt'] !== self::OK));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'verlauf' => $rows,
                'env' => $envRow,
                'offen' => count($offen),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $offen === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('markdown')) {
            $this->line('| Commit | Schluessel | Antwort | Verdikt |');
            $this->line('|---|---|---|---|');

            foreach ([...$rows, $envRow] as $row) {
                $this->line(sprintf('| `%s` | `%s` | %s | %s |', $row['commit'], $row['key'], $row['meldung'], $row['verdikt']));
            }

            return $offen === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Commit', 'Schluessel', 'Antwort', 'Verdikt'],
            array_map(
                fn (array $row): array => [$row['commit'], $row['key'], Str::limit($row['meldung'], 70), $row['verdikt']],
                [...$rows, $envRow],
            ),
        );

        if ($offen === []) {
            $this->components->info('Alle '.count($rows).' Verlaufsschluessel sind geloescht.');

            return self::SUCCESS;
        }

        $this->components->error(count($offen).' von '.count($rows).' Verlaufsschluesseln leben weiter — Abschnitt 2 der Anleitung ist offen.');
        $this->line('  docs/messungen/places-key-rotation-anleitung.md');

        return self::FAILURE;
    }

    /**
     * Alle Schluessel, die je in der Datei standen, je Commit einer.
     *
     * @return array<string, string> Commit (kurz) => Schluessel
     */
    private function historyKeys(): array
    {
        $log = Process::run(['git', 'log', '--all', '-G', 'AIzaSy', '--format=%H', '--', self::HISTORY_FILE]);

        if (! $log->successful()) {
            return [];
        }

        $keys = [];

        foreach (preg_split('/\R/', trim($log->output()), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $commit) {
            $file = Process::run(['git', 'show', $commit.':'.self::HISTORY_FILE]);

            if (! $file->successful()) {
                continue;
            }

            if (preg_match('/AIzaSy[A-Za-z0-9_-]{20,}/', $file->output(), $treffer) !== 1) {
                continue;
            }

            // Derselbe Schluessel steht in mehreren Commits; gefuehrt wird der
            // aelteste, in dem er auftaucht.
            $keys[substr($commit, 0, 7)] = $treffer[0];
        }

        return array_reverse(array_unique($keys));
    }

    /**
     * @return array{key: string, verdikt: string, status: string, meldung: string}
     */
    private function probe(string $key, string $label): array
    {
        try {
            $antwort = Http::timeout(20)->get(self::ENDPOINT, ['query' => 'Test', 'key' => $key])->json();
        } catch (Throwable $e) {
            return [
                'key' => $this->maskiert($key),
                'verdikt' => self::UNKLAR,
                'status' => '-',
                'meldung' => 'Aufruf fehlgeschlagen: '.$e->getMessage(),
            ];
        }

        $status = (string) ($antwort['status'] ?? '-');
        $meldung = (string) ($antwort['error_message'] ?? '');

        return [
            'key' => $this->maskiert($key),
            'verdikt' => $this->verdikt($status, $meldung, $label),
            'status' => $status,
            'meldung' => $meldung !== '' ? $meldung : $status,
        ];
    }

    /**
     * Fuer die .env-Zeile ist die Frage umgekehrt: dort ist ein lebender
     * Schluessel das Ziel, im Verlauf ein geloeschter.
     */
    private function verdikt(string $status, string $meldung, string $label): string
    {
        $geloescht = Str::contains($meldung, ['API key is invalid', 'API key not valid', 'API key is expired'], true);
        $lebt = in_array($status, ['OK', 'ZERO_RESULTS', 'INVALID_REQUEST', 'OVER_QUERY_LIMIT'], true)
            || Str::contains($meldung, ['enable Billing', 'Google has disabled the use of APIs'], true);

        if ($label === '.env') {
            return match (true) {
                in_array($status, ['OK', 'ZERO_RESULTS'], true) => 'nutzbar',
                default => 'nicht nutzbar',
            };
        }

        return match (true) {
            $geloescht => self::OK,
            $lebt => self::ALIVE,
            default => self::UNKLAR,
        };
    }

    private function maskiert(string $key): string
    {
        return strlen($key) > 15
            ? substr($key, 0, 10).'…'.substr($key, -5)
            : '(zu kurz)';
    }
}
