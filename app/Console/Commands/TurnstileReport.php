<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Turnstile\Mail\BotProtectionDailyReport;
use App\Turnstile\Monitoring\DailyReport;
use App\Turnstile\Support\BotProtectionRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tagesbericht des Bot-Schutzes (#12).
 *
 *   php artisan turnstile:report                 # Vortag
 *   php artisan turnstile:report --date=2026-10-07
 *   php artisan turnstile:report --no-mail       # nur Konsole
 *   php artisan turnstile:report --json
 *
 * Ohne --date gilt der Vortag: der Scheduler laeuft am Morgen, ein Bericht
 * ueber den angebrochenen Tag waere halb leer.
 *
 * Gerechnet wird in App\Turnstile\Monitoring\DailyReport, dargestellt in der
 * Mail-Vorlage. Die Konsolenausgabe zeigt dieselben Zahlen wie die Mail.
 */
class TurnstileReport extends Command
{
    protected $signature = 'turnstile:report
        {--date= : Berichtstag YYYY-MM-DD, Vorgabe gestern}
        {--no-mail : Nur auf der Konsole ausgeben}
        {--json : Bericht als JSON ausgeben}';

    protected $description = 'Tagesbericht je Portal: Registrierungen, Eintraege, blockiert, Quarantaene (#12)';

    public function handle(DailyReport $builder): int
    {
        try {
            $tag = $this->option('date') === null
                ? CarbonImmutable::yesterday()
                : CarbonImmutable::parse((string) $this->option('date'))->startOfDay();
        } catch (Throwable) {
            $this->components->error('--date erwartet ein Datum im Format YYYY-MM-DD.');

            return self::FAILURE;
        }

        $bericht = $builder->build($tag);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($bericht, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($bericht);
        }

        if ((bool) $this->option('no-mail') || ! (bool) config('turnstile.report.mail', true)) {
            return self::SUCCESS;
        }

        $empfaenger = BotProtectionRecipients::emails();

        if ($empfaenger === []) {
            $this->components->warn('Kein Empfaenger hinterlegt (turnstile.alerts.recipients) — keine Mail verschickt.');

            return self::SUCCESS;
        }

        Mail::to($empfaenger)->send(new BotProtectionDailyReport($bericht));

        $this->components->info('Bericht verschickt an '.implode(', ', $empfaenger).'.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $bericht
     */
    private function render(array $bericht): void
    {
        /** @var array<string, int|float> $summen */
        $summen = $bericht['totals'];
        /** @var list<array<string, mixed>> $portale */
        $portale = $bericht['portals'];

        $this->components->info(sprintf('Bot-Schutz %s — %d Portale', $bericht['date_label'], (int) $summen['portals']));

        if ($portale === []) {
            $this->components->warn('Kein Portal mit lesbarem Log.');

            return;
        }

        $zeilen = array_map(
            static fn (array $zeile): array => [
                (string) $zeile['name'],
                (string) $zeile['registrations'],
                (string) $zeile['listings'],
                (string) $zeile['checks'],
                (string) $zeile['blocked'],
                number_format(((float) $zeile['blocked_share']) * 100, 1, ',', '.').' %',
                (string) $zeile['errors'],
                $zeile['quarantined_accounts'].' / '.$zeile['quarantined_listings'],
            ],
            $portale
        );

        $zeilen[] = [
            '<options=bold>Summe</>',
            (string) $summen['registrations'],
            (string) $summen['listings'],
            (string) $summen['checks'],
            (string) $summen['blocked'],
            number_format(((float) $summen['blocked_share']) * 100, 1, ',', '.').' %',
            (string) $summen['errors'],
            $summen['quarantined_accounts'].' / '.$summen['quarantined_listings'],
        ];

        $this->table(
            ['Portal', 'Registrierungen', 'Einträge', 'Prüfungen', 'Blockiert', 'Quote', 'Fehler', 'Quarantäne K/E'],
            $zeilen
        );

        if ($bericht['unreadable'] !== []) {
            $this->components->warn('Ohne Log: '.implode(', ', (array) $bericht['unreadable']));
        }
    }
}
