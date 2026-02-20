<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Company;
use App\Services\TenantPermissionService;

class VerwaltungCompanyController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_OWN_COMPANY);

        $this->setBreadcrumbs([
            ['label' => 'Firmen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.companies.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_CREATE_COMPANY);

        $this->setBreadcrumbs([
            ['label' => 'Firmen', 'url' => route('verwaltung.companies.index')],
            ['label' => 'Neue Firma'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.companies.create', compact('navigationItems'));
    }

    public function edit(int $id)
    {
        $company = $this->resolveCompany($id);
        $this->authorizeCompanyAccess($company, TenancyPermissionConstants::PERMISSION_UPDATE_OWN_COMPANY);

        $this->setBreadcrumbs([
            ['label' => 'Firmen', 'url' => route('verwaltung.companies.index')],
            ['label' => $company->name],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.companies.edit', compact('navigationItems', 'company'));
    }

    public function destroy(int $id)
    {
        $company = $this->resolveCompany($id);
        $this->authorizeCompanyAccess($company, TenancyPermissionConstants::PERMISSION_DELETE_OWN_COMPANY);

        $company->openingHours()->delete();
        $company->categories()->detach();
        $company->clearMediaCollection('logo');
        $company->clearMediaCollection('cover');
        $company->clearMediaCollection('gallery');
        $company->delete();

        return redirect()
            ->route('verwaltung.companies.index')
            ->with('success', "Firma \"{$company->name}\" wurde gelöscht.");
    }

    /**
     * Resolve a company by ID, abort 404 if not found.
     */
    private function resolveCompany(int $id): Company
    {
        $company = Company::find($id);

        if (! $company) {
            abort(404, 'Firma nicht gefunden.');
        }

        return $company;
    }

    /**
     * Check if the current user can access this specific company.
     * Admins can access all, non-admins only their own.
     */
    private function authorizeCompanyAccess(Company $company, string $permission): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($company->user_id !== $user->id) {
            abort(403, 'Keine Berechtigung für diese Firma.');
        }

        $this->requirePermission($permission);
    }
}
