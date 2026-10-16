<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Jobs\AggregateAdImpressions;
use App\Ads\Support\AdImpressionRecorder;
use Illuminate\Console\Command;

/**
 * Verdichtet den Stundenpuffer der Impressionen ins Tagesaggregat
 * (Ticket #12, docs/ad-slots.md 14).
 *
 * Im Betrieb laeuft das stuendlich ueber den Scheduler (routes/console.php,
 * `Schedule::job(new AggregateAdImpressions)`). Der Befehl ist der Weg von
 * Hand — fuer die Abnahme und wenn ein Lauf nachgeholt werden muss. Er fuehrt
 * den Job SOFORT aus (nicht ueber die Queue), damit die Zahlen hier im
 * Terminal stehen.
 */
class AdsAggregateImpressionsCommand extends Command
{
    protected $signature = 'ads:aggregate-impressions';

    protected $description = 'Verdichtet die gemeldeten Werbeplatz-Impressionen in ad_impression_daily';

    public function handle(): int
    {
        if (! AdImpressionRecorder::enabled()) {
            $this->warn('Impression-Zaehlen ist abgeschaltet (ad-slots.impression_tracking).');

            return self::SUCCESS;
        }

        $ergebnis = (new AggregateAdImpressions)->handle();

        $this->info(sprintf(
            '%d Aggregatzeilen, %d Sichtkontakte und %d ausgelieferte Fragmente verdichtet, %d alte Tageszeilen geloescht.',
            $ergebnis['rows'],
            $ergebnis['impressions'],
            $ergebnis['deliveries'],
            $ergebnis['pruned'],
        ));

        return self::SUCCESS;
    }
}
