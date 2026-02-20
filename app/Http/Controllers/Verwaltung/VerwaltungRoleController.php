<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use App\Services\TenantPermissionService;

class VerwaltungRoleController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_ROLES);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Rollen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.roles.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_CREATE_ROLES);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Rollen', 'url' => route('verwaltung.roles.index')],
            ['label' => 'Erstellen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.roles.create', compact('navigationItems'));
    }

    public function edit(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_ROLES);

        $role = Role::where('id', $id)
            ->where(function ($q) {
                $q->where('tenant_id', $this->tenant()->id)
                    ->orWhereNull('tenant_id');
            })
            ->where('is_tenant_role', true)
            ->firstOrFail();

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Rollen', 'url' => route('verwaltung.roles.index')],
            ['label' => $role->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.roles.edit', compact('navigationItems', 'role'));
    }
}
