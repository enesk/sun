<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\TopicStatus;
use App\Content\Jobs\DiscoverTopicsJob;
use App\Content\Jobs\ScoreTopicsJob;
use App\Content\Jobs\SelectDailyTopicsJob;
use App\Content\Models\TopicCandidate;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Themenfindung, Bewertung und Tagesauswahl anstossen (#12).
 *
 * Ohne Parameter laeuft die volle Kette fuer alle Mandanten als Queue-Jobs:
 * Themen finden, bewerten, den Folgetag planen. Mit --sync laeuft sie sofort
 * im Vordergrund und zeigt am Ende die Auswahl mit ihren Teilscores — das ist
 * der Weg fuer die Staging-Abnahme.
 *
 * Die Teilschritte lassen sich einzeln fahren (--skip-discover,
 * --skip-select), etwa um nach geaenderten Gewichten neu zu bewerten, ohne
 * einen weiteren Modellaufruf zu bezahlen.
 */
class ContentTopicsDiscover extends Command
{
    protected $signature = 'content:topics:discover
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--date= : Zieltag der Auswahl (Y-m-d), Vorgabe: morgen}
        {--skip-discover : Nur bewerten und auswaehlen, kein Modellaufruf}
        {--skip-select : Keine Tagesauswahl}
        {--sync : Sofort ausfuehren statt in die Queue zu stellen}';

    protected $description = 'Findet Themenkandidaten, bewertet sie und plant den Folgetag';

    public function handle(): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

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

        $sync = (bool) $this->option('sync');
        $discover = ! (bool) $this->option('skip-discover');
        $select = ! (bool) $this->option('skip-select');
        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::tomorrow();

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant->getKey();

            if ($sync) {
                if ($discover) {
                    DiscoverTopicsJob::dispatchSync($tenantId, chainScoring: false);
                }

                ScoreTopicsJob::dispatchSync($tenantId, chainSelection: false);

                if ($select) {
                    SelectDailyTopicsJob::dispatchSync($tenantId, $date->toDateString());
                }

                $this->report($tenant, $date);

                continue;
            }

            if ($discover) {
                // Der Discover-Job stoesst Scoring und Auswahl selbst an.
                DiscoverTopicsJob::dispatch($tenantId);
            } else {
                ScoreTopicsJob::dispatch($tenantId, chainSelection: $select);
            }

            $this->line("[{$tenant->name}] eingereiht.");
        }

        return self::SUCCESS;
    }

    /**
     * Zeigt, was fuer den Zieltag geplant ist — inklusive Teilscores und
     * Begruendung des Regionszuschnitts (Abnahmekriterium des Tickets).
     */
    private function report(Tenant $tenant, CarbonImmutable $date): void
    {
        $tenant->run(function () use ($tenant, $date): void {
            $topics = TopicCandidate::query()
                ->whereDate('selected_for_date', $date)
                ->whereIn('status', [TopicStatus::SELECTED->value, TopicStatus::RESERVE->value])
                ->orderBy('status')
                ->orderByDesc('total_score')
                ->get();

            $this->newLine();
            $this->info("[{$tenant->name}] Plan fuer {$date->toDateString()}: {$topics->count()} Themen");

            if ($topics->isEmpty()) {
                $this->warn('Keine Kandidaten ueber der Mindestpunktzahl.');

                return;
            }

            $this->table(
                ['Status', 'Thema', 'Gesamt', 'Trend', 'Nachfrage', 'Luecke', 'Saison', 'Eigen.', 'Region'],
                $topics->map(fn (TopicCandidate $topic): array => [
                    $topic->status->label(),
                    Str::limit((string) $topic->primary_keyword, 34),
                    number_format((float) $topic->total_score, 1),
                    number_format((float) $topic->trend_score, 1),
                    number_format((float) $topic->demand_score, 1),
                    number_format((float) $topic->gap_score, 1),
                    number_format((float) $topic->seasonal_score, 1),
                    number_format((float) $topic->uniqueness_score, 1),
                    $topic->region_scope.($topic->region_code !== null ? " {$topic->region_code}" : ''),
                ])->all(),
            );

            foreach ($topics as $topic) {
                $this->line("  {$topic->primary_keyword}: {$topic->region_reason}");
            }
        });
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
