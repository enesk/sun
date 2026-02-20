<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;

class VerwaltungInvitationController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Einladungen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.invitations.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Einladungen', 'url' => route('verwaltung.invitations.index')],
            ['label' => 'Neue Einladung'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.invitations.create', compact('navigationItems'));
    }
}
