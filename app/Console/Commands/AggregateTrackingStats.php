<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AggregateTrackingStats extends Command implements Isolatable
{
    protected $signature = 'app:aggregate-tracking-stats
        {--date= : Specific date to aggregate (YYYY-MM-DD). Default: yesterday}
        {--from= : Start date for range aggregation (YYYY-MM-DD)}
        {--to= : End date for range aggregation (YYYY-MM-DD)}
        {--tenant= : Specific tenant ID (optional, runs for all if omitted)}
        {--cleanup : Delete raw events older than --retention-days}
        {--retention-days=90 : Days to keep raw events (default: 90)}';

    protected $description = 'Aggregate tracking_events into tracking_daily_stats per tenant';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');
            return self::FAILURE;
        }

        // Determine date range
        $dates = $this->resolveDateRange();
        if ($dates === null) {
            return self::FAILURE;
        }

        [$fromDate, $toDate] = $dates;

        $this->info("Aggregiere Tracking-Daten: {$fromDate} bis {$toDate}");
        $this->newLine();

        $totalEvents = 0;
        $totalCompanies = 0;

        foreach ($tenants as $tenant) {
            [$events, $companies] = $this->aggregateForTenant($tenant, $fromDate, $toDate);
            $totalEvents += $events;
            $totalCompanies += $companies;

            if ($this->option('cleanup')) {
                $this->cleanupForTenant($tenant);
            }
        }

        $this->newLine();
        $this->info("Fertig: {$totalEvents} Events aggregiert, {$totalCompanies} Company-Tage aktualisiert.");

        return self::SUCCESS;
    }

    private function resolveDateRange(): ?array
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $date = $this->option('date');

        if ($from && $to) {
            try {
                $fromDate = Carbon::parse($from)->format('Y-m-d');
                $toDate = Carbon::parse($to)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->error("Ungültiges Datumsformat: {$e->getMessage()}");
                return null;
            }

            if ($fromDate > $toDate) {
                $this->error("--from ({$fromDate}) muss vor --to ({$toDate}) liegen.");
                return null;
            }

            return [$fromDate, $toDate];
        }

        if ($date) {
            try {
                $d = Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->error("Ungültiges Datum: {$e->getMessage()}");
                return null;
            }
            return [$d, $d];
        }

        // Default: yesterday
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        return [$yesterday, $yesterday];
    }

    private function aggregateForTenant(Tenant $tenant, string $fromDate, string $toDate): array
    {
        $eventsProcessed = 0;
        $companiesUpdated = 0;

        $tenant->run(function () use ($fromDate, $toDate, &$eventsProcessed, &$companiesUpdated, $tenant) {
            // Single aggregation query — one pass over the data
            $rows = DB::connection('tenant')
                ->table('tracking_events')
                ->select(DB::raw("
                    company_id,
                    DATE(created_at) as event_date,
                    SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as page_views,
                    SUM(CASE WHEN event_type = 'contact_click' AND contact_type = 'phone' THEN 1 ELSE 0 END) as contact_clicks_phone,
                    SUM(CASE WHEN event_type = 'contact_click' AND contact_type = 'email' THEN 1 ELSE 0 END) as contact_clicks_email,
                    SUM(CASE WHEN event_type = 'contact_click' AND contact_type = 'website' THEN 1 ELSE 0 END) as contact_clicks_website,
                    SUM(CASE WHEN event_type = 'contact_click' AND contact_type = 'map' THEN 1 ELSE 0 END) as contact_clicks_map,
                    SUM(CASE WHEN event_type = 'search_impression' THEN 1 ELSE 0 END) as search_impressions,
                    COUNT(*) as total_events
                "))
                ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$fromDate, $toDate])
                ->groupBy('company_id', DB::raw('DATE(created_at)'))
                ->get();

            if ($rows->isEmpty()) {
                $this->line("  {$tenant->name}: keine Events im Zeitraum.");
                return;
            }

            foreach ($rows as $row) {
                $eventsProcessed += $row->total_events;

                DB::connection('tenant')
                    ->table('tracking_daily_stats')
                    ->updateOrInsert(
                        [
                            'company_id' => $row->company_id,
                            'date' => $row->event_date,
                        ],
                        [
                            'page_views' => $row->page_views,
                            'contact_clicks_phone' => $row->contact_clicks_phone,
                            'contact_clicks_email' => $row->contact_clicks_email,
                            'contact_clicks_website' => $row->contact_clicks_website,
                            'contact_clicks_map' => $row->contact_clicks_map,
                            'search_impressions' => $row->search_impressions,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                $companiesUpdated++;
            }

            $this->info("  {$tenant->name}: {$eventsProcessed} Events → {$companiesUpdated} Tageseinträge");
        });

        return [$eventsProcessed, $companiesUpdated];
    }

    private function cleanupForTenant(Tenant $tenant): void
    {
        $retentionDays = (int) $this->option('retention-days');
        $cutoffDate = Carbon::now()->subDays($retentionDays)->format('Y-m-d');

        $tenant->run(function () use ($cutoffDate, $retentionDays, $tenant) {
            $deleted = DB::connection('tenant')
                ->table('tracking_events')
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            if ($deleted > 0) {
                $this->line("  {$tenant->name}: {$deleted} Roh-Events älter als {$retentionDays} Tage gelöscht.");
            }
        });
    }
}
