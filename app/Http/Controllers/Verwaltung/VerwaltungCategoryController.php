<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Category;
use App\Services\TenantPermissionService;

class VerwaltungCategoryController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Kategorien'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.categories.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Kategorien', 'url' => route('verwaltung.categories.index')],
            ['label' => 'Neue Kategorie'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.categories.create', compact('navigationItems'));
    }

    public function edit(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $category = Category::findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Kategorien', 'url' => route('verwaltung.categories.index')],
            ['label' => $category->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.categories.edit', compact('navigationItems', 'category'));
    }

    public function destroy(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $category = Category::findOrFail($id);

        // Prevent deleting categories with children
        if ($category->children()->exists()) {
            return redirect()
                ->route('verwaltung.categories.index')
                ->with('error', "Kategorie \"{$category->name}\" kann nicht gelöscht werden — sie hat Unterkategorien.");
        }

        $name = $category->name;
        $category->companies()->detach();
        $category->delete();

        return redirect()
            ->route('verwaltung.categories.index')
            ->with('success', "Kategorie \"{$name}\" wurde gelöscht.");
    }
}
