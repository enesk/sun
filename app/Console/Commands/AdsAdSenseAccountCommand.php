<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Models\AdSetting;
use App\Ads\Support\AdSensePublisherId;
use App\Content\Providers\AdSenseApiException;
use App\Content\Providers\AdSenseClient;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Gegenprobe und Abfrage des AdSense-Zugangs ueber die Management API v2
 * (Ticket #55, pilot-protokoll.md §6.2 und §6.4).
 *
 * Zwei der vier Punkte der Kontositzung sind keine Klickarbeit, sobald das
 * Dienstkonto lesen darf: der Report je Anzeigenblock (§6.2) und der
 * Site-Status der Portale (§6.4). Dieser Befehl ist beides — die Abnahmeprobe
 * fuer die Freischaltung und danach das Werkzeug, mit dem die zwei Punkte
 * wiederholbar abgelesen werden, statt jede Domain im Konto aufzuschlagen.
 *
 * Reiner Lesezugriff: kein Schalter, keine Datenbankaenderung. Geschrieben
 * wird nur, was der AdSenseClient ohnehin protokolliert (llm_usage_logs beim
 * pageReport — der hier nicht laeuft).
 *
 *   php artisan ads:adsense-konto                    # Zugang + Site-Status
 *   php artisan ads:adsense-konto --report            # zusaetzlich §6.2
 *   php artisan ads:adsense-konto --report --ab=2026-10-16
 *   php artisan ads:adsense-konto --json              # maschinenlesbar
 *
 * Exit-Codes:
 *   0   Zugang steht, alle erwarteten Konten lesbar
 *   65  kein Dienstkonto-Schluessel lesbar (GOOGLE_SERVICE_ACCOUNT_JSON)
 *   70  AdSense Management API im Cloud-Projekt nicht freigeschaltet (#55/1)
 *   71  Token gilt, aber ein erwartetes Konto fehlt dem Dienstkonto (#55/2)
 *   1   anderer Fehler (Netz, Zeitueberschreitung, unerwartete Antwort)
 */
class AdsAdSenseAccountCommand extends Command
{
    protected $signature = 'ads:adsense-konto
        {--report : Report je Anzeigenblock ausgeben (§6.2)}
        {--ab= : Beginn des Report-Zeitraums (JJJJ-MM-TT)}
        {--bis= : Ende des Report-Zeitraums (JJJJ-MM-TT)}
        {--konto=* : Nur diese Konten pruefen (pub-… oder ca-pub-…)}
        {--json : Ergebnis als JSON statt als Tabelle}';

    protected $description = 'Liest Konten, Site-Status und den Report je Anzeigenblock aus der AdSense Management API (lesend)';

    public function handle(AdSenseClient $client): int
    {
        if (! $client->hasReadableCredentials()) {
            $this->fehler('Kein lesbarer Dienstkonto-Schluessel: GOOGLE_SERVICE_ACCOUNT_JSON bzw. ADSENSE_SERVICE_ACCOUNT_JSON zeigt auf keine lesbare Datei.');

            return 65;
        }

        $erwartet = $this->erwarteteKonten();

        try {
            $konten = $client->accounts();
        } catch (AdSenseApiException $exception) {
            return $this->zugangFehlt($exception, $erwartet);
        } catch (Throwable $exception) {
            $this->fehler($exception->getMessage());

            return 1;
        }

        $ergebnis = [
            'konten' => [],
            'erwartet' => array_keys($erwartet),
            'fehlend' => [],
        ];

        if (! $this->option('json')) {
            $this->info('Dienstkonto sieht '.count($konten).' AdSense-Konto(en):');
        }

        foreach ($konten as $name => $anzeigename) {
            if (! $this->gewaehlt($name)) {
                continue;
            }

            $ergebnis['konten'][$name] = [
                'display_name' => $anzeigename,
                'sites' => [],
                'report' => null,
                'fehler' => null,
            ];

            try {
                $ergebnis['konten'][$name]['sites'] = $client->sites($name);

                if ($this->option('report')) {
                    [$ab, $bis] = $this->zeitraum();
                    $ergebnis['konten'][$name]['report'] = $client->adUnitReport($ab, $bis, $name);
                }
            } catch (Throwable $exception) {
                $ergebnis['konten'][$name]['fehler'] = $exception->getMessage();
            }
        }

        // Jedes Konto, das im Netz eine Publisher-ID stellt, muss das
        // Dienstkonto sehen — sonst bleibt der halbe Bestand unlesbar.
        foreach ($erwartet as $kennung => $portale) {
            $voll = $client->qualify($kennung);

            if (! array_key_exists($voll, $konten)) {
                $ergebnis['fehlend'][$kennung] = $portale;
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($ergebnis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->bericht($ergebnis, $erwartet);
        }

        if ($ergebnis['fehlend'] !== []) {
            return 71;
        }

        foreach ($ergebnis['konten'] as $konto) {
            if ($konto['fehler'] !== null) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Konten, die das Netz benutzt: jede Publisher-ID, die an einem Portal
     * oder als Netz-Vorgabe hinterlegt ist, mit den Portalen dahinter.
     *
     * @return array<string, list<string>> 'pub-…' => Domains
     */
    private function erwarteteKonten(): array
    {
        $konten = [];

        try {
            $domains = Tenant::query()->pluck('domain', 'id');

            foreach (AdSetting::query()->get() as $setting) {
                $kennung = AdSensePublisherId::forAdsTxt($setting->adsensePublisherId());

                if ($kennung === null) {
                    continue;
                }

                $domain = (string) ($domains[$setting->tenant_id] ?? ('Tenant '.$setting->tenant_id));
                $konten[$kennung][] = $setting->ads_enabled ? $domain : $domain.' (aus)';
            }
        } catch (Throwable $exception) {
            $this->warn('Central-DB nicht lesbar, Soll-Abgleich entfaellt: '.$exception->getMessage());
        }

        $vorgabe = AdSensePublisherId::forAdsTxt((string) config('ad-slots.adsense.publisher_id'));

        if ($vorgabe !== null && ! array_key_exists($vorgabe, $konten)) {
            $konten[$vorgabe] = ['(Netz-Vorgabe ADSENSE_PUBLISHER_ID)'];
        }

        ksort($konten);

        return $konten;
    }

    private function gewaehlt(string $name): bool
    {
        /** @var list<string> $wahl */
        $wahl = (array) $this->option('konto');

        if ($wahl === []) {
            return true;
        }

        foreach ($wahl as $kennung) {
            $kennung = AdSensePublisherId::forAdsTxt($kennung) ?? trim($kennung);

            if ($kennung !== '' && str_contains($name, $kennung)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report-Zeitraum. Vorgabe: die sieben vollen Tage vor dem Nachlauf
     * (content.adsense.lag_days) — AdSense meldet den Vortag erst
     * vollstaendig, wenn er abgerechnet ist.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function zeitraum(): array
    {
        $lag = max(0, (int) config('content.adsense.lag_days', 2));
        $bis = $this->option('bis') !== null && $this->option('bis') !== ''
            ? CarbonImmutable::parse((string) $this->option('bis'))
            : CarbonImmutable::today()->subDays($lag);

        $ab = $this->option('ab') !== null && $this->option('ab') !== ''
            ? CarbonImmutable::parse((string) $this->option('ab'))
            : $bis->subDays(6);

        return [$ab, $bis];
    }

    /**
     * @param  array<string, mixed>  $ergebnis
     * @param  array<string, list<string>>  $erwartet
     */
    private function bericht(array $ergebnis, array $erwartet): void
    {
        /** @var array<string, array<string, mixed>> $konten */
        $konten = $ergebnis['konten'];

        foreach ($konten as $name => $konto) {
            $this->newLine();
            $this->line("<info>{$name}</info> — {$konto['display_name']}");

            if ($konto['fehler'] !== null) {
                $this->error('  '.$konto['fehler']);

                continue;
            }

            /** @var array<string, array{domain: string, state: string, auto_ads: bool}> $sites */
            $sites = $konto['sites'];

            if ($sites === []) {
                $this->warn('  Keine Sites im Konto.');
            } else {
                $this->line('  Sites (§6.4):');
                $this->table(
                    ['Domain', 'state', 'Auto Ads'],
                    array_map(
                        fn (array $site): array => [
                            $site['domain'],
                            $site['state'],
                            $site['auto_ads'] ? 'JA' : 'nein',
                        ],
                        array_values($sites),
                    ),
                );
            }

            /** @var list<array<string, mixed>>|null $report */
            $report = $konto['report'];

            if ($report === null) {
                continue;
            }

            [$ab, $bis] = $this->zeitraum();
            $this->line('  Report je Anzeigenblock (§6.2), '.$ab->toDateString().' bis '.$bis->toDateString().':');

            if ($report === []) {
                $this->warn('  Keine Zeilen im Zeitraum.');

                continue;
            }

            $this->table(
                ['Anzeigenblock', 'Impr.', 'Klicks', 'CTR', 'RPM', 'Viewability', 'Betrachtung s', 'Ertrag'],
                array_map(
                    fn (array $row): array => [
                        $row['ad_unit'],
                        (string) $row['impressions'],
                        (string) $row['clicks'],
                        $this->prozent((float) $row['ctr']),
                        number_format((float) $row['rpm'], 2, ',', '.'),
                        $this->prozent((float) $row['viewability']),
                        number_format((float) $row['active_view_time'], 1, ',', '.'),
                        number_format((float) $row['earnings'], 2, ',', '.'),
                    ],
                    $report,
                ),
            );
        }

        /** @var array<string, list<string>> $fehlend */
        $fehlend = $ergebnis['fehlend'];

        $this->newLine();

        if ($fehlend === []) {
            $this->info('Alle '.count($erwartet).' im Netz benutzten Konten sind lesbar.');

            return;
        }

        $this->error('Dem Dienstkonto fehlt der Zugriff auf '.count($fehlend).' Konto/Konten:');

        foreach ($fehlend as $kennung => $portale) {
            $this->line("  {$kennung} — ".implode(', ', $portale));
        }

        $this->line('  Handgriff: AdSense > Konto > Zugriff und Autorisierung > Nutzer: das Dienstkonto als Nutzer eintragen (#55, Schritt 2).');
    }

    /**
     * AdSense liefert Quoten als Anteil (0.42), nicht als Prozentwert.
     */
    private function prozent(float $wert): string
    {
        return number_format($wert * 100, 1, ',', '.').' %';
    }

    /**
     * @param  array<string, list<string>>  $erwartet
     */
    private function zugangFehlt(AdSenseApiException $exception, array $erwartet): int
    {
        if ($exception->apiDisabled()) {
            $this->fehler($exception->getMessage());
            $this->newLine();
            $this->line('Das Token wird ausgestellt und akzeptiert — die AdSense Management API ist im');
            $this->line('Cloud-Projekt des Dienstkontos nicht freigeschaltet (#55, Schritt 1):');
            $this->line('  Google Cloud Console > Projekt des Dienstkontos > APIs & Dienste');
            $this->line('  > AdSense Management API > Aktivieren. Danach diesen Befehl erneut.');

            return 70;
        }

        $this->fehler($exception->getMessage());
        $this->newLine();
        $this->line('Token gilt, der Zugriff fehlt (#55, Schritt 2): das Dienstkonto als Nutzer in');
        $this->line('AdSense > Konto > Zugriff und Autorisierung eintragen — in beiden Konten:');

        foreach (array_keys($erwartet) as $kennung) {
            $this->line("  {$kennung}");
        }

        return 71;
    }

    private function fehler(string $meldung): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['fehler' => $meldung], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->error($meldung);
    }
}
