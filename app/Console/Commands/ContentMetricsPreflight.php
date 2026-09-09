<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Providers\AdSenseClient;
use App\Content\Providers\SearchConsoleClient;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vorabpruefung der Metrik-Abnahme (#90).
 *
 * Der Go-Live-Check (#26) sieht nur, ob eine Schluesseldatei da ist und ob
 * eine gsc_property eingetragen wurde. Genau der haeufigste Fehlerfall bleibt
 * dabei unsichtbar: ein Dienstkonto, das in der Property nicht als Nutzer
 * steht, bekommt von der Search Console keine Fehlermeldung, sondern eine
 * leere Antwort. Der Abnahmelauf laeuft dann durch und schreibt nichts, und
 * niemand kann unterscheiden, ob die Freigabe fehlt oder ob die Property nur
 * noch keine Daten hat.
 *
 * Dieser Befehl stellt genau diese Frage vor dem Lauf, mit echten Aufrufen:
 * sites.list fuer die Search Console, accounts.list fuer AdSense, dazu je
 * Portal eine Stichprobe ueber das Fenster des Collectors.
 *
 *   php artisan content:metrics:preflight
 *   php artisan content:metrics:preflight --tenant=<uuid>
 *   php artisan content:metrics:preflight --offline
 *
 * Ablauf und Konten-Schritte stehen in
 * docs/messungen/metrik-abnahme-zugaenge.md.
 */
class ContentMetricsPreflight extends Command
{
    private const OK = 'ok';

    private const WARN = 'warnung';

    private const FAIL = 'fehler';

    protected $signature = 'content:metrics:preflight
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--offline : Nur Konfiguration pruefen, keine Aufrufe bei Google}';

    protected $description = 'Prueft vor der Metrik-Abnahme, ob Dienstkonto, Property-Freigabe und AdSense-Zugang wirklich tragen';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(SearchConsoleClient $searchConsole, AdSenseClient $adSense, TenantRollout $rollout): int
    {
        // Artisan::call kann denselben Befehl mehrfach ausfuehren; ohne das
        // Zuruecksetzen stuenden die Zeilen des vorigen Laufs noch da.
        $this->rows = [];

        $this->switches();

        $keyReadable = $this->serviceAccount($searchConsole);
        $offline = (bool) $this->option('offline');

        $sites = $keyReadable && ! $offline ? $this->sites($searchConsole) : null;

        $this->portals($searchConsole, $rollout, $sites, $offline);
        $this->adSense($adSense, $offline);

        $this->table(['Zustand', 'Prüfpunkt', 'Befund'], array_map(
            static fn (array $row): array => [
                match ($row[0]) {
                    self::OK => '✓',
                    self::WARN => '!',
                    default => '✗',
                },
                $row[1],
                $row[2],
            ],
            $this->rows,
        ));

        $failed = count(array_filter($this->rows, static fn (array $row): bool => $row[0] === self::FAIL));
        $warned = count(array_filter($this->rows, static fn (array $row): bool => $row[0] === self::WARN));

        $this->info(sprintf('%d Prüfpunkte, %d Fehler, %d Warnungen.', count($this->rows), $failed, $warned));

        if ($failed === 0) {
            $this->line('Abnahmelauf: siehe Abschnitt 5 in docs/messungen/metrik-abnahme-zugaenge.md.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Die drei Schalter, ohne die der Abnahmelauf aussteigt statt zu
     * arbeiten. Der Gap-Connector ist ohne seinen Schalter gar nicht
     * registriert; content:sources:run meldet dann nur
     * "Unbekannter Quell-Connector 'gsc_gap'".
     */
    private function switches(): void
    {
        $this->check(
            'Pipeline eingeschaltet',
            (bool) config('content.enabled'),
            'content.enabled ist true',
            'CONTENT_PIPELINE_ENABLED=true setzen — sonst steigen alle drei Befehle wirkungslos aus',
        );

        $this->check(
            'Gap-Connector registriert',
            (bool) config('content.sources.gsc_gap.enabled'),
            'content.sources.gsc_gap.enabled ist true',
            "CONTENT_SOURCES_GSC_ENABLED=true setzen — sonst meldet content:sources:run \"Unbekannter Quell-Connector 'gsc_gap'\"",
        );

        $this->check(
            'AdSense eingeschaltet',
            (bool) config('content.adsense.enabled'),
            'content.adsense.enabled ist true',
            'CONTENT_ADSENSE_ENABLED ist aus — pageviews und adsense_revenue_usd bleiben null (Ausfallweg, kein Fehler)',
            self::WARN,
        );
    }

    /**
     * Schluesseldatei: gesetzt, lesbar, ausserhalb von public/, mit
     * client_email. Die Adresse wird ausgegeben, weil genau sie in Search
     * Console und AdSense als Nutzer eingetragen werden muss.
     */
    private function serviceAccount(SearchConsoleClient $client): bool
    {
        if (! $client->isConfigured()) {
            $this->row(
                self::FAIL,
                'Dienstkonto',
                'GOOGLE_SERVICE_ACCOUNT_JSON fehlt oder die Datei ist nicht lesbar',
            );

            return false;
        }

        try {
            $email = $client->serviceAccountEmail();
        } catch (Throwable $exception) {
            $this->row(self::FAIL, 'Dienstkonto', $this->reason($exception));

            return false;
        }

        $this->row(self::OK, 'Dienstkonto', $email);

        return true;
    }

    /**
     * sites.list: die Properties, die das Dienstkonto sehen darf. Schlaegt
     * der Aufruf mit 403 accessNotConfigured fehl, ist die Search Console API
     * im Cloud-Projekt nicht aktiviert — ein anderes Bild als die fehlende
     * Freigabe in der Property, die still leer antwortet.
     *
     * @return array<string, string>|null null bedeutet: nicht ermittelbar
     */
    private function sites(SearchConsoleClient $client): ?array
    {
        try {
            $sites = $client->sites();
        } catch (Throwable $exception) {
            $this->row(self::FAIL, 'Sichtbare Properties', $this->reason($exception));

            return null;
        }

        if ($sites === []) {
            $this->row(
                self::FAIL,
                'Sichtbare Properties',
                'Das Dienstkonto sieht keine einzige Property — in der Search Console als Nutzer eintragen',
            );

            return $sites;
        }

        $this->row(self::OK, 'Sichtbare Properties', count($sites).': '.Str::limit(implode(', ', array_keys($sites)), 120));

        return $sites;
    }

    /**
     * Je Portal: Property gepflegt, Property freigegeben, Daten vorhanden.
     *
     * @param  array<string, string>|null  $sites
     */
    private function portals(SearchConsoleClient $client, TenantRollout $rollout, ?array $sites, bool $offline): void
    {
        $rows = $this->filtered($rollout->status());

        if ($rows === []) {
            $this->row(self::FAIL, 'Portale', 'Keine Mandanten gefunden.');

            return;
        }

        foreach ($rows as $row) {
            /** @var Tenant $tenant */
            $tenant = $row['tenant'];
            $label = 'Portal '.$tenant->name;
            $property = trim((string) ($row['gsc_property'] ?? ''));

            if ($property === '') {
                $this->row(self::WARN, $label, 'Keine gsc_property gepflegt — dieses Portal liefert keine Metriken');

                continue;
            }

            if (str_ends_with($property, '.test') || str_contains($property, '.test/')) {
                $this->row(self::FAIL, $label, "{$property} ist eine Testdomain — für die Abnahme braucht es eine echte Property");

                continue;
            }

            if ($sites !== null && ! isset($sites[$property])) {
                $this->row(
                    self::FAIL,
                    $label,
                    "{$property} ist für das Dienstkonto nicht sichtbar — dort als Nutzer eintragen (sonst antwortet die API still leer)",
                );

                continue;
            }

            if ($offline || $sites === null) {
                $this->row(self::OK, $label, $property);

                continue;
            }

            $this->row(...$this->probe($client, $property, $sites[$property] ?? 'unbekannt', $label));
        }
    }

    /**
     * Stichprobe ueber dasselbe Fenster, das der Gap-Connector liest. Eine
     * frisch verifizierte Property antwortet mit dataState 'final' und drei
     * Tagen Verzoegerung erst nach einigen Tagen — leer ist deshalb eine
     * Warnung, kein Fehler.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function probe(SearchConsoleClient $client, string $property, string $permission, string $label): array
    {
        $lag = (int) config('content.providers.search_console.lag_days', 3);
        $lookback = (int) config('content.providers.search_console.lookback_days', 28);
        $end = CarbonImmutable::today()->subDays($lag);
        $start = $end->subDays($lookback);

        try {
            $rows = $client->query($property, [
                'startDate' => $start->toDateString(),
                'endDate' => $end->toDateString(),
                'dimensions' => ['page'],
                'rowLimit' => 5,
                'dataState' => (string) config('content.providers.search_console.data_state', 'final'),
                'type' => 'web',
            ]);
        } catch (Throwable $exception) {
            return [self::FAIL, $label, $property.': '.$this->reason($exception)];
        }

        $window = $start->toDateString().' bis '.$end->toDateString();

        if ($rows === []) {
            return [
                self::WARN,
                $label,
                "{$property} ({$permission}) antwortet für {$window} mit 0 Zeilen — junge Property oder noch keine Impressionen",
            ];
        }

        $impressions = array_sum(array_map(static fn (array $row): int => (int) ($row['impressions'] ?? 0), $rows));

        return [
            self::OK,
            $label,
            "{$property} ({$permission}): Daten vorhanden, {$impressions} Impressionen in den Top-Seiten von {$window}",
        ];
    }

    /**
     * AdSense darf fehlen, ohne die Abnahme zu stoppen: dann bleiben
     * pageviews und adsense_revenue_usd null. Deshalb nie FAIL.
     */
    private function adSense(AdSenseClient $client, bool $offline): void
    {
        $account = $client->account();

        if ($account === null) {
            $this->row(self::WARN, 'AdSense-Konto', 'ADSENSE_ACCOUNT_ID fehlt (Form: accounts/pub-…)');

            return;
        }

        if ($offline) {
            $this->row(self::OK, 'AdSense-Konto', $account.' (nicht geprüft, --offline)');

            return;
        }

        try {
            $accounts = $client->accounts();
        } catch (Throwable $exception) {
            $this->row(self::WARN, 'AdSense-Konto', $this->reason($exception));

            return;
        }

        if (! isset($accounts[$account])) {
            $this->row(
                self::WARN,
                'AdSense-Konto',
                $account.' ist für das Dienstkonto nicht sichtbar. Sichtbar: '
                    .($accounts === [] ? 'keines' : Str::limit(implode(', ', array_keys($accounts)), 120)),
            );

            return;
        }

        $this->row(self::OK, 'AdSense-Konto', $account.' — '.$accounts[$account]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filtered(array $rows): array
    {
        $needle = $this->option('tenant');

        if ($needle === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            /** @var Tenant $tenant */
            $tenant = $row['tenant'];

            return (string) $tenant->getKey() === (string) $needle
                || (string) $tenant->uuid === (string) $needle
                || (string) $tenant->domain === (string) $needle;
        }));
    }

    private function reason(Throwable $exception): string
    {
        return Str::limit(trim($exception->getMessage()), 180);
    }

    private function check(string $label, bool $passed, string $onSuccess, string $onFailure, string $level = self::FAIL): void
    {
        $this->row($passed ? self::OK : $level, $label, $passed ? $onSuccess : $onFailure);
    }

    private function row(string $state, string $label, string $detail): void
    {
        $this->rows[] = [$state, $label, $detail];
    }
}
