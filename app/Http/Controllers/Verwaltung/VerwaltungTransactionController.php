<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungTransactionController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_TRANSACTIONS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Zahlungen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.transactions.index', compact('navigationItems'));
    }
}
