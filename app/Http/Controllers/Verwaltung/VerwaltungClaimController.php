<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungClaimController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_CLAIMS);

        $this->setBreadcrumbs([
            ['label' => 'Claim-Anträge'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.claims.index', compact('navigationItems'));
    }
}
