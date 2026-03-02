<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\StatisticsService;
use App\Services\TenantPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistiken-Controller für das Admin-Dashboard (Verwaltung).
 *
 * Seiten-Routes (Blade):
 * - GET /verwaltung/statistiken — Übersicht (alle Firmen)
 * - GET /verwaltung/statistiken/firma/{id} — Detail pro Firma
 *
 * JSON-API-Routes (Livewire/AJAX):
 * - GET /verwaltung/statistiken/api/overview — Globale KPIs + Trend
 * - GET /verwaltung/statistiken/api/company/{id} — Firmen-KPIs + Trend
 * - GET /verwaltung/statistiken/api/top-companies — Top-Firmen Ranking
 */
class VerwaltungStatisticsController extends VerwaltungBaseController
{
    public function __construct(
        TenantPermissionService $permissionService,
        private readonly StatisticsService $statisticsService,
    ) {
        parent::__construct($permissionService);
    }

    // ══════════════════════════════════════════════════════════
    // BLADE PAGE ROUTES
    // ══════════════════════════════════════════════════════════

    /**
     * GET /verwaltung/statistiken — Übersichtsseite.
     */
    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::MANAGE_COMPANIES);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Statistiken'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.statistics.index', compact('navigationItems'));
    }

    /**
     * GET /verwaltung/statistiken/firma/{id} — Detail pro Firma.
     */
    public function show(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::MANAGE_COMPANIES);

        $company = \App\Models\Portal\Company::findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Statistiken', 'url' => route('verwaltung.statistics.index')],
            ['label' => $company->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.statistics.show', compact('navigationItems', 'company'));
    }

    // ══════════════════════════════════════════════════════════
    // JSON API ROUTES (für Livewire/AJAX)
    // ══════════════════════════════════════════════════════════

    /**
     * GET /verwaltung/statistiken/api/overview
     *
     * Query-Params: ?period=30d (7d|30d|90d|12m)
     *
     * Response: {
     *   summary: { page_views, contact_clicks, search_impressions, ..._change },
     *   trend: [ { date, page_views, contact_clicks, search_impressions } ],
     * }
     */
    public function apiOverview(Request $request): JsonResponse
    {
        $this->requirePermission(TenancyPermissionConstants::MANAGE_COMPANIES);

        $period = $this->validatePeriod($request->input('period', '30d'));

        $summary = $this->statisticsService->getGlobalSummary($period);
        $trend = $this->statisticsService->getGlobalDailyTrend($period);

        return response()->json([
            'summary' => $summary,
            'trend' => $trend->values(),
        ]);
    }

    /**
     * GET /verwaltung/statistiken/api/company/{id}
     *
     * Query-Params: ?period=30d
     *
     * Response: {
     *   summary: { ... },
     *   trend: [ ... ],
     *   referrers: [ { domain, count } ],
     *   search_queries: [ { query, count } ],
     *   weekly: [ { week, week_start, ... } ],
     *   monthly: [ { month, ... } ],
     * }
     */
    public function apiCompany(Request $request, int $id): JsonResponse
    {
        $this->requirePermission(TenancyPermissionConstants::MANAGE_COMPANIES);

        \App\Models\Portal\Company::findOrFail($id);

        $period = $this->validatePeriod($request->input('period', '30d'));

        $summary = $this->statisticsService->getCompanySummary($id, $period);
        $trend = $this->statisticsService->getDailyTrend($id, $period);
        $referrers = $this->statisticsService->getTopReferrers($id, $period);
        $searchQueries = $this->statisticsService->getTopSearchQueries($id, $period);
        $weekly = $this->statisticsService->getWeeklyTrend($id);
        $monthly = $this->statisticsService->getMonthlyTrend($id);

        return response()->json([
            'summary' => $summary,
            'trend' => $trend->values(),
            'referrers' => $referrers->values(),
            'search_queries' => $searchQueries->values(),
            'weekly' => $weekly->values(),
            'monthly' => $monthly->values(),
        ]);
    }

    /**
     * GET /verwaltung/statistiken/api/top-companies
     *
     * Query-Params: ?period=30d&limit=20
     */
    public function apiTopCompanies(Request $request): JsonResponse
    {
        $this->requirePermission(TenancyPermissionConstants::MANAGE_COMPANIES);

        $period = $this->validatePeriod($request->input('period', '30d'));
        $limit = min((int) $request->input('limit', 20), 100);

        $companies = $this->statisticsService->getTopCompanies($period, $limit);

        return response()->json([
            'companies' => $companies->values(),
            'period' => $period,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════

    private function validatePeriod(string $input): string
    {
        $allowed = ['7d', '30d', '90d', '12m'];

        return in_array($input, $allowed, true) ? $input : '30d';
    }
}
