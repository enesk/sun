<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Statistik-Dashboard im Betriebsbereich (#16), eingebettet in
 * /firmenprofil/statistiken. Daten aus company_stats_daily (#15), also bis
 * einschliesslich gestern. Ohne Feature statistics zeigt die Komponente
 * feste Beispielwerte (verschwommen) mit Freischalt-Hinweis und liest keine
 * echten Zahlen.
 */
class Statistics extends Component
{
    use HasEntitlements;

    public const PERIODS = [7, 30, 90];

    #[Locked]
    public int $companyId = 0;

    #[Url(as: 'tage')]
    public int $days = 30;

    #[Url(as: 'kennzahl')]
    public string $metric = 'profile_views';

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
        $this->sanitize();
    }

    public function setPeriod(int $days): void
    {
        $this->days = $days;
        $this->sanitize();
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
        $this->sanitize();
    }

    public function render(CompanyStatisticsService $stats): View
    {
        $company = $this->company();
        $allowed = $this->can(PremiumFeature::Statistics);

        $to = Carbon::yesterday();
        $from = $to->copy()->subDays($this->days - 1);

        $data = $allowed
            ? $this->liveData($stats, $company, $from, $to)
            : $this->sampleData($from, $to);

        $chart = [
            'labels' => array_map(fn (array $day) => Carbon::parse($day['date'])->format('d.m.'), $data['daily']),
            'values' => array_map(fn (array $day) => $day[$this->metric], $data['daily']),
            'label' => __("portal.owner.statistics.metrics.{$this->metric}"),
        ];

        // Bei Livewire-Updates bekommt das Chart-Modul die neuen Werte per Event
        $this->dispatch('stats-chart', chart: $chart);

        return view('livewire.portal.company.dashboard.statistics', [
            'company' => $company,
            'allowed' => $allowed,
            'from' => $from,
            'to' => $to,
            'totals' => $data['totals'],
            'changes' => $data['changes'],
            'ranking' => $data['ranking'],
            'average' => $data['average'],
            'chart' => $chart,
            'daily' => $data['daily'],
            'metrics' => CompanyStatisticsService::METRICS,
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    /**
     * @return array<string, mixed>
     */
    private function liveData(CompanyStatisticsService $stats, Company $company, Carbon $from, Carbon $to): array
    {
        $totals = $stats->totals($company, $from, $to);
        $previous = $stats->totals($company, $from->copy()->subDays($this->days), $from->copy()->subDay());

        $changes = [];
        foreach ($totals as $metric => $value) {
            $changes[$metric] = CompanyStatisticsService::change($value, $previous[$metric]);
        }

        return [
            'totals' => $totals,
            'changes' => $changes,
            'ranking' => $stats->ranking($company),
            'average' => $stats->cityAverage($company, $from, $to),
            'daily' => $stats->daily($company, $from, $to),
        ];
    }

    /**
     * Feste Beispielwerte fuer Betriebe ohne Freischaltung (SUN-PREM-016).
     *
     * @return array<string, mixed>
     */
    private function sampleData(Carbon $from, Carbon $to): array
    {
        /** @var City|null $city */
        $city = $this->company()->city;
        $daily = [];
        $i = 0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay(), $i++) {
            $base = 14 + (int) round(6 * sin($i / 3)) + ($i % 5);
            $daily[] = [
                'date' => $day->toDateString(),
                'profile_views' => $base,
                'phone_clicks' => intdiv($base, 6),
                'website_clicks' => intdiv($base, 8),
                'quote_requests' => intdiv($base, 12),
                'list_impressions' => $base * 9,
            ];
        }

        $totals = [];
        foreach (CompanyStatisticsService::METRICS as $metric) {
            $totals[$metric] = array_sum(array_column($daily, $metric));
        }

        return [
            'totals' => $totals,
            'changes' => array_fill_keys(CompanyStatisticsService::METRICS, 12.0),
            'ranking' => ['position' => 4, 'total' => 23, 'city' => (string) $city?->name],
            'average' => array_map(fn (int $value) => round($value * 0.7, 1), $totals),
            'daily' => $daily,
        ];
    }

    private function sanitize(): void
    {
        if (! in_array($this->days, self::PERIODS, true)) {
            $this->days = 30;
        }

        if (! in_array($this->metric, CompanyStatisticsService::METRICS, true)) {
            $this->metric = 'profile_views';
        }
    }

    private function company(): Company
    {
        return $this->company ??= Company::ownedBy((int) Auth::id())
            ->when($this->companyId > 0, fn ($query) => $query->whereKey($this->companyId))
            ->firstOrFail();
    }
}
