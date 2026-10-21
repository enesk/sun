<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Models\AdImpressionDaily;
use App\Ads\Support\AdImpressionRecorder;
use App\Ads\Support\AdImpressionStats;
use App\Models\Tenant;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Liest die Abnahmezahlen der Impression-Zaehlung aus (Ticket #37, DoD #12,
 * docs/ad-slots.md 14.1).
 *
 * Gibt genau das aus, was das Widget "Werbeplaetze — eigene Impressionen"
 * zeigt: dieselbe Quelle ({@see AdImpressionStats::summary()}), dieselben
 * Zeitfenster, dieselbe Sortierung nach Gesamtzahl. Damit ist die Abnahme
 * ohne Browser belegbar und am Tag der Abnahme nachvollziehbar, statt nur als
 * Bildschirmfoto.
 *
 * Zusaetzlich die beiden Dinge, die ein Bildschirmfoto nicht hergibt:
 *
 *   * die Reife des Fensters — wie viele Tage im Zeitraum ueberhaupt Daten
 *     haben; unter sieben ist die Abnahme aus #37 noch nicht faellig,
 *   * den Vergleich mit dem AdSense-Report (`--adsense=`): Abweichungen sind
 *     erwartbar (AdSense zaehlt den Anzeigenblock, wir den Anker), ueber
 *     Faktor 2 ist es ein Befund.
 *
 * Seit Ticket #47 stehen neben den Sichtkontakten die ausgelieferten
 * Fragmente und die Viewability-Quote — die Groesse, an der die
 * Entscheidungsregel aus docs/ad-slots.md 13.3 haengt.
 *
 * `--ab=YYYY-MM-DD` setzt den Fensteranfang frei (Ticket #66). Noetig, wenn
 * der Zeitraum vollstaendig hinter einem Ereignis liegen muss und keine der
 * beiden Breiten das trifft: nach dem Deploy des Nenners am 16.10.2026 enthielt
 * selbst `--tage=7` noch sechs Tage ohne Nenner, ein ganzes sauberes
 * 7-Tage-Fenster gab es erst eine Woche spaeter. Das Widget bleibt davon
 * unberuehrt — es kennt weiter nur {@see AdImpressionStats::WINDOWS}.
 *
 * Nur Lesezugriff.
 */
class AdsImpressionAbnahmeCommand extends Command
{
    protected $signature = 'ads:impressionen-abnahme
        {--tage= : Nur dieses Zeitfenster zeigen (7 oder 30), Vorgabe beide}
        {--ab= : Fensteranfang als YYYY-MM-DD statt einer festen Breite (schliesst --tage aus)}
        {--tenant= : Nur dieses Portal (tenant_id), wie der Dashboard-Filter}
        {--adsense= : Impressionen desselben Portals und Zeitraums aus dem AdSense-Report}';

    protected $description = 'Zeigt die Abnahmezahlen des Impression-Widgets je Anker, Geraet und Portal';

    /** Ab diesem Faktor Abweichung zum AdSense-Report ist es ein Befund (#37 Schritt 7). */
    private const ADSENSE_FACTOR = 2.0;

    public function handle(): int
    {
        if (! AdImpressionRecorder::enabled()) {
            $this->warn('Impression-Zaehlen ist abgeschaltet (ad-slots.impression_tracking) — das Widget ist dann ausgeblendet.');

            return self::FAILURE;
        }

        $tenantId = $this->tenantId();

        if ($tenantId === false) {
            return self::FAILURE;
        }

        $ab = $this->ab();

        if ($ab === false) {
            return self::FAILURE;
        }

        $windows = $ab === null ? $this->windows() : [];

        if ($ab === null && $windows === []) {
            $this->error('--tage kennt nur '.implode(' und ', AdImpressionStats::WINDOWS).' — ein anderes Fenster geht ueber --ab=YYYY-MM-DD.');

            return self::FAILURE;
        }

        $adsense = $this->option('adsense') === null ? null : (int) $this->option('adsense');

        if ($adsense !== null && $tenantId === null) {
            $this->error('--adsense braucht --tenant: der Report gilt je Portal, die Gesamtzahl ueber alle Portale ist dagegen nicht vergleichbar.');

            return self::FAILURE;
        }

        $befund = false;

        $summaries = $ab === null
            ? array_map(fn (int $days): array => AdImpressionStats::summary($tenantId, $days), $windows)
            : [AdImpressionStats::summarySince($tenantId, $ab)];

        foreach ($summaries as $summary) {
            $this->newLine();
            $this->components->info(sprintf(
                '%d Tage ab %s — %s',
                $summary['days'],
                $summary['from'],
                $tenantId === null ? 'alle Portale' : "Portal {$tenantId} (".$this->tenantName($tenantId).')',
            ));

            $this->line(sprintf(
                'Sichtkontakte: %d (mobil %d, desktop %d)',
                $summary['total'],
                $summary['mobile'],
                $summary['desktop'],
            ));

            $this->line(sprintf(
                'Ausgeliefert: %d — Viewability-Quote: %s',
                $summary['delivered'],
                self::quote($summary['viewability']),
            ));

            $tageMitDaten = $this->daysWithData($tenantId, $summary['from']);
            $this->line("Tage mit Daten im Fenster: {$tageMitDaten} von {$summary['days']}");

            if ($summary['days'] < AdImpressionStats::WINDOWS[0]) {
                $this->warn(sprintf(
                    'Kurzes Fenster (%d statt %d Tage) — kleine Grundmenge. Eine Entscheidung nach docs/ad-slots.md 13.3 nur mit Vermerk auf die Fensterbreite.',
                    $summary['days'],
                    AdImpressionStats::WINDOWS[0],
                ));
            }

            if ($summary['total'] === 0 && $summary['delivered'] === 0) {
                $this->warn('Keine Impressionen in diesem Fenster — das Widget zeigt hier seinen Hinweistext.');

                continue;
            }

            $this->table(
                ['Anker', 'Schluessel', 'Mobil', 'Desktop', 'Sichtkontakte', 'Ausgeliefert', 'Sichtbar', 'Anteil mobil'],
                array_map(fn (array $slot): array => [
                    $slot['label'],
                    $slot['slot_key'],
                    $slot['mobile'],
                    $slot['desktop'],
                    $slot['total'],
                    $slot['delivered'],
                    self::quote($slot['viewability']),
                    $slot['total'] > 0 ? round($slot['mobile'] / $slot['total'] * 100).' %' : '—',
                ], $summary['slots']),
            );

            if ($summary['portals'] !== []) {
                $this->table(
                    ['Portal-Id', 'Portal', 'Mobil', 'Desktop', 'Sichtkontakte', 'Ausgeliefert', 'Sichtbar'],
                    array_map(fn (array $portal): array => [
                        $portal['tenant_id'],
                        $portal['name'],
                        $portal['mobile'],
                        $portal['desktop'],
                        $portal['total'],
                        $portal['delivered'],
                        self::quote($portal['viewability']),
                    ], $summary['portals']),
                );
            }

            if ($adsense !== null && $this->reportAdsense($summary['total'], $adsense)) {
                $befund = true;
            }
        }

        $this->newLine();
        $this->line('Quelle: ad_impression_daily, identisch zum Widget "Werbeplaetze — eigene Impressionen" (bis zu 60 s gecacht).');

        return $befund ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Quote fuer die Ausgabe. `null` heisst "kein Nenner im Fenster" und wird
     * zum Gedankenstrich — nicht zu 0 % (Ticket #47); Fenster, die noch Tage
     * vor der Einfuehrung des Nenners enthalten, haben ihn nicht.
     */
    private static function quote(?int $quote): string
    {
        return $quote === null ? '—' : $quote.' %';
    }

    /**
     * Vergleich mit dem AdSense-Report. true, wenn die Abweichung ein Befund
     * nach #37 Schritt 7 ist.
     */
    private function reportAdsense(int $eigene, int $adsense): bool
    {
        if ($adsense <= 0) {
            $this->warn('AdSense-Report mit 0 Impressionen angegeben — nicht vergleichbar.');

            return false;
        }

        $faktor = $eigene >= $adsense ? $eigene / $adsense : $adsense / $eigene;

        $this->line(sprintf(
            'AdSense-Report: %d, eigene Zaehlung: %d, Faktor %.2f',
            $adsense,
            $eigene,
            $faktor,
        ));

        if ($faktor > self::ADSENSE_FACTOR) {
            $this->error('Befund: Abweichung ueber Faktor '.self::ADSENSE_FACTOR.' — Zaehlkette pruefen.');

            return true;
        }

        $this->line('Abweichung im erwarteten Rahmen (AdSense zaehlt den Anzeigenblock, wir den Anker).');

        return false;
    }

    /**
     * Fensteranfang aus `--ab=`. `null` heisst "nicht gesetzt", `false` heisst
     * "gesetzt, aber unbrauchbar" — dann bricht das Kommando ab, statt still
     * auf die Vorgabefenster zurueckzufallen.
     */
    private function ab(): Carbon|false|null
    {
        $ab = $this->option('ab');

        if ($ab === null || $ab === '') {
            return null;
        }

        if ($this->option('tage') !== null && $this->option('tage') !== '') {
            $this->error('--ab und --tage schliessen sich aus: beide setzen den Fensteranfang.');

            return false;
        }

        try {
            $von = Carbon::createFromFormat('!Y-m-d', (string) $ab);
        } catch (InvalidFormatException) {
            $von = null;
        }

        // Rueckvergleich, weil Carbon ueberlaufende Angaben still umrechnet:
        // aus 2026-10-45 wuerde sonst klaglos der 14.11. werden.
        if ($von instanceof Carbon && $von->toDateString() !== (string) $ab) {
            $von = null;
        }

        if (! $von instanceof Carbon) {
            $this->error("--ab erwartet ein Datum als YYYY-MM-DD, bekommen: {$ab}.");

            return false;
        }

        if ($von->isFuture()) {
            $this->error('--ab liegt in der Zukunft — das Fenster waere leer.');

            return false;
        }

        return $von;
    }

    /**
     * @return list<int>
     */
    private function windows(): array
    {
        $tage = $this->option('tage');

        if ($tage === null || $tage === '') {
            return AdImpressionStats::WINDOWS;
        }

        return in_array((int) $tage, AdImpressionStats::WINDOWS, true) ? [(int) $tage] : [];
    }

    /**
     * Portal-Id aus der Option. false, wenn sie auf kein Portal zeigt — dann
     * waeren alle Zahlen still leer.
     */
    private function tenantId(): int|false|null
    {
        $tenant = $this->option('tenant');

        if ($tenant === null || $tenant === '') {
            return null;
        }

        if (Tenant::query()->whereKey((int) $tenant)->doesntExist()) {
            $this->error("Kein Portal mit tenant_id {$tenant}.");

            return false;
        }

        return (int) $tenant;
    }

    private function tenantName(int $tenantId): string
    {
        return (string) (Tenant::query()->whereKey($tenantId)->value('domain') ?? '—');
    }

    private function daysWithData(?int $tenantId, string $from): int
    {
        return DB::connection(AdImpressionRecorder::connection())
            ->table((new AdImpressionDaily)->getTable())
            ->where('day', '>=', $from)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->distinct()
            ->count('day');
    }
}
