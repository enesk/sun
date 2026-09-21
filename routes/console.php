<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('tenants:generate-sitemap')->everyOddHour();

Schedule::command('app:metrics-beat')->dailyAt('00:01');

Schedule::command('app:local-subscription-expiring-soon-reminder')->dailyAt('00:01');

Schedule::command('app:cleanup-local-subscription-statuses')->hourly();

Schedule::command('app:sync-seat-based-subscription-quantities')->hourly();

Schedule::command('app:aggregate-tracking-stats')->dailyAt('00:30');
Schedule::command('app:aggregate-tracking-stats --cleanup')->weeklyOn(1, '03:00');

/*
| Premium-Modul: Betriebsstatistik (#15)
|
| Verdichtet je Mandant den Vortag in company_stats_daily und loescht
| Rohevents aelter als 14 Tage (config premium.stats.raw_retention_days).
*/
Schedule::command('tenants:run stats:aggregate-daily')
    ->dailyAt('00:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:expire-jobs')->dailyAt('01:00');

// Premium (#5): Auto-Downgrade nach Ablauf/Grace Period, je Tenant
Schedule::command('tenants:run premium:process-expirations')->dailyAt('01:15')->withoutOverlapping()->onOneServer();

// Premium (#16): Monatsreport am 1. um 07:00, nach der Tagesaggregation (00:45), je Tenant
Schedule::command('tenants:run premium:send-monthly-reports')->monthlyOn(1, '07:00')->withoutOverlapping()->onOneServer();

// Premium (#11): Verifizierungsnachweise 90 Tage nach Entscheidung loeschen, je Tenant
Schedule::command('tenants:run premium:purge-verification-documents')->dailyAt('01:30')->withoutOverlapping()->onOneServer();

// Exklusive Anfragen (#9): Kontaktdaten nach 12 Monaten loeschen, je Tenant
Schedule::command('tenants:run leads:purge-contacts')->dailyAt('01:30')->withoutOverlapping()->onOneServer();

// Webhook-Anfragen (#32 Premium): Kontaktdaten und Antworten nach 12 Monaten loeschen, je Tenant
Schedule::command('tenants:run leads:inquiries:purge-contacts')->dailyAt('01:45')->withoutOverlapping()->onOneServer();

Schedule::command('import:cleanup')->dailyAt('02:00');

// Ratgeber-Import (#15): hochgeladene Themenlisten nach 7 Tagen loeschen
Schedule::command('guide:imports:prune')->dailyAt('02:15')->onOneServer();

// Ratgeber-Publisher (#12): hoechstens 30 Fassungen je Artikel, erste und veroeffentlichte bleiben
Schedule::command('guide:versions:prune')->dailyAt('01:50')->withoutOverlapping()->onOneServer();

/*
| Leistungsdaten der Ratgeber-URLs (docs/guide-system.md §9)
|
| Der Metrik-Collector holt die Search-Console-Rohzeilen je Portal selbst
| (SearchConsoleRawFetcher) und verdichtet sie zu article_metrics. Die
| uebrigen Eintraege der alten Content-Pipeline (Quellen, Tageskette,
| Veroeffentlichung, Lernschleife, Refresh, Tagesbericht) sind mit dem
| Rueckbau entfallen; den Tagesbericht schreibt guide:daily --stage=report.
*/
Schedule::command('content:metrics:collect')->dailyAt('06:30')->withoutOverlapping();

/*
| Ratgebersystem: Tages-Orchestrator (#13)
|
| Alle Zeiten in guide.timezone (Europe/Berlin). guide:daily reiht je
| aktivem Tenant einen DispatchDueTopicsJob ein; der staffelt die faelligen
| Themen selbst ueber das Laufzeitfenster (run_window_start bis
| run_window_end) und faengt Fehler je Tenant ab. withoutOverlapping
| verhindert, dass ein langer Lauf sich selbst ueberholt, onOneServer, dass
| mehrere Anwendungsserver denselben Tag doppelt starten.
*/
$guideTimezone = (string) config('guide.timezone', 'Europe/Berlin');

Schedule::command('guide:daily --stage=dispatch')
    ->dailyAt((string) config('guide.run_window_start', '02:00'))
    ->timezone($guideTimezone)
    ->withoutOverlapping()
    ->onOneServer();

// Haengende Laeufe (laenger als guide.schedule.stuck_after_minutes in einem
// Zwischenstatus) neu ansetzen, Fehlerquote je Tenant pruefen.
Schedule::command('guide:daily --stage=watchdog')
    ->cron('*/'.max(5, (int) config('guide.orchestrator.watchdog.every_minutes', 10)).' * * * *')
    ->timezone($guideTimezone)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('guide:daily --stage=report')
    ->dailyAt((string) config('guide.orchestrator.report_at', '20:00'))
    ->timezone($guideTimezone)
    ->withoutOverlapping()
    ->onOneServer();
