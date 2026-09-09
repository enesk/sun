<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\SourceFrequency;
use App\Content\Jobs\RunSourceConnectorJob;
use App\Content\Services\TenantRollout;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\Exceptions\UnknownConnectorException;
use App\Content\Sources\SourceRegistry;
use App\Content\Sources\SourceRunner;
use App\Models\Tenant;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Startet die Quell-Connectoren (#7).
 *
 * Ohne Parameter laufen alle faelligen Connectoren fuer alle Mandanten; der
 * Scheduler ruft die Frequenzvariante (--frequency) auf. Jeder Lauf wird als
 * eigener Queue-Job auf 'content-sources' eingereiht, --sync fuehrt ihn
 * stattdessen direkt aus (Diagnose, Staging-Abnahme).
 */
class ContentSourcesRun extends Command
{
    protected $signature = 'content:sources:run
        {--connector= : Schluessel eines einzelnen Connectors}
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--frequency= : Nur Connectoren dieses Rhythmus (hourly|six_hourly|daily|weekly)}
        {--force : Faelligkeitspruefung uebergehen}
        {--sync : Sofort ausfuehren statt in die Queue zu stellen}';

    protected $description = 'Fuehrt Quell-Connectoren der Content-Pipeline je Mandant aus';

    public function handle(SourceRegistry $registry, SourceRunner $runner): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        try {
            $connectors = $this->connectors($registry);
        } catch (UnknownConnectorException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($connectors === []) {
            $this->warn('Keine passenden Connectoren registriert.');

            return self::SUCCESS;
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

        $force = (bool) $this->option('force') || $this->option('connector') !== null;
        $sync = (bool) $this->option('sync');
        $dispatched = 0;
        $skipped = 0;
        $disabled = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            foreach ($connectors as $connector) {
                // Abgeschaltete Quellen (#55) gar nicht erst einreihen: der
                // Runner wuerde sie ohnehin ueberspringen, und 24 leere Jobs
                // je Sammellauf sind reine Queue-Last.
                if (! $registry->isEnabled($connector->key())) {
                    $disabled++;

                    continue;
                }

                if (! $force && ! $runner->isDue($tenant, $connector, $registry->frequencyOf($connector))) {
                    $skipped++;

                    continue;
                }

                if ($sync) {
                    $result = $runner->run($tenant, $connector);
                    $this->line("[{$tenant->name}] {$connector->key()}: {$result->summary()}");
                } else {
                    RunSourceConnectorJob::dispatch((int) $tenant->getKey(), $connector->key());
                }

                $dispatched++;
            }
        }

        $verb = $sync ? 'ausgefuehrt' : 'eingereiht';
        $this->info("{$dispatched} Laeufe {$verb}, {$skipped} noch nicht faellig, {$disabled} abgeschaltet.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, SourceConnector>
     */
    private function connectors(SourceRegistry $registry): array
    {
        $key = $this->option('connector');

        if ($key !== null) {
            return [$key => $registry->get((string) $key)];
        }

        $frequency = $this->option('frequency');

        if ($frequency === null) {
            return $registry->all();
        }

        $case = SourceFrequency::tryFrom((string) $frequency);

        if ($case === null) {
            $known = implode(', ', array_column(SourceFrequency::cases(), 'value'));

            throw new InvalidArgumentException("Unbekannte Frequenz '{$frequency}'. Erlaubt sind: {$known}.");
        }

        return $registry->dueFor($case);
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
