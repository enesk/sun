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

Schedule::command('import:cleanup')->dailyAt('02:00');

/*
| Content-Pipeline: Quell-Connectoren (#7)
|
| Die Connectoren deklarieren ihre Frequenz selbst (SourceFrequency); der
| Scheduler ruft je Rhythmus einen Sammellauf auf, der die faelligen
| Connectoren je Mandant als Jobs auf 'content-sources' einreiht.
*/
Schedule::command('content:sources:run --frequency=hourly')->hourlyAt(10)->withoutOverlapping();
Schedule::command('content:sources:run --frequency=six_hourly')->cron('20 */6 * * *')->withoutOverlapping();
Schedule::command('content:sources:run --frequency=daily')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('content:sources:run --frequency=weekly')->weeklyOn(1, '01:45')->withoutOverlapping();

Schedule::command('content:sources:prune')->dailyAt('03:40');

/*
| Content-Pipeline: Veroeffentlichung (#21)
|
| Der Normalfall laeuft ueber die Verzoegerung des ScheduleAndPublishJob. Der
| Fuenf-Minuten-Lauf ist die Sicherung: er holt verpasste Zeitpunkte nach,
| plant freigegebene Entwuerfe ein und zieht kurz vor Fensterende den
| Reserve-Kandidaten nach.
*/
Schedule::command('content:publish:due')->everyFiveMinutes()->withoutOverlapping();

/*
| Content-Pipeline: Metriken und Lernschleife (#23)
|
| Der Collector laeuft nach dem taeglichen Quellenlauf, damit er die frischen
| Search-Console-Rohzeilen des Gap-Connectors (#9) vorfindet und keinen
| zweiten Abruf braucht. Die Lernschleife rechnet einmal woechentlich; ihr
| Ergebnis aendert sich langsamer als taeglich.
*/
Schedule::command('content:metrics:collect')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('content:learning:run')->weeklyOn(1, '05:30')->withoutOverlapping();

/*
| Content-Pipeline: Refresh-Loop (#24)
|
| Laeuft nach dem Metrik-Collector, damit er dessen frische needs_refresh-
| Marken vorfindet, und vor dem Veroeffentlichungsfenster. Je Mandant
| hoechstens drei Aktualisierungen am Tag; sie zaehlen nicht gegen das
| Tagesziel von zwei neuen Artikeln.
*/
Schedule::command('content:refresh:run')
    ->dailyAt((string) config('content.refresh.run_at', '07:15'))
    ->timezone((string) config('content.pipeline.timezone', 'Europe/Berlin'))
    ->withoutOverlapping()
    ->onOneServer();

/*
| Content-Pipeline: Tageskette (#22)
|
| Alle Zeiten in Europe/Berlin. Jeder Eintrag reiht je Mandant einen eigenen
| DailyChainJob ein (--queue): ein Portal, das ausfaellt, haelt die uebrigen
| nicht auf. withoutOverlapping verhindert, dass ein langer Lauf sich selbst
| ueberholt, onOneServer, dass mehrere Anwendungsserver dieselbe Kette
| doppelt starten.
*/
$contentTimezone = (string) config('content.pipeline.timezone', 'Europe/Berlin');

Schedule::command('content:daily --stage=discover --queue')
    ->dailyAt((string) config('content.pipeline.schedule.discover_at', '02:00'))
    ->timezone($contentTimezone)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('content:daily --stage=select --queue')
    ->dailyAt((string) config('content.pipeline.schedule.select_at', '03:00'))
    ->timezone($contentTimezone)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('content:daily --stage=generate --queue')
    ->dailyAt((string) config('content.pipeline.schedule.generate_at', '03:30'))
    ->timezone($contentTimezone)
    ->withoutOverlapping()
    ->onOneServer();

// Die Veroeffentlichung braucht keinen eigenen Tageseintrag: die Kette
// stoesst sie je Artikel selbst an, und content:publish:due holt alle fuenf
// Minuten nach, was liegen geblieben ist.

// Der Wachhund prueft je Mandant, ob jeder Slot des Tages einen Entwurf
// traegt, und zieht sonst einen Reserve-Kandidaten nach.
Schedule::command('content:daily --stage=watchdog --queue')
    ->cron('*/'.max(5, (int) config('content.pipeline.watchdog.every_minutes', 30)).' * * * *')
    ->timezone($contentTimezone)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('content:report:daily')
    ->dailyAt((string) config('content.pipeline.schedule.report_at', '20:00'))
    ->timezone($contentTimezone)
    ->withoutOverlapping()
    ->onOneServer();
