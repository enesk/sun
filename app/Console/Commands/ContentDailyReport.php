<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Mail\DailyContentReport;
use App\Content\Models\Central\ContentAlert;
use App\Content\Models\Central\ContentDailyReport as StoredReport;
use App\Content\Orchestration\DailyReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Tagesbericht der Content-Pipeline (#22), planmaessig um 20:00.
 *
 * Der Bericht geht an alle aktiven Redaktions-Accounts mit der Rolle owner
 * und liegt zusaetzlich central in `content_daily_reports`, aus der die
 * Uebersicht des Panels ihn zeigt.
 */
class ContentDailyReport extends Command
{
    protected $signature = 'content:report:daily
        {--date= : Tag des Berichts (Y-m-d), Vorgabe: heute}
        {--mail-to=* : Zusaetzliche Empfaenger}
        {--no-mail : Nur bauen und anzeigen, nicht versenden}
        {--json : Bericht als JSON ausgeben}';

    protected $description = 'Erstellt den Tagesbericht der Ratgeber-Pipeline und schickt ihn an die Owner';

    public function handle(DailyReportBuilder $builder): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $report = $builder->build($date);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->summary($report);
        }

        if ($this->option('no-mail')) {
            return self::SUCCESS;
        }

        $recipients = array_values(array_unique(array_merge(
            ContentAlert::ownerRecipients(),
            array_map('strval', (array) $this->option('mail-to')),
        )));

        if ($recipients === []) {
            $this->warn('Kein Empfaenger: es gibt keinen aktiven Redaktions-Account mit der Rolle owner.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new DailyContentReport($report));

        StoredReport::query()
            ->where('report_date', $date->toDateString())
            ->update(['sent_at' => now()]);

        $this->info('Tagesbericht versandt an: '.implode(', ', $recipients));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function summary(array $report): void
    {
        $this->info(sprintf(
            'Tagesbericht %s: %d von %d Artikeln, %d offene Slots, %s USD heute.',
            $report['date'],
            $report['totals']['published'],
            $report['totals']['target'],
            $report['totals']['failed_slots'],
            number_format((float) $report['cost']['today'], 2),
        ));

        $this->table(
            ['Portal', 'Veroeffentlicht', 'Ziel', 'Offene Slots', 'USD'],
            array_map(static fn (array $portal): array => [
                $portal['name'],
                $portal['published'],
                $portal['target'],
                count($portal['failed_slots']),
                number_format((float) $portal['cost'], 2),
            ], $report['portals']),
        );

        foreach ($report['alerts'] as $alert) {
            $this->warn("[{$alert['level']}] {$alert['message']}");
        }
    }
}
