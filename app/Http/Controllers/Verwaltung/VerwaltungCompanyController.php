<?php

namespace App\Http\Controllers\Verwaltung;

use App\Companies\Deletion\DeleteCompanyAction;
use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Company;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Loeschung ueber die vollstaendige Pipeline (SUN-RQ #8).
     *
     * Vorher hat diese Methode Oeffnungszeiten, Kategorien und Bilder
     * geloescht und das Profil soft-deleted — Bewertungen, Leads,
     * Statistiken, Stellenanzeigen, Nachweisdokumente, Sitemap, Index und
     * Caches blieben stehen. Es gibt jetzt nur noch einen Loeschweg:
     * {@see DeleteCompanyAction}. Jeder Schritt landet in `deletion_log`.
     */
    public function destroy(int $id, DeleteCompanyAction $action)
    {
        $company = $this->resolveCompany($id);
        $this->authorizeCompanyAccess($company, TenancyPermissionConstants::PERMISSION_DELETE_OWN_COMPANY);

        $name = $company->name;

        try {
            $result = $action->forCompany($company, [
                'reason' => 'Verwaltung: '.(auth()->user()?->email ?? 'unbekannt'),
            ]);
        } catch (Throwable $exception) {
            Log::error('Verwaltung: Loeschung fehlgeschlagen.', [
                'company_id' => $company->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('verwaltung.companies.index')
                ->with('error', "Firma \"{$name}\" konnte nicht gelöscht werden. Details im Protokoll.");
        }

        if (! $result->ok()) {
            return redirect()
                ->route('verwaltung.companies.index')
                ->with('warning', "Firma \"{$name}\" wurde gelöscht, aber nicht jeder Schritt lief durch. Protokoll: deletion_log, run_id {$result->runId}.");
        }

        return redirect()
            ->route('verwaltung.companies.index')
            ->with('success', "Firma \"{$name}\" wurde vollständig gelöscht.");
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
