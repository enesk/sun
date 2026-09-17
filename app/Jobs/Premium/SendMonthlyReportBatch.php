<?php

declare(strict_types=1);

namespace App\Jobs\Premium;

use App\Enums\PremiumFeature;
use App\Mail\Company\MonthlyStatsReportMail;
use App\Models\Portal\Company;
use App\Models\User;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyStatisticsService;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Verschickt den Monatsreport (#16) an bis zu 200 Betriebe
 * (SendMonthlyReports::BATCH_SIZE). Der Tenant reist ueber den
 * QueueTenancyBootstrapper mit.
 *
 * Jeder Betrieb wird nach erfolgreichem Versand mit
 * monthly_report_sent_period markiert; ein Retry des Jobs ueberspringt
 * damit bereits versorgte Betriebe. Freischaltung und Abmeldung werden
 * beim Versand erneut geprueft.
 */
class SendMonthlyReportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 600;

    /**
     * @param  list<int>  $companyIds
     * @param  string  $period  Berichtsmonat YYYY-MM
     */
    public function __construct(
        public array $companyIds,
        public string $period,
    ) {}

    public function handle(CompanyStatisticsService $stats, CompanyEntitlementService $entitlements): void
    {
        $from = Carbon::createFromFormat('!Y-m', $this->period)->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousTo = $previousFrom->copy()->endOfMonth()->startOfDay();
        $monthLabel = $from->locale('de')->isoFormat('MMMM YYYY');
        $branding = TenantMailBranding::current();

        $companies = Company::query()
            ->with(['owner:id,name,email', 'city:id,name'])
            ->whereIn('id', $this->companyIds)
            ->whereNull('monthly_report_opted_out_at')
            ->where(fn ($query) => $query->whereNull('monthly_report_sent_period')->orWhere('monthly_report_sent_period', '!=', $this->period))
            ->get();

        foreach ($companies as $company) {
            if (! $entitlements->can($company, PremiumFeature::MonthlyReport)) {
                continue;
            }

            /** @var User|null $owner */
            $owner = $company->owner;
            $recipient = $owner?->email ?: $company->email;

            if (! is_string($recipient) || $recipient === '') {
                continue;
            }

            $totals = $stats->totals($company, $from, $to);
            $previous = $stats->totals($company, $previousFrom, $previousTo);
            $changes = [];

            foreach ($totals as $metric => $value) {
                $changes[$metric] = CompanyStatisticsService::change($value, $previous[$metric]);
            }

            try {
                Mail::to($recipient)->send(new MonthlyStatsReportMail(
                    companyName: (string) $company->name,
                    recipientName: $owner?->name,
                    monthLabel: $monthLabel,
                    totals: $totals,
                    changes: $changes,
                    ranking: $stats->ranking($company),
                    dashboardUrl: $branding->url('/firmenprofil/statistiken'),
                    settingsUrl: $branding->url('/firmenprofil/einstellungen#monatsbericht'),
                ));
            } catch (\Throwable $e) {
                Log::warning('Monatsreport nicht versendet', [
                    'company_id' => $company->id,
                    'period' => $this->period,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $company->forceFill(['monthly_report_sent_period' => $this->period])->saveQuietly();
        }
    }
}
