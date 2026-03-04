<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Job;
use App\Services\TenantPermissionService;

class VerwaltungJobController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_COMPANIES);

        $this->setBreadcrumbs([
            ['label' => 'Stellenanzeigen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.jobs.index', compact('navigationItems'));
    }

    public function show(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_COMPANIES);

        $job = Job::with(['company', 'city', 'applications'])
            ->withCount('applications')
            ->findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Stellenanzeigen', 'url' => route('verwaltung.jobs.index')],
            ['label' => $job->title],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.jobs.show', compact('navigationItems', 'job'));
    }
}
