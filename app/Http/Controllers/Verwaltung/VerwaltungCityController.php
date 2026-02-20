<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\City;
use App\Services\TenantPermissionService;

class VerwaltungCityController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Städte'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.cities.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Städte', 'url' => route('verwaltung.cities.index')],
            ['label' => 'Neue Stadt'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.cities.create', compact('navigationItems'));
    }

    public function edit(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $city = City::findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Städte', 'url' => route('verwaltung.cities.index')],
            ['label' => $city->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.cities.edit', compact('navigationItems', 'city'));
    }

    public function destroy(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $city = City::findOrFail($id);

        // Prevent deleting cities with companies
        if ($city->companies()->exists()) {
            return redirect()
                ->route('verwaltung.cities.index')
                ->with('error', "Stadt \"{$city->name}\" kann nicht gelöscht werden — es gibt {$city->companies()->count()} zugeordnete Firmen.");
        }

        $name = $city->name;
        $city->delete();

        return redirect()
            ->route('verwaltung.cities.index')
            ->with('success', "Stadt \"{$name}\" wurde gelöscht.");
    }
}
