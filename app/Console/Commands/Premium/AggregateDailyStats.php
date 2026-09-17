<?php

namespace App\Console\Commands\Premium;

use App\Constants\CompanyEventType;
use App\Models\Portal\CompanyEvent;
use App\Models\Portal\CompanyStatsDaily;
use App\Services\Premium\CompanyRankingResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Verdichtet die Rohevents eines Tages in company_stats_daily (#15) und
 * loescht Rohevents nach Ablauf der Aufbewahrung.
 *
 * Laeuft im Tenant-Kontext: php artisan tenants:run stats:aggregate-daily
 * (naechtlich im Scheduler). Wiederholbar: bestehende Tageszeilen werden
 * ueberschrieben. Zeilen entstehen nur fuer Betriebe mit mindestens einem Event.
 */
class AggregateDailyStats extends Command
{
    protected $signature = 'stats:aggregate-daily
        {--date= : Tag (YYYY-MM-DD), Standard: gestern}
        {--no-cleanup : Rohevents nicht loeschen}';

    protected $description = 'Aggregiert die Betriebsstatistik des Vortags je Betrieb und loescht alte Rohevents (im Tenant-Kontext)';

    private const CHUNK = 500;

    public function handle(CompanyRankingResolver $ranking): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext: php artisan tenants:run stats:aggregate-daily');

            return self::FAILURE;
        }

        try {
            $day = $this->option('date')
                ? Carbon::createFromFormat('Y-m-d', (string) $this->option('date'))->startOfDay()
                : now()->subDay()->startOfDay();
        } catch (\Throwable) {
            $this->error('Ungueltiges Datum, erwartet YYYY-MM-DD.');

            return self::FAILURE;
        }

        $written = $this->aggregate($day, $ranking);
        $this->info("Statistik {$day->toDateString()}: {$written} Betriebe.");

        if (! $this->option('no-cleanup')) {
            $deleted = $this->cleanup();
            $this->info("Rohevents geloescht: {$deleted}.");
        }

        return self::SUCCESS;
    }

    private function aggregate(Carbon $day, CompanyRankingResolver $ranking): int
    {
        $sums = [];
        foreach (CompanyEventType::cases() as $type) {
            $sums[] = "SUM(event_type = '{$type->value}') AS {$type->column()}";
        }

        $rows = CompanyEvent::query()
            ->select('company_id')
            ->selectRaw(implode(', ', $sums))
            ->where('occurred_at', '>=', $day)
            ->where('occurred_at', '<', $day->copy()->addDay())
            ->groupBy('company_id')
            ->toBase()
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $positions = $ranking->positions($rows->pluck('company_id')->map(fn ($id) => (int) $id)->all());
        $now = now();
        $columns = array_map(fn (CompanyEventType $type) => $type->column(), CompanyEventType::cases());

        foreach ($rows->chunk(self::CHUNK) as $chunk) {
            $records = $chunk->map(function ($row) use ($day, $positions, $columns, $now) {
                $record = [
                    'company_id' => (int) $row->company_id,
                    'date' => $day->toDateString(),
                    'ranking_position' => $positions[(int) $row->company_id] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($columns as $column) {
                    $record[$column] = (int) $row->{$column};
                }

                return $record;
            })->values()->all();

            CompanyStatsDaily::query()->upsert(
                $records,
                ['company_id', 'date'],
                [...$columns, 'ranking_position', 'updated_at'],
            );
        }

        return $rows->count();
    }

    private function cleanup(): int
    {
        $cutoff = now()->subDays((int) config('premium.stats.raw_retention_days', 14))->startOfDay();
        $deleted = 0;

        do {
            $batch = CompanyEvent::query()
                ->where('occurred_at', '<', $cutoff)
                ->limit(5000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
