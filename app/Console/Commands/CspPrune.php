<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Csp\Models\CspReport;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Raeumt die CSP-Sammelstelle (#51): loescht Zeilen, deren letzter Treffer
 * aelter ist als `csp.report.retention_days` (Vorgabe 30 Tage).
 *
 *   php artisan csp:prune
 *   php artisan csp:prune --days=7
 *   php artisan csp:prune --dry-run
 *
 * Ein Lauf, keine Schleife ueber Portale: `csp_reports` liegt zentral, anders
 * als das Turnstile-Log. Geloescht wird trotzdem in Runden von
 * `csp.report.prune_chunk` Zeilen (DELETE mit LIMIT) — die Tabelle kann nach
 * einem kaputten Drittanbieter-Schnipsel gross werden, und ein grosses DELETE
 * haelt sie zu lange gesperrt.
 *
 * Maßgeblich ist `last_seen_at`, nicht `first_seen_at`: eine Zeile, die immer
 * noch Treffer sammelt, ist ein offener Befund und bleibt stehen.
 */
class CspPrune extends Command
{
    protected $signature = 'csp:prune
        {--days= : Aufbewahrung in Tagen, Vorgabe aus csp.report.retention_days}
        {--chunk= : Zeilen je Runde, Vorgabe aus csp.report.prune_chunk}
        {--dry-run : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht CSP-Berichte, deren letzter Treffer aelter als die Aufbewahrungsfrist ist (#51)';

    public function handle(): int
    {
        $tage = (int) ($this->option('days') ?? config('csp.report.retention_days', 30));
        $runde = max(1, (int) ($this->option('chunk') ?? config('csp.report.prune_chunk', 1000)));
        $probe = (bool) $this->option('dry-run');

        if ($tage < 1) {
            $this->components->error('--days muss mindestens 1 sein.');

            return self::FAILURE;
        }

        $grenze = now()->subDays($tage);

        $geloescht = $probe
            ? $this->countOlderThan($grenze)
            : $this->deleteOlderThan($grenze, $runde);

        $this->components->info(sprintf(
            '%s Zeilen mit letztem Treffer vor %s (%d Tage)%s.',
            number_format($geloescht, 0, ',', '.'),
            $grenze->format('d.m.Y H:i'),
            $tage,
            $probe ? ' — Probelauf, nichts gelöscht' : ' gelöscht'
        ));

        if (! $probe && $geloescht > 0) {
            Log::info('CSP-Sammelstelle geraeumt', ['rows' => $geloescht, 'retention_days' => $tage]);
        }

        return self::SUCCESS;
    }

    private function countOlderThan(DateTimeInterface $grenze): int
    {
        return CspReport::query()->where('last_seen_at', '<', $grenze)->count();
    }

    /**
     * Runde fuer Runde. Die Obergrenze von 10.000 Runden ist eine Reissleine
     * gegen eine Endlosschleife, falls der Treiber kein LIMIT beim DELETE
     * unterstuetzt und 0 zurueckmeldet — dasselbe Muster wie turnstile:prune.
     */
    private function deleteOlderThan(DateTimeInterface $grenze, int $runde): int
    {
        $gesamt = 0;

        for ($i = 0; $i < 10_000; $i++) {
            $zeilen = CspReport::query()
                ->where('last_seen_at', '<', $grenze)
                ->limit($runde)
                ->delete();

            $gesamt += $zeilen;

            if ($zeilen < $runde) {
                break;
            }
        }

        return $gesamt;
    }
}
