<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungUserController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_TEAM);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Benutzer'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.users.index', compact('navigationItems'));
    }
}
