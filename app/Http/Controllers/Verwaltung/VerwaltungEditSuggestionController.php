<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungEditSuggestionController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_OWN_REVIEWS);

        $this->setBreadcrumbs([
            ['label' => 'Änderungsvorschläge'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.edit-suggestions.index', compact('navigationItems'));
    }
}
