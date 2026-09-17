<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyStatsDaily;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Liest die Tageswerte aus company_stats_daily (#15) fuer das Statistik-
 * Dashboard und den Monatsreport (#16). Freischaltungen prueft der Aufrufer
 * ueber CompanyEntitlementService.
 */
class CompanyStatisticsService
{
    /**
     * Kennzahlen, die Dashboard und Report zeigen, in Anzeige-Reihenfolge.
     */
    public const METRICS = [
        'profile_views',
        'phone_clicks',
        'website_clicks',
        'quote_requests',
        'list_impressions',
    ];

    /**
     * Stadt-Durchschnitt nur, wenn ausser dem Betrieb selbst mindestens so
     * viele aktive Betriebe in der Stadt sind (keine Rueckschluesse auf Einzelne).
     */
    private const MIN_CITY_PEERS = 3;

    public function __construct(
        private CompanyRankingResolver $ranking,
    ) {}

    /**
     * @return array<string, int>
     */
    public function totals(Company $company, Carbon $from, Carbon $to): array
    {
        $row = CompanyStatsDaily::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($this->sumSelect())
            ->toBase()
            ->first();

        return $this->normalize($row);
    }

    /**
     * Tageswerte mit Nullen fuer Tage ohne Zeile.
     *
     * @return list<array<string, int|string>> je Tag: date (Y-m-d) plus Kennzahlen
     */
    public function daily(Company $company, Carbon $from, Carbon $to): array
    {
        $rows = CompanyStatsDaily::query()
            ->where('company_id', $company->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', ...self::METRICS])
            ->keyBy(fn (CompanyStatsDaily $row) => $row->date->toDateString());

        $days = [];

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $entry = ['date' => $key];

            foreach (self::METRICS as $metric) {
                $entry[$metric] = (int) ($rows->get($key)?->{$metric} ?? 0);
            }

            $days[] = $entry;
        }

        return $days;
    }

    /**
     * Aktueller Platz in der Stadtliste und Zahl der aktiven Betriebe dort.
     *
     * @return array{position: int, total: int, city: string}|null
     */
    public function ranking(Company $company): ?array
    {
        if ($company->city_id === null) {
            return null;
        }

        $position = $this->ranking->positions([(int) $company->getKey()])[(int) $company->getKey()] ?? null;

        if ($position === null) {
            return null;
        }

        /** @var City|null $city */
        $city = $company->city;

        return [
            'position' => $position,
            'total' => $this->activeInCity((int) $company->city_id),
            'city' => (string) $city?->name,
        ];
    }

    /**
     * Durchschnitt je aktivem Betrieb der Stadt im Zeitraum, nur als Aggregat.
     * Betriebe ohne Tageszeile zaehlen mit 0.
     *
     * @return array<string, float>|null null bei zu wenigen Betrieben
     */
    public function cityAverage(Company $company, Carbon $from, Carbon $to): ?array
    {
        if ($company->city_id === null) {
            return null;
        }

        $count = $this->activeInCity((int) $company->city_id);

        if ($count - 1 < self::MIN_CITY_PEERS) {
            return null;
        }

        $row = DB::connection((new CompanyStatsDaily)->getConnectionName())
            ->table('company_stats_daily')
            ->join('companies', 'companies.id', '=', 'company_stats_daily.company_id')
            ->where('companies.city_id', $company->city_id)
            ->where('companies.is_active', true)
            ->whereBetween('company_stats_daily.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($this->sumSelect('company_stats_daily.'))
            ->first();

        return array_map(fn (int $sum) => round($sum / $count, 1), $this->normalize($row));
    }

    /**
     * Veraenderung in Prozent, null ohne Vergleichswert.
     */
    public static function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function activeInCity(int $cityId): int
    {
        return Company::query()->where('city_id', $cityId)->where('is_active', true)->count();
    }

    private function sumSelect(string $prefix = ''): string
    {
        return implode(', ', array_map(
            fn (string $metric) => "COALESCE(SUM({$prefix}{$metric}), 0) AS {$metric}",
            self::METRICS,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function normalize(?object $row): array
    {
        $totals = [];

        foreach (self::METRICS as $metric) {
            $totals[$metric] = (int) ($row?->{$metric} ?? 0);
        }

        return $totals;
    }
}
