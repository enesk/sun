<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Jobs\DailyChainJob;
use App\Content\Models\Central\ContentAlert;
use App\Content\Orchestration\ContentDailyOrchestrator;
use App\Content\Orchestration\SlotWatchdog;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\TenantCollection;
use Throwable;

/**
 * Tageskette der Content-Pipeline (#22).
 *
 * Zwei Betriebsarten:
 *
 *  - Ohne --queue arbeitet der Befehl die Stufen selbst ab. Das ist der Weg
 *    zum Nachholen eines verpassten Tages und zur Fehlersuche:
 *    `content:daily --tenant=7 --date=2026-09-08`.
 *  - Mit --queue reiht er je Mandant einen DailyChainJob ein. So ruft ihn
 *    der Scheduler: ein Ausfall bleibt beim betroffenen Portal.
 *
 * --sync fuehrt zusaetzlich die Fachjobs im Vordergrund aus (Abnahme ohne
 * Queue-Worker); ein kompletter Tageslauf dauert damit einige Minuten.
 */
class ContentDaily extends Command
{
    protected $signature = 'content:daily
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--date= : Tag der Kette (Y-m-d), Vorgabe: heute}
        {--stage= : Nur diese Stufe (discover|select|generate|publish|watchdog)}
        {--queue : Je Mandant einen DailyChainJob einreihen statt direkt zu arbeiten}
        {--sync : Fachjobs im Vordergrund ausfuehren}
        {--force : Wachhund auch vor dem Generatorfenster laufen lassen}';

    protected $description = 'Fuehrt die Tageskette der Ratgeber-Pipeline aus (Themen, Auswahl, Erzeugung, Veroeffentlichung)';

    public function handle(ContentDailyOrchestrator $orchestrator, SlotWatchdog $watchdog): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $stages = $this->stages();

        if ($stages === null) {
            $this->error('Unbekannte Stufe. Erlaubt: '.implode(', ', [...ContentDailyOrchestrator::STAGES, DailyChainJob::stageWatchdog()]));

            return self::FAILURE;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            // Vor dem Go-Live ist kein Portal freigeschaltet (#26). Das ist
            // kein Fehler, sonst meldete der Scheduler jeden Tag Alarm.
            if ($this->option('tenant') === null) {
                $this->warn('Kein Portal ist für die Content-Pipeline freigeschaltet (content:rollout).');

                return self::SUCCESS;
            }

            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $sync = (bool) $this->option('sync');
        $failed = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            foreach ($stages as $stage) {
                try {
                    $this->line("[{$tenant->name}] {$stage}: ".$this->runStage($orchestrator, $watchdog, $tenant, $stage, $date, $sync));
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("[{$tenant->name}] {$stage}: {$exception->getMessage()}");

                    ContentAlert::raise(
                        ContentAlert::KEY_CHAIN_FAILED,
                        __('Die Stufe „:stage" ist für :portal abgebrochen.', [
                            'stage' => $stage,
                            'portal' => (string) $tenant->name,
                        ]),
                        (int) $tenant->getKey(),
                        ContentAlert::LEVEL_CRITICAL,
                        ['stage' => $stage, 'exception' => $exception->getMessage()],
                        $date,
                    );
                }
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function runStage(
        ContentDailyOrchestrator $orchestrator,
        SlotWatchdog $watchdog,
        Tenant $tenant,
        string $stage,
        CarbonImmutable $date,
        bool $sync,
    ): string {
        if ($this->option('queue')) {
            DailyChainJob::dispatch((int) $tenant->getKey(), $stage, $date->toDateString());

            return __('eingereiht.');
        }

        if ($stage === DailyChainJob::stageWatchdog()) {
            $result = $watchdog->check($tenant, $date, $sync, (bool) $this->option('force'));

            if (! $result['checked']) {
                return __('noch außerhalb des Fensters.');
            }

            return __('belegt: :covered, nachgezogen: :retried, aufgegeben: :exhausted', [
                'covered' => implode(',', $result['covered']) ?: '-',
                'retried' => implode(',', $result['retried']) ?: '-',
                'exhausted' => implode(',', $result['exhausted']) ?: '-',
            ]);
        }

        return $orchestrator->runStage($tenant, $stage, $date, $sync);
    }

    /**
     * @return array<int, string>|null null = unbekannte Stufe
     */
    private function stages(): ?array
    {
        $stage = $this->option('stage');

        if ($stage === null) {
            return ContentDailyOrchestrator::STAGES;
        }

        $allowed = [...ContentDailyOrchestrator::STAGES, DailyChainJob::stageWatchdog()];

        return in_array($stage, $allowed, true) ? [(string) $stage] : null;
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            // Ohne --tenant arbeitet der Befehl nur auf freigeschalteten
            // Portalen (#26). Ein einzeln benanntes Portal laeuft weiter,
            // damit sich ein Portal vor dem Go-Live pruefen laesst.
            return app(TenantRollout::class)->activeTenants();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
