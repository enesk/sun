<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Csp\Models\CspReport;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wertet die CSP-Sammelstelle aus (#51) — der Schritt „24 Stunden Meldungen
 * sichten" aus docs/turnstile-golive.md §1.5, jetzt mit Zahlen.
 *
 *   php artisan csp:berichte                       # letzte 24 h, je Portal
 *   php artisan csp:berichte --hours=72
 *   php artisan csp:berichte --portal=fahrschulefinder.de
 *   php artisan csp:berichte --directive=script-src
 *   php artisan csp:berichte --details             # jede Zeile einzeln
 *
 * Ohne `--details` zwei Tabellen: Summe je Portal und Summe je Direktive. Das
 * ist die Entscheidungsgrundlage vor `CSP_MODE=enforce` — eine Direktive mit
 * Treffern heisst, dass `enforce` dort etwas bricht.
 *
 * Exit 0 heisst „keine Meldung im Zeitraum", Exit 70 „es gibt Befunde". Damit
 * laesst sich der Schritt in der Checkliste maschinell abhaken.
 */
class CspReports extends Command
{
    protected $signature = 'csp:berichte
        {--hours=24 : Zeitraum in Stunden, gemessen am letzten Treffer}
        {--portal= : Nur dieses Portal (Dokument-Host)}
        {--directive= : Nur diese Direktive, z. B. script-src-elem}
        {--details : Jede entdoppelte Zeile einzeln, nach Treffern sortiert}
        {--limit=50 : Hoechstens so viele Zeilen bei --details}';

    protected $description = 'Zeigt gesammelte CSP-Verstoesse je Portal und Direktive (#51)';

    public function handle(): int
    {
        $stunden = max(1, (int) $this->option('hours'));

        if (config('csp.report.enabled') !== true) {
            $this->components->warn('CSP_REPORT_ENABLED ist aus — es wird nichts gesammelt.');
        }

        $zeilen = $this->basis($stunden)->count();

        if ($zeilen === 0) {
            $this->components->info(sprintf('Keine CSP-Meldung in den letzten %d Stunden.', $stunden));

            return self::SUCCESS;
        }

        $this->option('details') ? $this->details($stunden) : $this->summen($stunden);

        $this->newLine();
        $this->components->info(sprintf(
            '%d entdoppelte Meldungen, %s Treffer in den letzten %d Stunden.',
            $zeilen,
            number_format((int) $this->basis($stunden)->sum('hits'), 0, ',', '.'),
            $stunden
        ));

        // 70 = es gibt Befunde. Dasselbe Signal wie scripts/csp-live-pruefen.sh
        // und turnstile-golive-stand.sh benutzen fuer "noch etwas offen".
        return 70;
    }

    private function summen(int $stunden): void
    {
        // toBase(): die Aggregatspalten sind keine Modell-Attribute, die
        // Zeilen kommen als stdClass zurueck.
        $portale = $this->basis($stunden)
            ->toBase()
            ->selectRaw('portal, count(*) as zeilen, sum(hits) as treffer, max(last_seen_at) as zuletzt')
            ->groupBy('portal')
            ->orderByDesc('treffer')
            ->get();

        $this->table(
            ['Portal', 'Verstöße', 'Treffer', 'Zuletzt'],
            $portale->map(fn ($zeile): array => [
                (string) $zeile->portal,
                (string) $zeile->zeilen,
                number_format((int) $zeile->treffer, 0, ',', '.'),
                (string) $zeile->zuletzt,
            ])->all()
        );

        $direktiven = $this->basis($stunden)
            ->toBase()
            ->selectRaw('violated_directive, count(*) as zeilen, sum(hits) as treffer')
            ->groupBy('violated_directive')
            ->orderByDesc('treffer')
            ->get();

        $this->table(
            ['Direktive', 'Verstöße', 'Treffer'],
            $direktiven->map(fn ($zeile): array => [
                (string) $zeile->violated_directive,
                (string) $zeile->zeilen,
                number_format((int) $zeile->treffer, 0, ',', '.'),
            ])->all()
        );
    }

    private function details(int $stunden): void
    {
        $zeilen = $this->basis($stunden)
            ->orderByDesc('hits')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $this->table(
            ['Portal', 'Direktive', 'Blockiert', 'Dokument', 'Treffer', 'Zuletzt'],
            $zeilen->map(fn (CspReport $zeile): array => [
                $zeile->portal,
                $zeile->effective_directive ?? $zeile->violated_directive,
                mb_substr($zeile->blocked_uri, 0, 60),
                mb_substr($zeile->document_uri, 0, 50),
                number_format($zeile->hits, 0, ',', '.'),
                $zeile->last_seen_at?->format('d.m. H:i') ?? '—',
            ])->all()
        );
    }

    /** @return Builder<CspReport> */
    private function basis(int $stunden): Builder
    {
        $query = CspReport::query()->sinceHours($stunden);

        $portal = trim((string) $this->option('portal'));

        if ($portal !== '') {
            $query->where('portal', $portal);
        }

        $direktive = trim((string) $this->option('directive'));

        if ($direktive !== '') {
            $query->where(function (Builder $teil) use ($direktive): void {
                $teil->where('violated_directive', $direktive)
                    ->orWhere('effective_directive', $direktive);
            });
        }

        return $query;
    }
}
