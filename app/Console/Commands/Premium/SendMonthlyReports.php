<?php

declare(strict_types=1);

namespace App\Console\Commands\Premium;

use App\Enums\PremiumFeature;
use App\Jobs\Premium\SendMonthlyReportBatch;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyEntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Monatsreport (#16): reiht je Tenant die Betriebe mit Feature
 * monthly_report in Batches von hoechstens 200 als Jobs ein.
 *
 * Laeuft ueber `tenants:run premium:send-monthly-reports` am 1. des Monats.
 * Uebersprungen werden abgemeldete Betriebe und solche, deren Report fuer den
 * Monat schon raus ist (monthly_report_sent_period) — ein zweiter Lauf
 * verschickt also nichts doppelt.
 */
class SendMonthlyReports extends Command
{
    public const BATCH_SIZE = 200;

    protected $signature = 'premium:send-monthly-reports
        {--month= : Berichtsmonat (YYYY-MM), Standard: Vormonat}
        {--dry-run : Nur zaehlen, keine Jobs einreihen}';

    protected $description = 'Verschickt den Monatsreport an Pro/Premium-Betriebe (im Tenant-Kontext)';

    public function handle(CompanyEntitlementService $entitlements): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext ausfuehrbar: php artisan tenants:run premium:send-monthly-reports');

            return self::FAILURE;
        }

        try {
            $month = $this->option('month')
                ? Carbon::createFromFormat('!Y-m', (string) $this->option('month'))->startOfMonth()
                : now()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            $this->error('Ungueltiger Monat, erwartet YYYY-MM.');

            return self::FAILURE;
        }

        $period = $month->format('Y-m');
        [$featureSql, $bindings] = $entitlements->featureSql(PremiumFeature::MonthlyReport);

        $batches = 0;
        $companies = 0;

        Company::query()
            ->where('is_active', true)
            ->whereNull('monthly_report_opted_out_at')
            ->where(fn ($query) => $query->whereNull('monthly_report_sent_period')->orWhere('monthly_report_sent_period', '!=', $period))
            ->whereRaw("({$featureSql}) = 1", $bindings)
            ->select('id')
            ->chunkById(self::BATCH_SIZE, function ($chunk) use ($period, &$batches, &$companies): void {
                $ids = $chunk->pluck('id')->map(fn ($id) => (int) $id)->all();
                $companies += count($ids);
                $batches++;

                if (! $this->option('dry-run')) {
                    SendMonthlyReportBatch::dispatch($ids, $period);
                }
            });

        $this->info(sprintf(
            'Monatsreport %s: %d Betriebe in %d Batches%s.',
            $period,
            $companies,
            $batches,
            $this->option('dry-run') ? ' (dry-run, nichts eingereiht)' : ' eingereiht',
        ));

        return self::SUCCESS;
    }
}
