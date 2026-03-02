<?php

namespace App\Services;

use App\Models\Portal\TrackingDailyStat;
use App\Models\Portal\TrackingEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistik-Service für Dashboard-Widgets.
 *
 * Liest aus tracking_daily_stats (aggregiert von #173) und
 * tracking_events (Rohdaten für Referrer, Suchbegriffe).
 *
 * Konsumenten:
 * - Owner Dashboard (Thomas sieht SEINE Firma)
 * - Verwaltung Dashboard (Admin sieht alle Firmen)
 * - Livewire-Widgets (#175)
 */
class StatisticsService
{
    // ══════════════════════════════════════════════════════════
    // PERIOD RESOLUTION
    // ══════════════════════════════════════════════════════════

    /**
     * Periode in Start/End-Datum auflösen.
     *
     * Unterstützt: 7d, 30d, 90d, 12m, oder custom (from/to).
     *
     * @return array{from: Carbon, to: Carbon, prev_from: Carbon, prev_to: Carbon}
     */
    public function resolvePeriod(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = Carbon::today();

        if ($from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            $days = $start->diffInDays($end) + 1;

            return [
                'from' => $start,
                'to' => $end,
                'prev_from' => $start->copy()->subDays($days),
                'prev_to' => $start->copy()->subDay(),
            ];
        }

        return match ($period) {
            '7d' => [
                'from' => $now->copy()->subDays(6),
                'to' => $now,
                'prev_from' => $now->copy()->subDays(13),
                'prev_to' => $now->copy()->subDays(7),
            ],
            '90d' => [
                'from' => $now->copy()->subDays(89),
                'to' => $now,
                'prev_from' => $now->copy()->subDays(179),
                'prev_to' => $now->copy()->subDays(90),
            ],
            '12m' => [
                'from' => $now->copy()->subMonths(12)->startOfMonth(),
                'to' => $now,
                'prev_from' => $now->copy()->subMonths(24)->startOfMonth(),
                'prev_to' => $now->copy()->subMonths(12)->subDay(),
            ],
            default => [ // 30d
                'from' => $now->copy()->subDays(29),
                'to' => $now,
                'prev_from' => $now->copy()->subDays(59),
                'prev_to' => $now->copy()->subDays(30),
            ],
        };
    }

    // ══════════════════════════════════════════════════════════
    // COMPANY SUMMARY (Thomas: seine Firma)
    // ══════════════════════════════════════════════════════════

    /**
     * KPI-Summary für eine einzelne Firma.
     *
     * @return array{
     *   page_views: int,
     *   contact_clicks: int,
     *   contact_breakdown: array{phone: int, email: int, website: int, map: int},
     *   search_impressions: int,
     *   page_views_prev: int,
     *   contact_clicks_prev: int,
     *   search_impressions_prev: int,
     *   page_views_change: float|null,
     *   contact_clicks_change: float|null,
     *   search_impressions_change: float|null,
     * }
     */
    public function getCompanySummary(int $companyId, string $period = '30d'): array
    {
        $dates = $this->resolvePeriod($period);

        $current = $this->sumStats($companyId, $dates['from'], $dates['to']);
        $previous = $this->sumStats($companyId, $dates['prev_from'], $dates['prev_to']);

        return [
            'page_views' => $current['page_views'],
            'contact_clicks' => $current['contact_clicks'],
            'contact_breakdown' => $current['contact_breakdown'],
            'search_impressions' => $current['search_impressions'],
            'page_views_prev' => $previous['page_views'],
            'contact_clicks_prev' => $previous['contact_clicks'],
            'search_impressions_prev' => $previous['search_impressions'],
            'page_views_change' => $this->percentChange($current['page_views'], $previous['page_views']),
            'contact_clicks_change' => $this->percentChange($current['contact_clicks'], $previous['contact_clicks']),
            'search_impressions_change' => $this->percentChange($current['search_impressions'], $previous['search_impressions']),
            'period' => $period,
            'from' => $dates['from']->toDateString(),
            'to' => $dates['to']->toDateString(),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GLOBAL SUMMARY (Admin: alle Firmen)
    // ══════════════════════════════════════════════════════════

    /**
     * KPI-Summary über alle Firmen eines Tenants.
     */
    public function getGlobalSummary(string $period = '30d'): array
    {
        $dates = $this->resolvePeriod($period);

        $current = $this->sumStats(null, $dates['from'], $dates['to']);
        $previous = $this->sumStats(null, $dates['prev_from'], $dates['prev_to']);

        return [
            'page_views' => $current['page_views'],
            'contact_clicks' => $current['contact_clicks'],
            'contact_breakdown' => $current['contact_breakdown'],
            'search_impressions' => $current['search_impressions'],
            'page_views_change' => $this->percentChange($current['page_views'], $previous['page_views']),
            'contact_clicks_change' => $this->percentChange($current['contact_clicks'], $previous['contact_clicks']),
            'search_impressions_change' => $this->percentChange($current['search_impressions'], $previous['search_impressions']),
            'period' => $period,
            'from' => $dates['from']->toDateString(),
            'to' => $dates['to']->toDateString(),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // DAILY TREND (Charts)
    // ══════════════════════════════════════════════════════════

    /**
     * Tägliche Datenpunkte für Charts.
     *
     * @return Collection<int, array{
     *   date: string,
     *   page_views: int,
     *   contact_clicks: int,
     *   search_impressions: int,
     * }>
     */
    public function getDailyTrend(int $companyId, string $period = '30d'): Collection
    {
        $dates = $this->resolvePeriod($period);

        $stats = TrackingDailyStat::forCompany($companyId)
            ->inPeriod($dates['from']->toDateString(), $dates['to']->toDateString())
            ->orderBy('date')
            ->get();

        // Alle Tage im Bereich füllen (auch Tage ohne Daten → 0)
        return $this->fillDailyGaps($stats, $dates['from'], $dates['to']);
    }

    /**
     * Täglicher Trend über alle Firmen (Admin).
     */
    public function getGlobalDailyTrend(string $period = '30d'): Collection
    {
        $dates = $this->resolvePeriod($period);

        $stats = TrackingDailyStat::query()
            ->select([
                'date',
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(contact_clicks_phone + contact_clicks_email + contact_clicks_website + contact_clicks_map) as contact_clicks'),
                DB::raw('SUM(search_impressions) as search_impressions'),
            ])
            ->whereBetween('date', [$dates['from']->toDateString(), $dates['to']->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->fillDailyGaps($stats, $dates['from'], $dates['to']);
    }

    // ══════════════════════════════════════════════════════════
    // WEEKLY TREND
    // ══════════════════════════════════════════════════════════

    /**
     * Wöchentliche Aggregation für eine Firma.
     *
     * @return Collection<int, array{
     *   week: string,
     *   week_start: string,
     *   page_views: int,
     *   contact_clicks: int,
     *   search_impressions: int,
     * }>
     */
    public function getWeeklyTrend(int $companyId, int $weeks = 12): Collection
    {
        $to = Carbon::today();
        $from = $to->copy()->subWeeks($weeks)->startOfWeek(Carbon::MONDAY);

        return TrackingDailyStat::forCompany($companyId)
            ->inPeriod($from->toDateString(), $to->toDateString())
            ->select([
                DB::raw('YEARWEEK(date, 1) as week'),
                DB::raw('MIN(date) as week_start'),
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(contact_clicks_phone + contact_clicks_email + contact_clicks_website + contact_clicks_map) as contact_clicks'),
                DB::raw('SUM(search_impressions) as search_impressions'),
            ])
            ->groupBy(DB::raw('YEARWEEK(date, 1)'))
            ->orderBy('week')
            ->get()
            ->map(fn ($row) => [
                'week' => $row->week,
                'week_start' => $row->week_start,
                'page_views' => (int) $row->page_views,
                'contact_clicks' => (int) $row->contact_clicks,
                'search_impressions' => (int) $row->search_impressions,
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // MONTHLY TREND
    // ══════════════════════════════════════════════════════════

    /**
     * Monatliche Aggregation für eine Firma.
     */
    public function getMonthlyTrend(int $companyId, int $months = 12): Collection
    {
        $to = Carbon::today();
        $from = $to->copy()->subMonths($months)->startOfMonth();

        return TrackingDailyStat::forCompany($companyId)
            ->inPeriod($from->toDateString(), $to->toDateString())
            ->select([
                DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(contact_clicks_phone + contact_clicks_email + contact_clicks_website + contact_clicks_map) as contact_clicks'),
                DB::raw('SUM(search_impressions) as search_impressions'),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'page_views' => (int) $row->page_views,
                'contact_clicks' => (int) $row->contact_clicks,
                'search_impressions' => (int) $row->search_impressions,
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // TOP REFERRERS (Rohdaten)
    // ══════════════════════════════════════════════════════════

    /**
     * Top Referrer-Domains für eine Firma.
     * Liest aus tracking_events (Rohdaten), da referrer nicht aggregiert wird.
     */
    public function getTopReferrers(int $companyId, string $period = '30d', int $limit = 10): Collection
    {
        $dates = $this->resolvePeriod($period);

        return TrackingEvent::forCompany($companyId)
            ->pageViews()
            ->inPeriod($dates['from']->toDateTimeString(), $dates['to']->endOfDay()->toDateTimeString())
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->select([
                DB::raw($this->referrerDomainExpression() . ' as domain'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('domain')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'domain' => $row->domain,
                'count' => (int) $row->count,
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // TOP SEARCH QUERIES
    // ══════════════════════════════════════════════════════════

    /**
     * Top Suchbegriffe die zu Impressionen für eine Firma geführt haben.
     */
    public function getTopSearchQueries(int $companyId, string $period = '30d', int $limit = 10): Collection
    {
        $dates = $this->resolvePeriod($period);

        return TrackingEvent::forCompany($companyId)
            ->searchImpressions()
            ->inPeriod($dates['from']->toDateTimeString(), $dates['to']->endOfDay()->toDateTimeString())
            ->whereNotNull('search_query')
            ->where('search_query', '!=', '')
            ->select([
                'search_query',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'query' => $row->search_query,
                'count' => (int) $row->count,
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // TOP COMPANIES (Admin)
    // ══════════════════════════════════════════════════════════

    /**
     * Top-Firmen nach Aufrufen (für Admin-Dashboard).
     */
    public function getTopCompanies(string $period = '30d', int $limit = 20): Collection
    {
        $dates = $this->resolvePeriod($period);

        return TrackingDailyStat::query()
            ->inPeriod($dates['from']->toDateString(), $dates['to']->toDateString())
            ->join('companies', 'companies.id', '=', 'tracking_daily_stats.company_id')
            ->select([
                'companies.id',
                'companies.name',
                'companies.slug',
                'companies.is_premium',
                DB::raw('SUM(tracking_daily_stats.page_views) as page_views'),
                DB::raw('SUM(tracking_daily_stats.contact_clicks_phone + tracking_daily_stats.contact_clicks_email + tracking_daily_stats.contact_clicks_website + tracking_daily_stats.contact_clicks_map) as contact_clicks'),
                DB::raw('SUM(tracking_daily_stats.search_impressions) as search_impressions'),
            ])
            ->groupBy('companies.id', 'companies.name', 'companies.slug', 'companies.is_premium')
            ->orderByDesc('page_views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'is_premium' => (bool) $row->is_premium,
                'page_views' => (int) $row->page_views,
                'contact_clicks' => (int) $row->contact_clicks,
                'search_impressions' => (int) $row->search_impressions,
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * Aggregierte Stats aus tracking_daily_stats summieren.
     *
     * @param int|null $companyId Null = alle Firmen (global)
     */
    private function sumStats(?int $companyId, Carbon $from, Carbon $to): array
    {
        $query = TrackingDailyStat::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $result = $query->selectRaw('
            COALESCE(SUM(page_views), 0) as page_views,
            COALESCE(SUM(contact_clicks_phone), 0) as phone,
            COALESCE(SUM(contact_clicks_email), 0) as email,
            COALESCE(SUM(contact_clicks_website), 0) as website,
            COALESCE(SUM(contact_clicks_map), 0) as map_clicks,
            COALESCE(SUM(search_impressions), 0) as search_impressions
        ')->first();

        $phone = (int) $result->phone;
        $email = (int) $result->email;
        $website = (int) $result->website;
        $map = (int) $result->map_clicks;

        return [
            'page_views' => (int) $result->page_views,
            'contact_clicks' => $phone + $email + $website + $map,
            'contact_breakdown' => [
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'map' => $map,
            ],
            'search_impressions' => (int) $result->search_impressions,
        ];
    }

    /**
     * Prozentuale Veränderung berechnen.
     */
    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0 && $current === 0) {
            return null;
        }

        if ($previous === 0) {
            return 100.0; // Von 0 auf etwas = +100%
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Tägliche Datenpunkte mit Lücken füllen (Tage ohne Events → 0).
     */
    private function fillDailyGaps(Collection $stats, Carbon $from, Carbon $to): Collection
    {
        $indexed = $stats->keyBy(fn ($row) => $row instanceof TrackingDailyStat
            ? $row->date->toDateString()
            : ($row->date ?? '')
        );

        $result = collect();
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $dateStr = $cursor->toDateString();
            $row = $indexed->get($dateStr);

            if ($row) {
                $pageViews = $row instanceof TrackingDailyStat
                    ? $row->page_views
                    : (int) $row->page_views;
                $contactClicks = $row instanceof TrackingDailyStat
                    ? $row->total_contact_clicks
                    : (int) ($row->contact_clicks ?? 0);
                $searchImpressions = $row instanceof TrackingDailyStat
                    ? $row->search_impressions
                    : (int) $row->search_impressions;

                $result->push([
                    'date' => $dateStr,
                    'page_views' => $pageViews,
                    'contact_clicks' => $contactClicks,
                    'search_impressions' => $searchImpressions,
                ]);
            } else {
                $result->push([
                    'date' => $dateStr,
                    'page_views' => 0,
                    'contact_clicks' => 0,
                    'search_impressions' => 0,
                ]);
            }

            $cursor->addDay();
        }

        return $result;
    }

    /**
     * SQL-Expression um die Domain aus einem Referrer-URL zu extrahieren.
     * MySQL-spezifisch.
     */
    private function referrerDomainExpression(): string
    {
        // Entfernt http(s)://, extrahiert die Domain (bis zum ersten /)
        return "SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '/', 1), '?', 1)";
    }
}
