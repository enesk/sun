<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungFaqController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'FAQ'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.faqs.index', compact('navigationItems'));
    }
}
