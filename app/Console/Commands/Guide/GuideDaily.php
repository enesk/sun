<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Mail\DailyGuideReport;
use App\Guide\Models\Central\GuideDailyReport;
use App\Guide\Orchestration\DailyOrchestrator;
use App\Guide\Orchestration\DailyReportBuilder;
use App\Guide\Support\GuideOwners;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Tageslauf des Ratgebersystems (#13). Planmaessig aus routes/console.php,
 * von Hand z. B.:
 *
 *   php artisan guide:daily --tenant=7 --date=2026-09-21
 *   php artisan guide:daily --tenant=sanitaerfinder.com --sync --now
 *   php artisan guide:daily --stage=watchdog
 *   php artisan guide:daily --stage=report --no-mail --json
 *
 * dispatch (Vorgabe): je aktivem Tenant ein DispatchDueTopicsJob, der die
 * faelligen Themen gestaffelt ueber das Laufzeitfenster einreiht. Ein
 * zweiter Aufruf am selben Tag setzt die bestehenden Laeufe fort.
 * watchdog: haengende Laeufe neu ansetzen (RunWatchdog).
 * report: Tagesbericht bauen, speichern und an die Inhaber senden.
 */
class GuideDaily extends Command
{
    protected $signature = 'guide:daily
        {--tenant= : Nur dieses Portal (ID, UUID oder Domain)}
        {--date= : Tag (Y-m-d), Vorgabe: heute in guide.timezone}
        {--stage=dispatch : dispatch, watchdog oder report}
        {--sync : Themenauswahl im selben Prozess statt auf guide-dispatch}
        {--now : Laeufe sofort einreihen statt ueber das Laufzeitfenster zu staffeln}
        {--mail-to=* : Zusaetzliche Empfaenger des Tagesberichts}
        {--no-mail : Tagesbericht nur bauen und speichern}
        {--json : Ergebnis als JSON ausgeben}';

    protected $description = 'Startet den Tageslauf des Ratgebersystems, den Watchdog oder den Tagesbericht';

    public function handle(DailyOrchestrator $orchestrator, DailyReportBuilder $reports): int
    {
        $stage = (string) $this->option('stage');

        if (! in_array($stage, DailyOrchestrator::STAGES, true)) {
            $this->error('--stage muss '.implode(', ', DailyOrchestrator::STAGES).' sein.');

            return self::FAILURE;
        }

        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $day = $this->option('date') !== null
            ? Carbon::parse((string) $this->option('date'), $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();

        if ($stage === DailyOrchestrator::STAGE_REPORT) {
            return $this->report($reports, $day);
        }

        $tenant = null;
        $needle = trim((string) $this->option('tenant'));

        if ($needle !== '') {
            $tenant = ctype_digit($needle)
                ? Tenant::query()->find((int) $needle)
                : Tenant::query()->where('uuid', $needle)->orWhere('domain', $needle)->first();

            if ($tenant === null) {
                $this->error("Portal nicht gefunden: {$needle}");

                return self::FAILURE;
            }
        }

        $results = $stage === DailyOrchestrator::STAGE_WATCHDOG
            ? $orchestrator->watchdog($tenant)
            : $orchestrator->dispatch($day, $tenant, (bool) $this->option('sync'), (bool) $this->option('now'));

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Portal', 'Name', 'Ergebnis', 'Hinweis'], array_map(
                static fn (array $result): array => [$result['tenant_id'], $result['name'], $result['outcome'], $result['message']],
                $results,
            ));
        }

        // Ein fehlgeschlagener Tenant hat die uebrigen nicht aufgehalten, soll
        // aber im Scheduler-Protokoll auffallen.
        $failed = array_filter($results, static fn (array $result): bool => $result['outcome'] === DailyOrchestrator::OUTCOME_FAILED);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function report(DailyReportBuilder $reports, Carbon $day): int
    {
        $report = $reports->build($day);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $totals = $report['totals'];
            $this->info(sprintf(
                'Tagesbericht %s: %d geprueft, %d neu, %d aktualisiert, %d unveraendert, %d Pruefung, %d fehlgeschlagen, %d verschoben, %s USD.',
                $report['date'],
                $totals['checked'],
                $totals['created'],
                $totals['updated'],
                $totals['unchanged'],
                $totals['review'],
                $totals['failed'],
                $totals['deferred'],
                number_format((float) $totals['cost_usd'], 2),
            ));
        }

        if ($this->option('no-mail')) {
            return self::SUCCESS;
        }

        $recipients = array_values(array_unique(array_filter([
            ...GuideOwners::emails(),
            ...array_map('strval', (array) $this->option('mail-to')),
        ])));

        if ($recipients === []) {
            $this->warn('Kein Empfaenger: es gibt keinen aktiven Inhaber mit Mailadresse.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new DailyGuideReport($report));

        GuideDailyReport::query()
            ->where('report_date', $report['date'])
            ->update(['sent_at' => now()]);

        $this->info('Tagesbericht versandt an: '.implode(', ', $recipients));

        return self::SUCCESS;
    }
}
