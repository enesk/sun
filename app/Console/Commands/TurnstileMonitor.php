<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Turnstile\Monitoring\VerificationAlert;
use App\Turnstile\Monitoring\VerificationMonitor;
use Illuminate\Console\Command;

/**
 * Stuendliche Ueberwachung des Bot-Schutzes (#12).
 *
 *   php artisan turnstile:monitor
 *   php artisan turnstile:monitor --no-mail   # nur anzeigen, nichts verschicken
 *
 * Die Logik steht in App\Turnstile\Monitoring\VerificationMonitor; dieser
 * Befehl ist die Huelle fuer Scheduler und Hand. Eigener Befehl statt
 * Schedule::call, damit `withoutOverlapping()` und `onOneServer()` greifen und
 * sich der Lauf im Betrieb von Hand wiederholen laesst.
 *
 * Rueckgabe immer 0: ein Alarm ist ein Befund, kein Fehlschlag des Befehls.
 */
class TurnstileMonitor extends Command
{
    protected $signature = 'turnstile:monitor
        {--no-mail : Alarme nur auf der Konsole zeigen, keine Mail und keine Entdopplung}';

    protected $description = 'Prueft je Portal die letzte Stunde des Verifikations-Logs und alarmiert (#12)';

    public function handle(VerificationMonitor $monitor): int
    {
        if (! (bool) config('turnstile.alerts.enabled', true)) {
            $this->components->warn('Alarme sind abgeschaltet (turnstile.alerts.enabled).');

            return self::SUCCESS;
        }

        $ergebnis = $monitor->run(versenden: ! (bool) $this->option('no-mail'));

        if ($ergebnis['unreadable'] !== []) {
            $this->components->warn('Ohne Log: '.implode(', ', $ergebnis['unreadable']));
        }

        if ($ergebnis['alerts'] === []) {
            $this->components->info(sprintf('%d Portale geprüft, keine Auffälligkeit.', $ergebnis['portals']));

            return self::SUCCESS;
        }

        $this->table(
            // Keine __()-Schluessel ohne Punkt: __('Portal') laedt lang/de/portal.php
            // als Array (Dateiname case-insensitiv) und bricht die Tabelle.
            ['Portal', 'Alarm', 'Meldung', 'Mail'],
            array_map(
                static fn (VerificationAlert $alarm): array => [
                    $alarm->tenantName,
                    $alarm->type,
                    $alarm->headline,
                    in_array($alarm, $ergebnis['sent'], true) ? 'ja' : '–',
                ],
                $ergebnis['alerts']
            )
        );

        $this->components->info(sprintf(
            '%d Portale geprüft, %d Alarme, %d Mails an %s.',
            $ergebnis['portals'],
            count($ergebnis['alerts']),
            count($ergebnis['sent']),
            $ergebnis['recipients'] === [] ? 'niemanden' : implode(', ', $ergebnis['recipients'])
        ));

        return self::SUCCESS;
    }
}
