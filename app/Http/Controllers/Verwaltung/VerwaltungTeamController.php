<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Team;
use App\Services\TenantPermissionService;

class VerwaltungTeamController extends VerwaltungBaseController
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
            ['label' => 'Teams'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.teams.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_TEAM);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Teams', 'url' => route('verwaltung.teams.index')],
            ['label' => 'Erstellen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.teams.create', compact('navigationItems'));
    }

    public function edit(string $uuid)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_TEAM);

        $team = Team::where('uuid', $uuid)
            ->where('tenant_id', $this->tenant()->id)
            ->firstOrFail();

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Teams', 'url' => route('verwaltung.teams.index')],
            ['label' => $team->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.teams.edit', compact('navigationItems', 'team'));
    }
}
