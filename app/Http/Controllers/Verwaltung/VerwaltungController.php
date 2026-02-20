<?php

namespace App\Http\Controllers\Verwaltung;

use App\Services\TenantPermissionService;

/**
 * Dashboard overview controller — the landing page after login.
 *
 * The heavy lifting (KPIs, recent activity) is done by Livewire widgets:
 * - StatsOverview — KPI cards with period filter
 * - RecentReviews — Latest reviews with quick-approve/reject
 * - RecentCompanies — Latest company registrations
 */
class VerwaltungController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->setBreadcrumbs([
            ['label' => 'Übersicht'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.index', compact('navigationItems'));
    }
}
