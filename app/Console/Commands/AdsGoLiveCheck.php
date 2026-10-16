<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdSetting;
use App\Ads\Support\AdSensePublisherId;
use App\Ads\Support\AdSlotCatalog;
use App\Ads\Support\AdsTxtBuilder;
use App\Ads\Support\AdThemeContext;
use App\Models\Portal\AdSetting as LegacyAdSetting;
use App\Models\Portal\AdSlot as LegacyAdSlot;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Maschinelle Go-Live-Pruefung des Werbeplatz-Systems (Ticket #11,
 * docs/ad-slots-golive.md).
 *
 * Eine Zeile je Pruefung — OK / WARN / FAIL —, danach eine Tabelle je Portal.
 * Nur Lesezugriff: nichts wird geschaltet, nichts geleert. Exit 1 bei FAIL.
 *
 *   php artisan ads:golive:check
 *   php artisan ads:golive:check --tenant=46   # nur ein Portal
 *
 * Die teuerste Pruefung ist die `ads.txt`: fuer jedes Portal wird der neue
 * Inhalt aus der Central-DB gebaut und mit der alten Datei aus der Tenant-DB
 * (`ad_settings.ads_txt_content`) verglichen. Das ist der Punkt, an dem der
 * Umstieg wehtun kann — verliert eine Domain beim Deploy ihre Google-Zeile,
 * stellt AdSense dort die Auslieferung ein, ohne dass im Frontend etwas
 * auffaellt. Mit `--ohne-alt` bleibt der Vergleich aus (kein Zugriff auf die
 * Tenant-DBs).
 */
class AdsGoLiveCheck extends Command
{
    private const OK = 'OK';

    private const WARN = 'WARN';

    private const FAIL = 'FAIL';

    protected $signature = 'ads:golive:check
        {--tenant= : Nur dieses Portal (ID)}
        {--ohne-alt : Die alte ads.txt der Tenant-DB nicht vergleichen}';

    protected $description = 'Prueft die Go-Live-Vorbedingungen der Werbeplaetze je Portal (Exit 1 bei FAIL)';

    /** @var array<string, int> */
    private array $tally = [self::OK => 0, self::WARN => 0, self::FAIL => 0];

    public function handle(AdsTxtBuilder $adsTxt): int
    {
        $this->checkGlobals();
        $this->newLine();
        $this->checkTenants($adsTxt);

        $fail = $this->tally[self::FAIL];
        $warn = $this->tally[self::WARN];

        $this->newLine();
        $this->line(sprintf(
            'Go-Live-Check Werbeplaetze: %d FAIL, %d WARN — %s',
            $fail,
            $warn,
            $fail > 0 ? 'nicht bereit.' : 'bereit.',
        ));

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Was fuer alle Portale gilt: Schluessel, Editor-Herkunft, CSP, Katalog. */
    private function checkGlobals(): void
    {
        filled(config('app.key'))
            ? $this->result(self::OK, 'APP_KEY (Editor-Token)', 'gesetzt')
            : $this->result(self::FAIL, 'APP_KEY (Editor-Token)', 'fehlt');

        $origin = \App\Ads\EditorToken::origin();

        if ($origin === '') {
            $this->result(self::FAIL, 'Editor-Herkunft (ADS_EDITOR_ORIGIN)', 'keine gueltige Herkunft — die Bruecke laeuft nicht an');
        } elseif (! str_starts_with($origin, 'https://')) {
            $this->result(self::WARN, 'Editor-Herkunft (ADS_EDITOR_ORIGIN)', $origin.' — ohne https nur lokal brauchbar');
        } else {
            $this->result(self::OK, 'Editor-Herkunft (ADS_EDITOR_ORIGIN)', $origin);
        }

        $mode = (string) config('csp.mode');

        match ($mode) {
            'enforce' => $this->result(self::OK, 'CSP-Modus (CSP_MODE)', 'enforce'),
            'report' => $this->result(self::WARN, 'CSP-Modus (CSP_MODE)', 'report — Verstoesse werden gemeldet, nicht verhindert'),
            default => $this->result(self::FAIL, 'CSP-Modus (CSP_MODE)', $mode === '' ? 'nicht gesetzt' : $mode),
        };

        // Die Google-Hosts der Anzeigen muessen in script-src und frame-src
        // stehen, sonst blockiert der Browser den Loader bzw. den Rahmen.
        $this->checkCspHosts('script-src', [
            'https://pagead2.googlesyndication.com',
        ]);
        $this->checkCspHosts('frame-src', [
            'https://googleads.g.doubleclick.net',
            'https://tpc.googlesyndication.com',
        ]);

        $this->checkPanelCache();

        $catalog = AdSlotCatalog::all();
        $themes = AdSlotCatalog::themes();

        $catalog === []
            ? $this->result(self::FAIL, 'Slot-Katalog', 'leer')
            : $this->result(self::OK, 'Slot-Katalog', sprintf(
                'Version %d, %d Anker, Themes: %s',
                AdSlotCatalog::version(),
                count($catalog),
                implode(', ', $themes),
            ));

        $netzId = trim((string) config('ad-slots.adsense.publisher_id'));

        $netzId === ''
            ? $this->result(self::OK, 'Netz-Publisher-ID', 'keine — jedes Portal bringt seine eigene mit')
            : $this->result(
                AdSensePublisherId::isValid($netzId) ? self::OK : self::FAIL,
                'Netz-Publisher-ID',
                AdSensePublisherId::isValid($netzId) ? 'gesetzt und im Format' : 'gesetzt, aber nicht im Format ca-pub-16 Ziffern',
            );
    }

    /**
     * @param  list<string>  $hosts
     */
    /**
     * Der Filament-Komponenten-Cache (Ticket #64).
     *
     * Liegt `bootstrap/cache/filament/panels/*.php`, liest Filament **nur**
     * diese Liste und durchsucht die Verzeichnisse nicht mehr. Eine Panel-Seite,
     * die nach dem letzten `filament:cache-components` dazugekommen ist, ist
     * dann nicht registriert — ihre Route existiert laut `route:list`, aber der
     * Aufruf endet mit **404**. Genau so war die Seite „Werbeplaetze" auf der
     * Produktion unerreichbar, mit einem Cache vom 08.10.2026.
     *
     * Geprueft wird deshalb zweierlei: ist der Cache aelter als der
     * ausgelieferte Codestand, und stehen die Werbe-Klassen darin.
     */
    private function checkPanelCache(): void
    {
        $datei = base_path('bootstrap/cache/filament/panels/admin.php');

        if (! is_file($datei)) {
            // Ohne Cache durchsucht Filament die Verzeichnisse — langsamer,
            // aber immer vollstaendig. Das ist der lokale Normalfall.
            $this->result(self::OK, 'Panel-Komponenten-Cache', 'keiner, Filament durchsucht die Verzeichnisse');

            return;
        }

        $cacheZeit = (int) filemtime($datei);
        $codeZeit = $this->codeZeit();

        // Der Cache ist PHP-Quelltext aus var_export: Klassennamen stehen dort
        // mit doppelten Backslashes ('App\\Filament\\…'). Vor dem Vergleich
        // deshalb auf einfache zusammenziehen, sonst findet str_contains nie
        // etwas und jede Pruefung meldet "fehlt".
        $inhalt = str_replace('\\\\', '\\', (string) file_get_contents($datei));

        $fehlend = array_values(array_filter(
            [
                \App\Filament\Admin\Pages\AdPlacementEditor::class,
                \App\Filament\Admin\Pages\AdSettings::class,
            ],
            fn (string $klasse): bool => ! str_contains($inhalt, $klasse),
        ));

        if ($fehlend !== []) {
            $this->result(self::FAIL, 'Panel-Komponenten-Cache', sprintf(
                'vom %s ohne %s: 404. Behebung: filament:optimize',
                date('d.m.Y H:i', $cacheZeit),
                implode(', ', array_map('class_basename', $fehlend)),
            ));

            return;
        }

        if ($codeZeit !== null && $cacheZeit < $codeZeit) {
            $this->result(self::WARN, 'Panel-Komponenten-Cache', sprintf(
                'vom %s, aelter als der Code (%s): filament:optimize',
                date('d.m.Y H:i', $cacheZeit),
                date('d.m.Y H:i', $codeZeit),
            ));

            return;
        }

        $this->result(self::OK, 'Panel-Komponenten-Cache', sprintf(
            'vom %s, Werbe-Seiten drin',
            date('d.m.Y H:i', $cacheZeit),
        ));
    }

    /**
     * Zeitstempel des ausgelieferten Codestands. Ohne Git-Verzeichnis (oder
     * ohne `git` im Pfad) gibt es keinen Vergleich — dann prueft nur die Liste
     * der Klassen.
     */
    private function codeZeit(): ?int
    {
        $head = base_path('.git/HEAD');

        if (! is_file($head)) {
            return null;
        }

        // Nicht `git log` aufrufen: der Befehl steht in einem Artisan-Lauf
        // nicht zwingend zur Verfuegung. Der Zeitstempel von .git/HEAD
        // bewegt sich bei jedem Pull und Checkout — das genuegt hier.
        $zeit = (int) filemtime($head);

        return $zeit > 0 ? $zeit : null;
    }

    private function checkCspHosts(string $directive, array $hosts): void
    {
        $erlaubt = array_map(strval(...), (array) config("csp.directives.{$directive}", []));
        $fehlend = array_values(array_diff($hosts, $erlaubt));

        $fehlend === []
            ? $this->result(self::OK, "CSP {$directive}", 'Google-Anzeigenhosts freigegeben')
            : $this->result(self::FAIL, "CSP {$directive}", 'fehlt: '.implode(', ', $fehlend));
    }

    /** Je Portal eine Zeile; die Befunde stehen als Pruefzeilen darueber. */
    private function checkTenants(AdsTxtBuilder $adsTxt): void
    {
        $nurTenant = $this->option('tenant');
        $ohneAlt = (bool) $this->option('ohne-alt');

        $rows = [];
        $freigegeben = 0;

        $tenants = Tenant::query()->orderBy('id')->get();

        if (filled($nurTenant)) {
            $tenants = $tenants->where('id', (int) $nurTenant);
        }

        foreach ($tenants as $tenant) {
            $id = (int) $tenant->getKey();
            $domain = (string) $tenant->getAttribute('domain');
            $label = $domain !== '' ? $domain : "Portal {$id}";
            $theme = AdThemeContext::forTenant($tenant);
            $setting = AdSetting::query()->forTenant($tenant)->first();
            $enabled = $setting?->ads_enabled === true;
            $publisher = $setting?->adsensePublisherId();
            $hatAnker = AdSlotCatalog::forTheme($theme) !== [];

            if ($enabled) {
                $freigegeben++;
            }

            $belegungen = AdPlacement::query()->deliverable()->forTenant($tenant)->count();
            $adsenseBelegungen = AdPlacement::query()
                ->deliverable()
                ->forTenant($tenant)
                ->whereHas('adUnit', fn ($query) => $query->where('provider', AdProviderType::ADSENSE->value))
                ->count();

            $neueZeilen = $setting === null ? [] : $adsTxt->lines($id);
            $alteZeilen = $ohneAlt ? null : $this->legacyAdsTxt($tenant);
            $altSlots = $ohneAlt ? null : $this->legacySlotCount($tenant);

            // 1. Freigabe ohne wirksame Publisher-ID: AdSense-Anker bleiben
            //    leer, und die ads.txt verliert ihre Google-Zeile.
            if ($enabled && $publisher === null && $adsenseBelegungen > 0) {
                $this->result(self::FAIL, $label, "freigegeben, {$adsenseBelegungen} AdSense-Belegungen, aber keine wirksame Publisher-ID");
            } elseif ($enabled && $publisher === null) {
                $this->result(self::WARN, $label, 'freigegeben ohne Publisher-ID — AdSense kann hier nicht ausspielen');
            } elseif ($enabled && ! AdSensePublisherId::isValid($publisher)) {
                $this->result(self::WARN, $label, 'Publisher-ID nicht im Format ca-pub-16 Ziffern: Altbestand, beim naechsten Speichern korrigieren');
            }

            // 2. Freigabe in einem Theme ohne Anker (#16: default/starter
            //    bekommen keine). Dann wirkt die Freigabe nur fuer Auto Ads.
            if ($enabled && ! $hatAnker) {
                $this->result(self::WARN, $label, "freigegeben, aber Theme {$theme} hat keine Anker im Katalog");
            }

            // 3. Freigabe ohne Belegung: der Schalter steht, nichts laeuft.
            if ($enabled && $belegungen === 0 && $setting?->adsense_auto_ads !== true) {
                $this->result(self::WARN, $label, 'freigegeben, aber keine auslieferbare Belegung und keine Auto Ads');
            }

            // 4. ads.txt: leer heisst 404. Richtig fuer ein nicht
            //    freigegebenes Portal, falsch fuer ein freigegebenes.
            if ($enabled && $neueZeilen === []) {
                $this->result(self::FAIL, $label, 'ads.txt waere leer (404) — Google sieht keinen autorisierten Verkaeufer');
            }

            // 4a. Abgeschaltetes Portal, aber die eigene Google-Zeile steht in
            //     den Zusatzzeilen (#41). Seit #41 filtert der Builder sie
            //     heraus; die WARN sagt, dass in `ads_txt_extra` noch toter
            //     Altbestand liegt, der beim Wiedereinschalten doppelt waere.
            $eigeneGoogleExtra = $setting === null ? [] : array_values(array_filter(
                $setting->adsTxtLines(),
                static fn (string $zeile): bool => AdSensePublisherId::isGoogleLineFor($zeile, $publisher),
            ));

            if (! $enabled && $eigeneGoogleExtra !== []) {
                $this->result(self::WARN, $label, 'aus, aber eigene Google-Zeile in den Zusatzzeilen — wird aus der ads.txt gefiltert (#41), gehoert aus ads_txt_extra entfernt');
            }

            // 5. Der eigentliche Umstiegsfehler: die alte Datei hatte Zeilen,
            //    die die neue nicht mehr fuehrt. Die eigene Google-Zeile ist
            //    dabei kein Verlust, wenn der Schalter aus ist: genau das ist
            //    die Wirkung von fail-closed (#41).
            $verloren = $alteZeilen === null ? [] : array_values(array_diff($alteZeilen, $neueZeilen));

            if (! $enabled) {
                $verloren = array_values(array_filter(
                    $verloren,
                    static fn (string $zeile): bool => ! AdSensePublisherId::isGoogleLineFor($zeile, $publisher),
                ));
            }

            if ($verloren !== []) {
                $this->result(self::FAIL, $label, sprintf(
                    'ads.txt verliert %d Zeile(n) aus der Tenant-DB: %s — vorher php artisan ads:legacy-takeover',
                    count($verloren),
                    implode(' | ', array_slice($verloren, 0, 3)),
                ));
            }

            // 6. Parallelphase: das Altsystem liefert in diesem Portal noch
            //    selbst aus. Kein Fehler, aber es erklaert leere Anker.
            if ($altSlots !== null && $altSlots > 0) {
                $this->result(self::WARN, $label, "Altsystem liefert hier noch {$altSlots} aktive Slots aus (Parallelphase)");
            }

            $rows[] = [
                $id,
                $label,
                $theme.($hatAnker ? '' : ' (ohne Anker)'),
                $enabled ? 'ja' : 'nein',
                $publisher === null ? '—' : ($setting?->hasOwnPublisherId() === true ? 'eigene' : 'Netz'),
                $setting?->adsense_auto_ads === true ? 'ja' : 'nein',
                (string) $belegungen.($adsenseBelegungen > 0 ? " ({$adsenseBelegungen}× AdSense)" : ''),
                $neueZeilen === [] ? '404' : (string) count($neueZeilen),
                $alteZeilen === null ? '—' : (string) count($alteZeilen),
            ];
        }

        $this->table(
            ['ID', 'Domain', 'Theme', 'ads_enabled', 'Publisher-ID', 'Auto Ads', 'Belegungen', 'ads.txt', 'alt'],
            $rows,
        );

        $this->result(
            self::OK,
            'Freigegebene Portale',
            sprintf('%d von %d (ads_enabled)', $freigegeben, count($rows)),
        );
    }

    /**
     * Zeilen der alten `ads.txt` aus der Tenant-DB, gleich normalisiert wie
     * {@see AdSetting::adsTxtLines()} — nur so ist der Vergleich belastbar.
     *
     * @return list<string>|null null = Tenant-DB nicht lesbar
     */
    private function legacyAdsTxt(Tenant $tenant): ?array
    {
        try {
            $inhalt = (string) $tenant->run(
                static fn (): ?string => LegacyAdSetting::query()->value('ads_txt_content'),
            );
        } catch (Throwable $e) {
            $this->result(self::WARN, (string) $tenant->getAttribute('domain'), "Tenant-DB nicht lesbar: {$e->getMessage()}");

            return null;
        }

        $zeilen = preg_split('/\R/', $inhalt) ?: [];

        return array_values(array_unique(array_filter(
            array_map(trim(...), $zeilen),
            static fn (string $zeile): bool => $zeile !== '' && ! str_starts_with($zeile, '#'),
        )));
    }

    /** Aktive Slots des Altsystems in diesem Portal; null = nicht lesbar. */
    private function legacySlotCount(Tenant $tenant): ?int
    {
        try {
            return (int) $tenant->run(
                static fn (): int => LegacyAdSlot::query()->where('is_active', true)->count(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function result(string $status, string $check, string $outcome): void
    {
        $this->tally[$status]++;

        $color = match ($status) {
            self::OK => 'green',
            self::WARN => 'yellow',
            default => 'red',
        };

        // mb_strlen statt %-40s: Umlaute im Pruefpunkt verschieben sonst die Spalte.
        $this->line(sprintf(
            '<fg=%s>%s</>  %s%s',
            $color,
            str_pad($status, 4),
            $check.str_repeat(' ', max(1, 41 - mb_strlen($check))),
            $outcome,
        ));
    }
}
